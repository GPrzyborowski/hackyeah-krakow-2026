<?php

namespace Tests\Feature\Api\Shared;

use App\Enums\OfferCategory;
use App\Enums\OfferStatus;
use App\Enums\WorkMode;
use App\Models\Company;
use App\Models\CompanyReview;
use App\Models\JobOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Employer\InteractsWithEmployerFixtures;
use Tests\TestCase;

class PublicOfferTest extends TestCase
{
    use InteractsWithEmployerFixtures, RefreshDatabase;

    public function test_guest_lists_published_offers_with_web_filters_and_no_match_score(): void
    {
        $company = Company::factory()->create(['name' => 'Zielone Biuro']);
        CompanyReview::factory()->for($company)->create(['quote' => 'Elastyczne godziny po powrocie.']);
        $remote = JobOffer::factory()->published()->for($company)->create(['title' => 'Księgowa', 'work_mode' => WorkMode::Remote]);
        JobOffer::factory()->published()->for($company)->create(['title' => 'Kadrowa', 'work_mode' => WorkMode::Onsite]);
        JobOffer::factory()->for($company)->create(['title' => 'Szkic', 'status' => OfferStatus::Draft, 'work_mode' => WorkMode::Remote]);

        $this->getJson('/api/v1/public/offers?work_mode[]=remote&work_mode[]=bogus')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $remote->id)
            ->assertJsonPath('data.0.company.featured_quote.quote', 'Elastyczne godziny po powrocie.')
            ->assertJsonPath('meta.filters.work_mode', ['remote'])
            ->assertJsonPath('meta.total', 1)
            ->assertJsonMissingPath('data.0.score');
    }

    public function test_guest_filters_offers_by_category_and_sees_its_label(): void
    {
        $company = Company::factory()->create();
        $itOffer = JobOffer::factory()->published()->for($company)->create(['category' => OfferCategory::It]);
        JobOffer::factory()->published()->for($company)->create(['category' => OfferCategory::Education]);

        $this->getJson('/api/v1/public/offers?category[]=it&category[]=bogus')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $itOffer->id)
            ->assertJsonPath('data.0.category', 'it')
            ->assertJsonPath('data.0.category_label', 'IT i technologie')
            ->assertJsonPath('meta.filters.category', ['it']);

        $this->getJson("/api/v1/public/offers/{$itOffer->id}")
            ->assertOk()
            ->assertJsonPath('data.category', 'it');
    }

    public function test_guest_filters_offers_by_contract_type_and_sees_the_labels(): void
    {
        $company = Company::factory()->create();
        $mandateOffer = JobOffer::factory()->published()->for($company)->create(['contract_types' => ['employment', 'mandate']]);
        JobOffer::factory()->published()->for($company)->create(['contract_types' => ['employment']]);

        $this->getJson('/api/v1/public/offers?contract_type[]=mandate&contract_type[]=bogus')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mandateOffer->id)
            ->assertJsonPath('data.0.contract_types', ['employment', 'mandate'])
            ->assertJsonPath('data.0.contract_type_labels', ['Umowa o pracę', 'Umowa zlecenie'])
            ->assertJsonPath('meta.filters.contract_type', ['mandate']);

        $this->getJson("/api/v1/public/offers/{$mandateOffer->id}")
            ->assertOk()
            ->assertJsonPath('data.contract_types', ['employment', 'mandate']);
    }

    public function test_guest_filters_offers_with_a_nursery_nearby_and_sees_the_distance(): void
    {
        $company = Company::factory()->create();
        $nearby = JobOffer::factory()->published()->for($company)->create(['nursery_distance_km' => 2]);
        JobOffer::factory()->published()->for($company)->create(['nursery_distance_km' => 10]);
        JobOffer::factory()->published()->for($company)->create(['nursery_distance_km' => null]);

        $this->getJson('/api/v1/public/offers?nursery_nearby=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $nearby->id)
            ->assertJsonPath('data.0.nursery_distance_km', 2)
            ->assertJsonPath('meta.filters.nursery_nearby', true);
    }

    public function test_guest_sees_published_offer_detail_with_skills(): void
    {
        $offer = $this->publishedOffer(Company::factory()->create(), [$this->skill('Excel')], [$this->skill('SAP')]);

        $this->getJson("/api/v1/public/offers/{$offer->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $offer->id)
            ->assertJsonPath('data.required_skills', ['Excel'])
            ->assertJsonPath('data.nice_to_have_skills', ['SAP'])
            ->assertJsonPath('data.company.rating.count', 0);
    }

    public function test_unpublished_offer_is_404(): void
    {
        $draft = JobOffer::factory()->create(['status' => OfferStatus::Draft]);

        $this->getJson("/api/v1/public/offers/{$draft->id}")->assertNotFound();
    }

    public function test_guest_filters_by_reviews_and_start_date_and_sorts_by_rating(): void
    {
        $rated = Company::factory()->create();
        CompanyReview::factory()->for($rated)->create();
        $ratedOffer = JobOffer::factory()->published()->for($rated)->create(['start_date' => '2027-09-15', 'published_at' => now()->subWeek()]);
        JobOffer::factory()->published()->create(['start_date' => '2027-09-15', 'published_at' => now()]);
        JobOffer::factory()->published()->for($rated)->create(['start_date' => '2027-06-01']);

        $this->getJson('/api/v1/public/offers?with_reviews=1&start_from=2027-09-01&sort=rating')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ratedOffer->id)
            ->assertJsonPath('meta.filters.with_reviews', true)
            ->assertJsonPath('meta.filters.start_from', '2027-09-01')
            ->assertJsonPath('meta.filters.sort', 'rating');

        $this->getJson('/api/v1/public/offers?start_from=2027-09-01&sort=rating')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $ratedOffer->id);

        $this->getJson('/api/v1/public/offers?sort=bogus&start_from=nope')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.filters.sort', 'newest')
            ->assertJsonPath('meta.filters.start_from', null);
    }
}
