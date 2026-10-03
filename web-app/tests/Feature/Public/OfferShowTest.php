<?php

namespace Tests\Feature\Public;

use App\Enums\OfferStatus;
use App\Enums\ReviewStatus;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\CompanyReview;
use App\Models\JobOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Employer\InteractsWithEmployerFixtures;
use Tests\TestCase;

class OfferShowTest extends TestCase
{
    use InteractsWithEmployerFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guest_sees_a_published_offer_with_company_rating_and_reviews_but_no_match(): void
    {
        $company = Company::factory()->verified()->create(['name' => 'Zielone Biuro', 'description' => 'Biuro rachunkowe.']);
        CompanyReview::factory()->for($company)->create(['quote' => 'Wróciłam na 3/5 etatu.', 'rating_return' => 5, 'rating_flexibility' => 4, 'rating_no_pregnancy_questions' => 3]);
        CompanyReview::factory()->for($company)->create(['quote' => 'Ukryta opinia', 'status' => ReviewStatus::Pending]);
        $offer = $this->publishedOffer($company, [$this->skill('Excel')], [$this->skill('SAP')]);

        $this->get(route('public.offers.show', $offer))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/offers/Show')
                ->where('offer.id', $offer->id)
                ->where('offer.required_skills', ['Excel'])
                ->where('offer.nice_to_have_skills', ['SAP'])
                ->where('offer.company.verified', true)
                ->where('offer.company.rating.count', 1)
                ->where('offer.company.rating.overall', 4)
                ->where('offer.company_description', 'Biuro rachunkowe.')
                ->where('offer.job_share.is_job_share', false)
                ->has('reviews', 1)
                ->where('reviews.0.quote', 'Wróciłam na 3/5 etatu.')
                ->missing('offer.match'),
            );
    }

    public function test_job_share_offer_exposes_the_shared_workday(): void
    {
        $offer = JobOffer::factory()->jobShare()->published()->create();

        $this->get(route('public.offers.show', $offer))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('offer.job_share.is_job_share', true)
                ->where('offer.workday_starts_at', '08:00')
                ->where('offer.workday_ends_at', '16:00'),
            );
    }

    public function test_employers_and_candidates_can_open_the_public_offer(): void
    {
        $offer = JobOffer::factory()->published()->create();
        $candidate = CandidateProfile::factory()->published()->create()->user;

        $this->actingAs(User::factory()->employer()->create())
            ->get(route('public.offers.show', $offer))
            ->assertOk();

        $this->actingAs($candidate)
            ->get(route('public.offers.show', $offer))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('public/offers/Show')->missing('offer.match'));
    }

    public function test_draft_and_closed_offers_are_not_found(): void
    {
        $draft = JobOffer::factory()->create(['status' => OfferStatus::Draft]);
        $closed = JobOffer::factory()->published()->create(['status' => OfferStatus::Closed]);

        $this->get(route('public.offers.show', $draft))->assertNotFound();
        $this->get(route('public.offers.show', $closed))->assertNotFound();
        $this->get('/offers/999999')->assertNotFound();
    }
}
