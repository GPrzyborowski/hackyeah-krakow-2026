<?php

namespace Tests\Feature\Api\Candidate;

use App\Enums\EmploymentFraction;
use App\Enums\WorkMode;
use App\Models\Company;
use App\Models\CompanyReview;
use App\Models\JobOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Employer\InteractsWithEmployerFixtures;
use Tests\TestCase;

class OfferTest extends TestCase
{
    use InteractsWithEmployerFixtures, RefreshDatabase;

    public function test_offers_are_ranked_by_match_with_card_data(): void
    {
        $skill = $this->skill('Rekrutacja IT');
        $otherSkill = $this->skill('Onboarding');
        $candidate = $this->candidate([$skill]);
        $company = Company::factory()->create(['name' => 'Zielone Biuro']);
        CompanyReview::factory()->for($company)->create(['quote' => 'Wróciłam bez stresu.']);
        $best = $this->publishedOffer($company, [$skill]);
        $best->update(['salary_min' => 6000, 'salary_max' => 8000, 'flexible_hours' => true]);
        $weaker = $this->publishedOffer(Company::factory()->create(), [$otherSkill]);
        JobOffer::factory()->for($company)->create(['status' => 'draft']);
        $candidate->interests()->create(['job_offer_id' => $best->id]);
        $candidate->savedOffers()->attach($weaker);
        Sanctum::actingAs($candidate->user);

        $this->getJson('/api/v1/candidate/offers')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $best->id)
            ->assertJsonPath('data.0.match.score', 100)
            ->assertJsonPath('data.0.salary_min', 6000)
            ->assertJsonPath('data.0.is_parent_friendly', true)
            ->assertJsonPath('data.0.is_interested', true)
            ->assertJsonPath('data.0.is_saved', false)
            ->assertJsonPath('data.0.company.name', 'Zielone Biuro')
            ->assertJsonPath('data.0.company.first_review.quote', 'Wróciłam bez stresu.')
            ->assertJsonPath('data.1.id', $weaker->id)
            ->assertJsonPath('data.1.is_saved', true)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.filters.sort', 'match')
            ->assertJsonPath('meta.filters.start_from', '2027-09-01')
            ->assertJsonPath('meta.has_confirmed_skills', true);
    }

    public function test_filters_match_the_web_list(): void
    {
        $skill = $this->skill('Rekrutacja IT');
        $candidate = $this->candidate([$skill]);
        $remote = $this->publishedOffer(Company::factory()->create(), [$skill]);
        $remote->update(['work_mode' => WorkMode::Remote, 'employment_fraction' => EmploymentFraction::Half]);
        $saved = $this->publishedOffer(Company::factory()->create(), [$skill]);
        $candidate->savedOffers()->attach($saved);
        Sanctum::actingAs($candidate->user);

        $this->getJson('/api/v1/candidate/offers?'.http_build_query(['work_modes' => ['remote'], 'employment_fractions' => ['1/2']]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $remote->id);

        $this->getJson('/api/v1/candidate/offers?saved=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $saved->id);

        $this->getJson('/api/v1/candidate/offers?sort=oldest')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sort');
    }

    public function test_nursery_nearby_filter_and_distance_on_cards(): void
    {
        $skill = $this->skill('Rekrutacja IT');
        $candidate = $this->candidate([$skill]);
        $nearby = $this->publishedOffer(Company::factory()->create(), [$skill]);
        $nearby->update(['nursery_distance_km' => 3]);
        $far = $this->publishedOffer(Company::factory()->create(), [$skill]);
        $far->update(['nursery_distance_km' => 4]);
        $this->publishedOffer(Company::factory()->create(), [$skill]);
        Sanctum::actingAs($candidate->user);

        $this->getJson('/api/v1/candidate/offers?nursery_nearby=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $nearby->id)
            ->assertJsonPath('data.0.nursery_distance_km', 3)
            ->assertJsonPath('meta.filters.nursery_nearby', true);

        $this->getJson("/api/v1/candidate/offers/{$far->id}")
            ->assertOk()
            ->assertJsonPath('data.nursery_distance_km', 4);

        $this->getJson('/api/v1/candidate/offers?nursery_nearby=maybe')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nursery_nearby');
    }

    public function test_offers_list_is_paginated_without_n_plus_one_queries(): void
    {
        $skill = $this->skill('Rekrutacja IT');
        $candidate = $this->candidate([$skill]);

        foreach (range(1, 25) as $index) {
            $company = Company::factory()->create();
            CompanyReview::factory()->for($company)->create();
            $this->publishedOffer($company, [$skill]);
        }

        Sanctum::actingAs($candidate->user);
        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        $this->getJson('/api/v1/candidate/offers')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.last_page', 2);
        $this->assertLessThanOrEqual(15, $queryCount);

        $this->getJson('/api/v1/candidate/offers?page=2')->assertOk()->assertJsonCount(5, 'data');
    }

    public function test_offer_detail_has_match_breakdown_and_reviews(): void
    {
        $skill = $this->skill('Rekrutacja IT');
        $missing = $this->skill('Onboarding');
        $candidate = $this->candidate([$skill]);
        $company = Company::factory()->create(['description' => 'Biuro przy parku.']);
        CompanyReview::factory()->for($company)->create(['quote' => 'Elastyczne godziny naprawdę działają.']);
        $offer = $this->publishedOffer($company, [$skill, $missing]);
        Sanctum::actingAs($candidate->user);

        $this->getJson("/api/v1/candidate/offers/{$offer->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $offer->id)
            ->assertJsonPath('data.match.matched_required', ['Rekrutacja IT'])
            ->assertJsonPath('data.match.missing_required', ['Onboarding'])
            ->assertJsonPath('data.company_description', 'Biuro przy parku.')
            ->assertJsonPath('data.reviews.0.quote', 'Elastyczne godziny naprawdę działają.')
            ->assertJsonPath('data.job_sharing', null);
    }

    public function test_job_share_offer_detail_includes_the_panel(): void
    {
        $skill = $this->skill('Rekrutacja IT');
        $candidate = $this->candidate([$skill], ['open_to_job_sharing' => true]);
        $offer = $this->publishedOffer(Company::factory()->create(), [$skill]);
        $offer->update(['is_job_share' => true, 'workday_starts_at' => '08:00', 'workday_ends_at' => '16:00']);
        Sanctum::actingAs($candidate->user);

        $this->getJson("/api/v1/candidate/offers/{$offer->id}")
            ->assertOk()
            ->assertJsonPath('data.job_sharing.hours_per_person', 4)
            ->assertJsonPath('data.job_sharing.is_open_to_job_sharing', true)
            ->assertJsonPath('data.job_sharing.pair', null);
    }

    public function test_unpublished_offer_returns_404(): void
    {
        $candidate = $this->candidate([]);
        $draft = JobOffer::factory()->create(['status' => 'draft']);
        Sanctum::actingAs($candidate->user);

        $this->getJson("/api/v1/candidate/offers/{$draft->id}")->assertNotFound();
    }
}
