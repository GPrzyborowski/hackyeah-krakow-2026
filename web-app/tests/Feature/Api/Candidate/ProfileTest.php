<?php

namespace Tests\Feature\Api\Candidate;

use App\Enums\CvStatus;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\JobOffer;
use App\Models\Skill;
use App\Models\User;
use App\Services\Ai\CvAnalysis;
use App\Services\Ai\CvAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;

    private CandidateProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->candidate = User::factory()->create(['name' => 'Marta Kowalska', 'email' => 'marta@example.com']);
        $this->profile = $this->candidate->candidateProfile()->create(['onboarding_step' => 1]);
    }

    public function test_guests_get_401(): void
    {
        $this->getJson('/api/v1/candidate/profile')->assertUnauthorized();
    }

    public function test_employers_get_403(): void
    {
        Sanctum::actingAs(User::factory()->employer()->create());

        $this->getJson('/api/v1/candidate/profile')->assertForbidden();
    }

    public function test_candidate_sees_her_full_profile_including_private_dates(): void
    {
        $this->profile->update([
            'due_date' => '2027-01-02',
            'leave_starts_on' => '2026-12-01',
            'available_from' => '2027-09-01',
            'cv_path' => 'cvs/secret.pdf',
            'cv_text' => 'Tajne CV',
        ]);
        $this->profile->skills()->attach(Skill::factory()->create(['name' => 'Rekrutacja IT']), ['source' => 'manual', 'confirmed_at' => now()]);
        $this->profile->skills()->attach(Skill::factory()->create(['name' => 'Excel']), ['source' => 'ai', 'confirmed_at' => null]);
        Sanctum::actingAs($this->candidate);

        $this->getJson('/api/v1/candidate/profile')
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Marta Kowalska')
            ->assertJsonPath('data.anonymous_name', 'Marta K.')
            ->assertJsonPath('data.due_date', '2027-01-02')
            ->assertJsonPath('data.leave_starts_on', '2026-12-01')
            ->assertJsonPath('data.skills.0.name', 'Excel')
            ->assertJsonPath('data.skills.0.confirmed', false)
            ->assertJsonPath('data.skills.1.confirmed', true)
            ->assertJsonPath('data.cv.has_text', true)
            ->assertJsonPath('data.published', false)
            ->assertJsonMissingPath('data.cv_path')
            ->assertDontSee('cvs/secret.pdf');
    }

    public function test_cv_upload_runs_the_analyzer_and_returns_the_proposed_skills(): void
    {
        Storage::fake('local');
        $this->fakeAnalyzer(new CvAnalysis(
            skills: ['Rekrutacja IT'],
            positions: [['title' => 'Specjalistka ds. rekrutacji', 'score' => 92]],
            summary: 'Doświadczona rekruterka IT.',
        ));
        Sanctum::actingAs($this->candidate);

        $this->postJson('/api/v1/candidate/profile/cv', [
            'cv' => UploadedFile::fake()->create('CV.pdf', 200, 'application/pdf'),
        ])
            ->assertOk()
            ->assertJsonPath('meta.analysis_succeeded', true)
            ->assertJsonPath('data.cv.status', CvStatus::Parsed->value)
            ->assertJsonPath('data.cv.original_name', 'CV.pdf')
            ->assertJsonPath('data.skills.0.name', 'Rekrutacja IT')
            ->assertJsonPath('data.skills.0.confirmed', false)
            ->assertJsonPath('data.suggested_positions.0.score', 92)
            ->assertJsonPath('data.ai_summary', 'Doświadczona rekruterka IT.');

        Storage::disk('local')->assertExists($this->profile->refresh()->cv_path);
    }

    public function test_failed_cv_analysis_is_reported_in_meta(): void
    {
        $this->app->instance(CvAnalyzer::class, new class implements CvAnalyzer
        {
            public function analyze(CandidateProfile $profile): CvAnalysis
            {
                throw new RuntimeException('AI down');
            }
        });
        Sanctum::actingAs($this->candidate);

        $this->postJson('/api/v1/candidate/profile/cv', ['cv_text' => 'Rekrutacja IT'])
            ->assertOk()
            ->assertJsonPath('meta.analysis_succeeded', false)
            ->assertJsonPath('data.cv.status', CvStatus::Failed->value);
    }

    public function test_cv_requires_a_pdf_or_pasted_text(): void
    {
        Sanctum::actingAs($this->candidate);

        $this->postJson('/api/v1/candidate/profile/cv', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cv' => 'Dodaj plik PDF z CV albo wklej jego treść.']);
    }

    public function test_cv_analysis_is_rate_limited(): void
    {
        $this->fakeAnalyzer(new CvAnalysis(skills: []));
        Sanctum::actingAs($this->candidate);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/candidate/profile/cv', ['cv_text' => 'Rekrutacja IT'])->assertOk();
        }

        $this->postJson('/api/v1/candidate/profile/cv', ['cv_text' => 'Rekrutacja IT'])->assertTooManyRequests();
    }

    public function test_confirming_skills_confirms_all_tags_and_advances(): void
    {
        $this->profile->skills()->attach(Skill::factory()->create(), ['source' => 'ai', 'confirmed_at' => null]);
        Sanctum::actingAs($this->candidate);

        $this->postJson('/api/v1/candidate/profile/skills/confirm')
            ->assertOk()
            ->assertJsonPath('data.skills.0.confirmed', true)
            ->assertJsonPath('data.onboarding_step', 2);
    }

    public function test_confirming_without_skills_fails(): void
    {
        Sanctum::actingAs($this->candidate);

        $this->postJson('/api/v1/candidate/profile/skills/confirm')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['skills' => 'Dodaj przynajmniej jedną umiejętność.']);
    }

    public function test_preferences_are_saved(): void
    {
        Sanctum::actingAs($this->candidate);

        $this->putJson('/api/v1/candidate/profile/preferences', [
            'stage' => 'pregnant',
            'headline' => 'Rekruterka IT',
            'work_modes' => ['hybrid'],
            'employment_fractions' => ['3/5'],
            'wants_flexible_hours' => true,
            'open_to_job_sharing' => true,
            'preferred_day_part' => 'morning',
            'available_from' => '2027-09-01',
            'due_date' => '2027-01-02',
        ])
            ->assertOk()
            ->assertJsonPath('data.headline', 'Rekruterka IT')
            ->assertJsonPath('data.stage', 'pregnant')
            ->assertJsonPath('data.stage_label', 'W ciąży')
            ->assertJsonPath('data.due_date', '2027-01-02')
            ->assertJsonPath('data.work_modes.0.value', 'hybrid')
            ->assertJsonPath('data.employment_fractions.0.value', '3/5')
            ->assertJsonPath('data.preferred_day_part_label', 'Poranki')
            ->assertJsonPath('data.available_from', '2027-09-01')
            ->assertJsonPath('data.onboarding_step', 3);
    }

    public function test_preferences_require_available_from_and_valid_enums(): void
    {
        Sanctum::actingAs($this->candidate);

        $this->putJson('/api/v1/candidate/profile/preferences', ['work_modes' => ['office-only']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['available_from' => 'Podaj, od kiedy możesz zacząć pracę.'])
            ->assertJsonValidationErrors('work_modes.0');
    }

    public function test_privacy_toggles_are_partially_updated(): void
    {
        $company = Company::factory()->create(['name' => 'Obecny Pracodawca']);
        Sanctum::actingAs($this->candidate);

        $this->patchJson('/api/v1/candidate/profile/privacy', ['hidden_from_company_id' => $company->id])
            ->assertOk()
            ->assertJsonPath('data.privacy.hidden_from_company.name', 'Obecny Pracodawca');
    }

    public function test_summary_with_contact_details_is_rejected(): void
    {
        Sanctum::actingAs($this->candidate);

        $this->patchJson('/api/v1/candidate/profile/summary', ['ai_summary' => 'Napisz: marta@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ai_summary');

        $this->patchJson('/api/v1/candidate/profile/summary', ['ai_summary' => '  Rekruterka IT z 6-letnim stażem.  '])
            ->assertOk()
            ->assertJsonPath('data.ai_summary', 'Rekruterka IT z 6-letnim stażem.');
    }

    public function test_summary_updates_are_rate_limited(): void
    {
        Sanctum::actingAs($this->candidate);

        foreach (range(1, 20) as $ignored) {
            $this->patchJson('/api/v1/candidate/profile/summary', ['ai_summary' => 'Rekruterka IT.'])->assertOk();
        }

        $this->patchJson('/api/v1/candidate/profile/summary', ['ai_summary' => 'Rekruterka IT.'])->assertTooManyRequests();
    }

    public function test_publish_requires_available_from_and_a_confirmed_skill(): void
    {
        Sanctum::actingAs($this->candidate);

        $this->postJson('/api/v1/candidate/profile/publish')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['available_from', 'skills']);
    }

    public function test_publish_makes_the_profile_visible(): void
    {
        $this->profile->update(['available_from' => '2027-09-01', 'stage' => 'after_leave']);
        $this->profile->skills()->attach(Skill::factory()->create(), ['source' => 'manual', 'confirmed_at' => now()]);
        Sanctum::actingAs($this->candidate);

        $this->postJson('/api/v1/candidate/profile/publish', ['allow_direct_messages' => true])
            ->assertOk()
            ->assertJsonPath('data.published', true)
            ->assertJsonPath('data.onboarding_step', 4)
            ->assertJsonPath('data.privacy.allow_direct_messages', true);
    }

    public function test_candidate_can_hide_and_show_her_profile(): void
    {
        $this->profile->update(['available_from' => '2027-09-01', 'stage' => 'after_leave', 'published_at' => now(), 'onboarding_step' => 4]);
        $this->profile->skills()->attach(Skill::factory()->create(), ['source' => 'manual', 'confirmed_at' => now()]);
        Sanctum::actingAs($this->candidate);

        $this->postJson('/api/v1/candidate/profile/visibility', ['visible' => false])
            ->assertOk()
            ->assertJsonPath('data.published', false);

        $this->postJson('/api/v1/candidate/profile/visibility', ['visible' => true])
            ->assertOk()
            ->assertJsonPath('data.published', true);

        $this->postJson('/api/v1/candidate/profile/visibility', [])->assertUnprocessable()->assertJsonValidationErrors('visible');
    }

    public function test_employer_preview_shows_only_the_anonymous_allowlist(): void
    {
        $this->profile->update(['due_date' => '2027-01-02', 'leave_starts_on' => '2026-12-01', 'available_from' => '2027-09-01']);
        $this->profile->skills()->attach(Skill::factory()->create(['name' => 'Rekrutacja IT']), ['source' => 'manual', 'confirmed_at' => now()]);
        $this->profile->skills()->attach(Skill::factory()->create(['name' => 'Niezatwierdzona']), ['source' => 'ai', 'confirmed_at' => null]);
        Sanctum::actingAs($this->candidate);

        $this->getJson('/api/v1/candidate/profile/employer-preview')
            ->assertOk()
            ->assertJsonPath('data.anonymous_name', 'Marta K.')
            ->assertJsonPath('data.skills', [['name' => 'Rekrutacja IT', 'matched' => false]])
            ->assertDontSee('Kowalska')
            ->assertDontSee('marta@example.com')
            ->assertDontSee('2027-01-02')
            ->assertDontSee('2026-12-01');
    }

    public function test_options_list_the_choices_for_profile_forms(): void
    {
        Company::factory()->create(['name' => 'Zielone Biuro']);
        Sanctum::actingAs($this->candidate);

        $this->getJson('/api/v1/candidate/profile/options')
            ->assertOk()
            ->assertJsonPath('data.companies.0.name', 'Zielone Biuro')
            ->assertJsonPath('data.day_parts.0.value', 'morning')
            ->assertJsonCount(4, 'data.employment_fractions');
    }

    public function test_skill_suggestions_contain_only_dictionary_and_offer_skills(): void
    {
        Skill::factory()->create(['name' => 'Excel']);
        Skill::findOrCreateByName('Zielone Biuro HR')->jobOffers()->attach(JobOffer::factory()->create(), ['importance' => 'required']);
        Skill::findOrCreateByName('marta.prywatnie');
        Sanctum::actingAs($this->candidate);

        $this->getJson('/api/v1/candidate/profile/options')
            ->assertOk()
            ->assertJsonPath('data.skill_suggestions', ['Excel', 'Zielone Biuro HR']);
    }

    private function fakeAnalyzer(CvAnalysis $analysis): void
    {
        $this->app->instance(CvAnalyzer::class, new readonly class($analysis) implements CvAnalyzer
        {
            public function __construct(private CvAnalysis $analysis) {}

            public function analyze(CandidateProfile $profile): CvAnalysis
            {
                return $this->analysis;
            }
        });
    }
}
