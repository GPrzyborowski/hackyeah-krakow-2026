<?php

namespace Tests\Feature\Public;

use App\Enums\OfferStatus;
use App\Enums\ReviewStatus;
use App\Models\Company;
use App\Models\CompanyReview;
use App\Models\JobOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompanyPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_company_page_shows_only_approved_reviews_and_published_offers(): void
    {
        $company = Company::factory()->create(['name' => 'Kamienica Studio']);

        $approved = CompanyReview::factory()->for($company)->create([
            'rating_return' => 4,
            'rating_flexibility' => 4,
            'rating_no_pregnancy_questions' => 4,
            'quote' => 'Spotkania są przed 15:00.',
        ]);
        CompanyReview::factory()->for($company)->create(['status' => ReviewStatus::Pending, 'quote' => 'Oczekująca']);
        CompanyReview::factory()->for($company)->create(['status' => ReviewStatus::Rejected, 'quote' => 'Odrzucona']);

        $published = JobOffer::factory()->published()->for($company)->create();
        JobOffer::factory()->for($company)->create(['status' => OfferStatus::Draft]);
        JobOffer::factory()->published()->for($company)->create(['status' => OfferStatus::Closed]);
        JobOffer::factory()->published()->create();

        $this->get(route('public.companies.show', $company))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/companies/Show')
                ->where('company.name', 'Kamienica Studio')
                ->where('company.rating.count', 1)
                ->where('company.rating.overall', 4)
                ->has('reviews', 1)
                ->where('reviews.0.id', $approved->id)
                ->where('reviews.0.quote', 'Spotkania są przed 15:00.')
                ->missing('reviews.0.user_id')
                ->has('offers', 1)
                ->where('offers.0.id', $published->id),
            );
    }

    public function test_company_page_without_reviews_has_empty_rating(): void
    {
        $company = Company::factory()->create();

        $this->get(route('public.companies.show', $company))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('company.rating.count', 0)
                ->where('company.rating.overall', null)
                ->has('reviews', 0)
                ->has('offers', 0),
            );
    }

    public function test_unknown_company_returns_not_found(): void
    {
        $this->get('/companies/999')->assertNotFound();
    }
}
