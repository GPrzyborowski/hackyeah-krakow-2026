<?php

namespace Tests\Feature\Candidate;

use App\Enums\CvStatus;
use App\Enums\SkillSource;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Skill;
use App\Models\User;
use App\Services\Ai\CvAnalysis;
use App\Services\Ai\CvAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;

    private CandidateProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->candidate = User::factory()->create(['name' => 'Marta Kowalska']);
        $this->profile = $this->candidate->candidateProfile()->create(['onboarding_step' => 1]);
    }

    public function test_onboarding_page_starts_at_cv_step_for_new_candidate(): void
    {
        $this->actingAs($this->candidate)
            ->get(route('candidate.onboarding.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('candidate/Onboarding')
                ->where('step', 2)
                ->where('profile.anonymous_name', 'Marta K.')
                ->where('profile.is_published', false));
    }

    public function test_candidate_cannot_skip_ahead_of_completed_steps(): void
    {
        $this->actingAs($this->candidate)
            ->get(route('candidate.onboarding.show', ['step' => 4]))
            ->assertInertia(fn (Assert $page) => $page->where('step', 2));
    }

    public function test_cv_upload_is_stored_privately_and_analysis_is_applied(): void
    {
        Storage::fake('local');
        $this->fakeAnalyzer(new CvAnalysis(
            skills: ['Rekrutacja IT', 'Onboarding'],
            positions: [['title' => 'Specjalistka ds. rekrutacji', 'score' => 92]],
            summary: 'Doświadczona rekruterka IT.',
            headline: 'Specjalistka ds. rekrutacji',
            yearsOfExperience: 6,
            extractedText: 'Tekst z PDF',
        ));
        Skill::factory()->create(['name' => 'Onboarding', 'slug' => 'onboarding']);

        $this->actingAs($this->candidate)
            ->post(route('candidate.onboarding.cv'), [
                'cv' => UploadedFile::fake()->create('CV_Marta_K.pdf', 200, 'application/pdf'),
            ])
            ->assertRedirect(route('candidate.onboarding.show', ['step' => 2]));

        $this->profile->refresh();
        Storage::disk('local')->assertExists($this->profile->cv_path);
        $this->assertStringStartsWith('cvs/', $this->profile->cv_path);
        $this->assertSame('CV_Marta_K.pdf', $this->profile->cv_original_name);
        $this->assertSame(CvStatus::Parsed, $this->profile->cv_status);
        $this->assertSame('Specjalistka ds. rekrutacji', $this->profile->headline);
        $this->assertSame(6, $this->profile->years_of_experience);
        $this->assertSame('Doświadczona rekruterka IT.', $this->profile->ai_summary);
        $this->assertSame('Tekst z PDF', $this->profile->cv_text);
        $this->assertEquals([['title' => 'Specjalistka ds. rekrutacji', 'score' => 92]], $this->profile->suggested_positions);

        $skills = $this->profile->skills()->get();
        $this->assertEqualsCanonicalizing(['Rekrutacja IT', 'Onboarding'], $skills->pluck('name')->all());
        $this->assertTrue($skills->every(fn (Skill $skill): bool => $skill->pivot->source === SkillSource::Ai->value && $skill->pivot->confirmed_at === null));
        $this->assertSame(0, $this->profile->confirmedSkills()->count());
        $this->assertSame(2, Skill::query()->count());
    }

    public function test_analysis_does_not_overwrite_filled_headline_or_pasted_text(): void
    {
        $this->profile->update(['headline' => 'Moja rola', 'years_of_experience' => 3]);
        $this->fakeAnalyzer(new CvAnalysis(skills: [], headline: 'Inna rola', yearsOfExperience: 9, extractedText: 'Inny tekst'));

        $this->actingAs($this->candidate)
            ->post(route('candidate.onboarding.cv'), ['cv_text' => 'Wklejone CV'])
            ->assertRedirect();

        $this->profile->refresh();
        $this->assertSame('Moja rola', $this->profile->headline);
        $this->assertSame(3, $this->profile->years_of_experience);
        $this->assertSame('Wklejone CV', $this->profile->cv_text);
    }

    public function test_failed_analysis_marks_cv_as_failed(): void
    {
        $this->app->instance(CvAnalyzer::class, new class implements CvAnalyzer
        {
            public function analyze(CandidateProfile $profile): CvAnalysis
            {
                throw new RuntimeException('AI down');
            }
        });

        $this->actingAs($this->candidate)
            ->post(route('candidate.onboarding.cv'), ['cv_text' => 'Rekrutacja IT'])
            ->assertRedirect();

        $this->assertSame(CvStatus::Failed, $this->profile->refresh()->cv_status);
    }

    public function test_cv_analysis_is_rate_limited(): void
    {
        $this->fakeAnalyzer(new CvAnalysis(skills: []));

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->actingAs($this->candidate)
                ->post(route('candidate.onboarding.cv'), ['cv_text' => 'Rekrutacja IT'])
                ->assertRedirect();
        }

        $this->actingAs($this->candidate)
            ->post(route('candidate.onboarding.cv'), ['cv_text' => 'Rekrutacja IT'])
            ->assertTooManyRequests();
    }

    public function test_candidate_sees_and_edits_the_summary_shown_to_employers(): void
    {
        $this->profile->update(['ai_summary' => 'Doświadczona rekruterka IT.']);

        $this->actingAs($this->candidate)
            ->get(route('candidate.onboarding.show'))
            ->assertInertia(fn (Assert $page) => $page->where('profile.ai_summary', 'Doświadczona rekruterka IT.'));

        $this->actingAs($this->candidate)
            ->patch(route('candidate.onboarding.summary'), ['ai_summary' => '  Od 6 lat prowadzę rekrutacje IT i onboarding.  '])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame('Od 6 lat prowadzę rekrutacje IT i onboarding.', $this->profile->refresh()->ai_summary);
    }

    #[DataProvider('rejectedSummaries')]
    public function test_summary_with_contact_details_or_family_information_is_rejected(string $summary): void
    {
        $this->profile->update(['ai_summary' => 'Doświadczona rekruterka IT.']);

        $this->actingAs($this->candidate)
            ->patch(route('candidate.onboarding.summary'), ['ai_summary' => $summary])
            ->assertSessionHasErrors('ai_summary');

        $this->assertSame('Doświadczona rekruterka IT.', $this->profile->refresh()->ai_summary);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rejectedSummaries(): array
    {
        return [
            'e-mail' => ['Rekruterka IT, pisz na marta.kowalska@example.com'],
            'phone' => ['Rekruterka IT, tel. +48 601 234 567'],
            'pregnancy' => ['Rekruterka IT, obecnie w ciąży.'],
            'too long' => [str_repeat('a', 401)],
        ];
    }

    public function test_cv_step_requires_a_pdf_or_pasted_text(): void
    {
        Storage::fake('local');

        $this->actingAs($this->candidate)
            ->post(route('candidate.onboarding.cv'), [])
            ->assertSessionHasErrors(['cv', 'cv_text']);

        $this->actingAs($this->candidate)
            ->post(route('candidate.onboarding.cv'), ['cv' => UploadedFile::fake()->create('cv.docx', 10)])
            ->assertSessionHasErrors('cv');
    }

    public function test_candidate_can_add_and_remove_tags_manually(): void
    {
        $this->actingAs($this->candidate)
            ->post(route('candidate.skills.store'), ['name' => 'Employer branding'])
            ->assertRedirect();

        $skill = Skill::query()->where('name', 'Employer branding')->firstOrFail();
        $this->assertSame(SkillSource::Manual->value, $this->profile->skills()->first()->pivot->source);
        $this->assertSame(1, $this->profile->confirmedSkills()->count());

        $this->actingAs($this->candidate)
            ->delete(route('candidate.skills.destroy', $skill))
            ->assertRedirect();

        $this->assertSame(0, $this->profile->skills()->count());
    }

    public function test_confirming_skills_confirms_remaining_tags_and_advances(): void
    {
        $skill = Skill::factory()->create();
        $this->profile->skills()->attach($skill, ['source' => SkillSource::Ai->value, 'confirmed_at' => null]);

        $this->actingAs($this->candidate)
            ->post(route('candidate.onboarding.skills.confirm'))
            ->assertRedirect(route('candidate.onboarding.show', ['step' => 3]));

        $this->assertSame(1, $this->profile->confirmedSkills()->count());
        $this->assertSame(2, $this->profile->refresh()->onboarding_step);
    }

    public function test_confirming_without_any_skill_fails(): void
    {
        $this->actingAs($this->candidate)
            ->post(route('candidate.onboarding.skills.confirm'))
            ->assertSessionHasErrors('skills');

        $this->assertSame(1, $this->profile->refresh()->onboarding_step);
    }

    public function test_preferences_are_saved_and_step_advances(): void
    {
        $this->profile->update(['onboarding_step' => 2]);

        $this->actingAs($this->candidate)
            ->put(route('candidate.onboarding.preferences'), [
                'headline' => 'HR Business Partner',
                'years_of_experience' => 6,
                'city' => 'Poznań',
                'work_modes' => ['remote', 'hybrid'],
                'employment_fractions' => ['3/5'],
                'wants_flexible_hours' => true,
                'open_to_job_sharing' => true,
                'available_from' => '2027-09-01',
                'leave_starts_on' => '2027-03-14',
                'due_date' => '2027-04-10',
            ])
            ->assertRedirect(route('candidate.onboarding.show', ['step' => 4]));

        $this->profile->refresh();
        $this->assertSame(3, $this->profile->onboarding_step);
        $this->assertSame(['remote', 'hybrid'], $this->profile->work_modes);
        $this->assertSame('2027-09-01', $this->profile->available_from->toDateString());
        $this->assertSame('2027-04-10', $this->profile->due_date->toDateString());
        $this->assertTrue($this->profile->open_to_job_sharing);
    }

    public function test_preferences_require_available_from_and_valid_enums(): void
    {
        $this->actingAs($this->candidate)
            ->put(route('candidate.onboarding.preferences'), ['work_modes' => ['moon']])
            ->assertSessionHasErrors(['available_from', 'work_modes.0']);
    }

    public function test_going_back_does_not_reduce_progress(): void
    {
        $this->profile->update(['onboarding_step' => 3]);
        $this->profile->skills()->attach(Skill::factory()->create(), ['source' => 'manual', 'confirmed_at' => now()]);

        $this->actingAs($this->candidate)->post(route('candidate.onboarding.skills.confirm'));

        $this->assertSame(3, $this->profile->refresh()->onboarding_step);
    }

    public function test_privacy_toggles_autosave(): void
    {
        $company = Company::factory()->create();

        $this->actingAs($this->candidate)
            ->patch(route('candidate.onboarding.privacy'), ['allow_direct_messages' => true, 'hidden_from_company_id' => $company->id])
            ->assertRedirect();

        $this->profile->refresh();
        $this->assertTrue($this->profile->allow_direct_messages);
        $this->assertSame($company->id, $this->profile->hidden_from_company_id);
        $this->assertTrue($this->profile->show_availability_instead_of_gap);
    }

    public function test_publish_requires_available_from_and_a_confirmed_skill(): void
    {
        $this->profile->skills()->attach(Skill::factory()->create(), ['source' => 'ai', 'confirmed_at' => null]);

        $this->actingAs($this->candidate)
            ->post(route('candidate.onboarding.publish'))
            ->assertSessionHasErrors(['available_from', 'skills']);

        $this->assertNull($this->profile->refresh()->published_at);
    }

    public function test_publish_makes_profile_visible_and_redirects_home(): void
    {
        $this->profile->update(['available_from' => '2027-09-01', 'onboarding_step' => 3]);
        $this->profile->skills()->attach(Skill::factory()->create(), ['source' => 'ai', 'confirmed_at' => now()]);

        $this->actingAs($this->candidate)
            ->post(route('candidate.onboarding.publish'))
            ->assertRedirect(route('candidate.home'));

        $this->profile->refresh();
        $this->assertNotNull($this->profile->published_at);
        $this->assertSame(4, $this->profile->onboarding_step);
    }

    public function test_published_candidate_can_hide_and_show_profile(): void
    {
        $profile = CandidateProfile::factory()->published()->create();
        $profile->skills()->attach(Skill::factory()->create(), ['source' => 'ai', 'confirmed_at' => now()]);

        $this->actingAs($profile->user)->post(route('candidate.onboarding.visibility'));
        $this->assertNull($profile->refresh()->published_at);

        $this->actingAs($profile->user)->post(route('candidate.onboarding.visibility'));
        $this->assertNotNull($profile->refresh()->published_at);
    }

    public function test_published_candidate_can_open_any_step_as_profile_editor(): void
    {
        $profile = CandidateProfile::factory()->published()->create();

        $this->actingAs($profile->user)
            ->get(route('candidate.onboarding.show', ['step' => 4]))
            ->assertInertia(fn (Assert $page) => $page->where('step', 4)->where('profile.is_published', true));
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
