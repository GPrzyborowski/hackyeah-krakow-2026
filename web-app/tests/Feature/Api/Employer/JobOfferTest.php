<?php

namespace Tests\Feature\Api\Employer;

use App\Enums\InvitationStatus;
use App\Enums\OfferCategory;
use App\Enums\OfferStatus;
use App\Models\CompanyReview;
use App\Models\Invitation;
use App\Models\JobOffer;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Employer\InteractsWithEmployerFixtures;
use Tests\TestCase;

class JobOfferTest extends TestCase
{
    use InteractsWithEmployerFixtures, RefreshDatabase;

    public function test_index_lists_only_own_company_offers_with_statistics(): void
    {
        $employer = $this->employer();
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->publishedOffer($employer->company, [$recruitment]);
        $offer->update(['salary_min' => 8000, 'salary_max' => 10000, 'flexible_hours' => true]);
        CompanyReview::factory()->for($employer->company)->create();
        $this->publishedOffer($this->employer()->company, [$recruitment]);
        $this->candidate([$recruitment]);
        $decided = $this->candidate([$recruitment], name: 'Anna Nowak');
        $offer->decisions()->create(['candidate_profile_id' => $decided->id, 'decision' => 'skipped']);
        Invitation::factory()->accepted()->for($offer)->create();
        Sanctum::actingAs($employer);

        $this->getJson('/api/v1/employer/offers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $offer->id)
            ->assertJsonPath('data.0.status', 'published')
            ->assertJsonPath('data.0.status_label', 'Opublikowana')
            ->assertJsonPath('data.0.required_skills', ['Rekrutacja IT'])
            ->assertJsonPath('data.0.is_parent_friendly', true)
            ->assertJsonPath('data.0.statistics.matched_count', 2)
            ->assertJsonPath('data.0.statistics.to_review_count', 1)
            ->assertJsonPath('data.0.statistics.invited_count', 1)
            ->assertJsonPath('data.0.statistics.accepted_count', 1)
            ->assertJsonPath('data.0.submitted_pairs_count', 0)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_index_filters_by_status(): void
    {
        $employer = $this->employer();
        $this->publishedOffer($employer->company, [$this->skill('Excel')]);
        $draft = JobOffer::factory()->for($employer->company)->create(['status' => OfferStatus::Draft]);
        Sanctum::actingAs($employer);

        $this->getJson('/api/v1/employer/offers?status=draft')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $draft->id);

        $this->getJson('/api/v1/employer/offers?status=archived')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_index_does_not_query_per_candidate(): void
    {
        $employer = $this->employer();
        $skill = $this->skill('Rekrutacja IT');
        $this->publishedOffer($employer->company, [$skill]);

        foreach (range(1, 20) as $index) {
            $this->candidate([$skill], name: "Kandydatka{$index} Nowak");
        }

        Sanctum::actingAs($employer);
        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        $this->getJson('/api/v1/employer/offers')->assertOk()->assertJsonPath('data.0.statistics.matched_count', 20);

        $this->assertLessThanOrEqual(15, $queryCount);
    }

    public function test_employer_saves_a_draft_and_creates_unknown_skills(): void
    {
        $employer = $this->employer();
        $this->skill('Onboarding');
        Sanctum::actingAs($employer);

        $this->postJson('/api/v1/employer/offers', $this->payload([
            'required_skills' => ['Onboarding', 'Prawo pracy'],
            'nice_to_have_skills' => ['Excel'],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.published_at', null)
            ->assertJsonPath('data.nice_to_have_skills', ['Excel']);

        $offer = JobOffer::sole();
        $this->assertSame($employer->company_id, $offer->company_id);
        $this->assertEqualsCanonicalizing(['Onboarding', 'Prawo pracy'], $offer->requiredSkills()->pluck('name')->all());
        $this->assertSame(3, Skill::count());
    }

    public function test_category_is_optional_for_older_app_versions_and_defaults_to_other(): void
    {
        $employer = $this->employer();
        Sanctum::actingAs($employer);

        $this->postJson('/api/v1/employer/offers', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.category', 'other')
            ->assertJsonPath('data.category_label', 'Inne');

        $offer = JobOffer::sole();
        $this->assertSame(OfferCategory::Other, $offer->category);

        $this->putJson("/api/v1/employer/offers/{$offer->id}", $this->payload(['category' => 'health']))
            ->assertOk()
            ->assertJsonPath('data.category', 'health')
            ->assertJsonPath('data.category_label', 'Medycyna i zdrowie');

        $this->putJson("/api/v1/employer/offers/{$offer->id}", $this->payload())
            ->assertOk()
            ->assertJsonPath('data.category', 'health');

        $this->putJson("/api/v1/employer/offers/{$offer->id}", $this->payload(['category' => 'astronomy']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category');
    }

    public function test_contract_types_are_optional_for_older_app_versions_and_default_to_employment(): void
    {
        $employer = $this->employer();
        Sanctum::actingAs($employer);

        $this->postJson('/api/v1/employer/offers', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.contract_types', ['employment'])
            ->assertJsonPath('data.contract_type_labels', ['Umowa o pracę']);

        $offer = JobOffer::sole();

        $this->putJson("/api/v1/employer/offers/{$offer->id}", $this->payload(['contract_types' => ['mandate']]))
            ->assertOk()
            ->assertJsonPath('data.contract_types', ['mandate'])
            ->assertJsonPath('data.contract_type_labels', ['Umowa zlecenie']);

        $this->putJson("/api/v1/employer/offers/{$offer->id}", $this->payload())
            ->assertOk()
            ->assertJsonPath('data.contract_types', ['mandate']);

        $this->putJson("/api/v1/employer/offers/{$offer->id}", $this->payload(['contract_types' => []]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contract_types');

        $this->putJson("/api/v1/employer/offers/{$offer->id}", $this->payload(['contract_types' => ['b2b']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contract_types.0');
    }

    public function test_employer_publishes_a_job_share_offer(): void
    {
        Sanctum::actingAs($this->employer());

        $this->postJson('/api/v1/employer/offers', $this->payload([
            'action' => 'publish',
            'is_job_share' => true,
            'employment_fraction' => '1/2',
            'workday_starts_at' => '08:00',
            'workday_ends_at' => '16:00',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.is_job_share', true)
            ->assertJsonPath('data.workday_starts_at', '08:00');

        $this->assertNotNull(JobOffer::sole()->published_at);
    }

    public function test_publishing_validates_like_the_web_form(): void
    {
        Sanctum::actingAs($this->employer());

        $this->postJson('/api/v1/employer/offers', $this->payload([
            'action' => 'publish',
            'required_skills' => [],
            'salary_min' => 9000,
            'salary_max' => 8000,
            'start_date' => now()->subDay()->toDateString(),
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'required_skills' => 'Dodaj co najmniej jedną wymaganą umiejętność, aby opublikować ofertę.',
                'salary_max' => 'Górna granica wynagrodzenia nie może być niższa od dolnej.',
                'start_date' => 'Planowany start nie może być w przeszłości.',
            ]);

        $this->assertSame(0, JobOffer::count());
    }

    public function test_job_share_offer_requires_workday_hours(): void
    {
        Sanctum::actingAs($this->employer());

        $this->postJson('/api/v1/employer/offers', $this->payload(['is_job_share' => true]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['workday_starts_at', 'workday_ends_at']);
    }

    public function test_title_and_description_are_moderated(): void
    {
        Sanctum::actingAs($this->employer());

        $this->postJson('/api/v1/employer/offers', $this->payload([
            'title' => 'Asystentka (nie w ciąży)',
            'description' => 'Czy planuje Pani dzieci w najbliższym czasie?',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'description']);

        $this->assertSame(0, JobOffer::count());
    }

    public function test_employer_shows_and_updates_own_offer(): void
    {
        $employer = $this->employer();
        $offer = $this->publishedOffer($employer->company, [$this->skill('Excel')]);
        Sanctum::actingAs($employer);

        $this->getJson("/api/v1/employer/offers/{$offer->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $offer->id)
            ->assertJsonPath('data.required_skills', ['Excel']);

        $this->putJson("/api/v1/employer/offers/{$offer->id}", $this->payload(['action' => 'publish', 'title' => 'Księgowa']))
            ->assertOk()
            ->assertJsonPath('data.title', 'Księgowa')
            ->assertJsonPath('data.required_skills', ['Onboarding']);

        $this->assertSame('Księgowa', $offer->refresh()->title);
    }

    public function test_employer_cannot_view_edit_or_close_another_company_offer(): void
    {
        $offer = $this->publishedOffer($this->employer()->company, [$this->skill('Excel')]);
        Sanctum::actingAs($this->employer());

        $this->getJson("/api/v1/employer/offers/{$offer->id}")->assertForbidden();
        $this->putJson("/api/v1/employer/offers/{$offer->id}", $this->payload(['title' => 'Przejęta']))->assertForbidden();
        $this->postJson("/api/v1/employer/offers/{$offer->id}/close")->assertForbidden();

        $this->assertNotSame('Przejęta', $offer->refresh()->title);
        $this->assertSame(OfferStatus::Published, $offer->status);
    }

    public function test_closing_an_offer_withdraws_pending_invitations(): void
    {
        $employer = $this->employer();
        $offer = JobOffer::factory()->published()->for($employer->company)->create();
        $pending = Invitation::factory()->for($offer)->create(['status' => InvitationStatus::Pending]);
        $accepted = Invitation::factory()->accepted()->for($offer)->create();
        Sanctum::actingAs($employer);

        $this->postJson("/api/v1/employer/offers/{$offer->id}/close")
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');

        $this->assertSame(InvitationStatus::Withdrawn, $pending->refresh()->status);
        $this->assertSame(InvitationStatus::Accepted, $accepted->refresh()->status);

        $this->postJson("/api/v1/employer/offers/{$offer->id}/close")->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'action' => 'draft',
            'title' => 'Specjalistka ds. rekrutacji',
            'city' => 'Poznań',
            'work_mode' => 'hybrid',
            'start_date' => '2027-09-01',
            'description' => 'Prowadzenie procesów rekrutacyjnych w zespołach IT.',
            'employment_fraction' => '3/5',
            'salary_min' => 8500,
            'salary_max' => 11000,
            'flexible_hours' => true,
            'fixed_meeting_hours' => true,
            'childcare_subsidy' => false,
            'required_skills' => ['Onboarding'],
            'nice_to_have_skills' => [],
            ...$overrides,
        ];
    }
}
