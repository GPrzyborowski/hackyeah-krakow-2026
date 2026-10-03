<?php

namespace Tests\Feature\Api\Shared;

use App\Enums\OfferStatus;
use App\Enums\ReviewStatus;
use App\Models\Company;
use App\Models\CompanyReview;
use App\Models\JobOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_only_approved_reviews_without_authors_and_only_published_offers(): void
    {
        $company = Company::factory()->create();
        $author = User::factory()->create(['name' => 'Marta Kowalska', 'email' => 'marta@example.com']);
        CompanyReview::factory()->for($company)->create(['user_id' => $author->id, 'quote' => 'Bardzo dobra firma dla mam.']);
        CompanyReview::factory()->for($company)->create(['status' => ReviewStatus::Pending, 'quote' => 'Czeka na moderację.']);
        $published = JobOffer::factory()->published()->for($company)->create();
        JobOffer::factory()->for($company)->create(['status' => OfferStatus::Draft]);

        $this->getJson("/api/v1/public/companies/{$company->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $company->id)
            ->assertJsonCount(1, 'data.reviews')
            ->assertJsonPath('data.reviews.0.quote', 'Bardzo dobra firma dla mam.')
            ->assertJsonPath('data.rating.count', 1)
            ->assertJsonCount(1, 'data.offers')
            ->assertJsonPath('data.offers.0.id', $published->id)
            ->assertJsonMissingPath('data.reviews.0.user_id')
            ->assertDontSee('Czeka na moderację.')
            ->assertDontSee('Kowalska')
            ->assertDontSee('marta@example.com');
    }

    public function test_unknown_company_is_404(): void
    {
        $this->getJson('/api/v1/public/companies/999')->assertNotFound();
    }
}
