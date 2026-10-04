<?php

namespace Tests\Feature\Public;

use App\Enums\EmploymentFraction;
use App\Enums\OfferCategory;
use App\Enums\OfferStatus;
use App\Enums\ReviewStatus;
use App\Enums\WorkMode;
use App\Models\Company;
use App\Models\CompanyReview;
use App\Models\JobOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OfferIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guests_see_only_published_offers(): void
    {
        $published = JobOffer::factory()->published()->create(['title' => 'Specjalistka ds. rekrutacji']);
        JobOffer::factory()->create(['status' => OfferStatus::Draft]);
        JobOffer::factory()->published()->create(['status' => OfferStatus::Closed]);

        $this->get(route('public.offers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/offers/Index')
                ->has('offers.data', 1)
                ->where('offers.data.0.id', $published->id)
                ->where('offers.data.0.title', 'Specjalistka ds. rekrutacji')
                ->missing('offers.data.0.match')
                ->has('offers.data.0.company.name'),
            );
    }

    public function test_signed_in_users_can_browse_public_offers(): void
    {
        JobOffer::factory()->published()->create();

        $this->actingAs(User::factory()->employer()->create())
            ->get(route('public.offers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('offers.data', 1));
    }

    public function test_offers_can_be_filtered_by_work_mode_and_fraction(): void
    {
        $match = JobOffer::factory()->published()->create([
            'work_mode' => WorkMode::Remote,
            'employment_fraction' => EmploymentFraction::Half,
        ]);
        JobOffer::factory()->published()->create([
            'work_mode' => WorkMode::Remote,
            'employment_fraction' => EmploymentFraction::Full,
        ]);
        JobOffer::factory()->published()->create([
            'work_mode' => WorkMode::Onsite,
            'employment_fraction' => EmploymentFraction::Half,
        ]);

        $this->get(route('public.offers.index', [
            'work_mode' => [WorkMode::Remote->value, 'invalid'],
            'fraction' => [EmploymentFraction::Half->value],
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('offers.data', 1)
                ->where('offers.data.0.id', $match->id)
                ->where('filters.work_mode', [WorkMode::Remote->value])
                ->where('filters.fraction', [EmploymentFraction::Half->value]),
            );
    }

    public function test_offers_can_be_filtered_by_category(): void
    {
        $itOffer = JobOffer::factory()->published()->create(['category' => OfferCategory::It, 'published_at' => now()->subDay()]);
        $healthOffer = JobOffer::factory()->published()->create(['category' => OfferCategory::Health, 'published_at' => now()]);
        JobOffer::factory()->published()->create(['category' => OfferCategory::Finance]);

        $this->get(route('public.offers.index', ['category' => [OfferCategory::It->value, OfferCategory::Health->value, 'invalid']]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('offers.data', 2)
                ->where('offers.data.0.id', $healthOffer->id)
                ->where('offers.data.0.category', 'health')
                ->where('offers.data.0.category_label', 'Medycyna i zdrowie')
                ->where('offers.data.1.id', $itOffer->id)
                ->where('filters.category', [OfferCategory::It->value, OfferCategory::Health->value])
                ->has('categories', count(OfferCategory::cases())),
            );
    }

    public function test_offers_can_be_filtered_by_flexible_hours(): void
    {
        $flexible = JobOffer::factory()->published()->create(['flexible_hours' => true]);
        JobOffer::factory()->published()->create(['flexible_hours' => false]);

        $this->get(route('public.offers.index', ['flexible' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('offers.data', 1)
                ->where('offers.data.0.id', $flexible->id),
            );
    }

    public function test_offers_can_be_filtered_by_nursery_nearby(): void
    {
        $nearby = JobOffer::factory()->published()->create(['nursery_distance_km' => 1]);
        JobOffer::factory()->published()->create(['nursery_distance_km' => 5]);
        JobOffer::factory()->published()->create(['nursery_distance_km' => null]);

        $this->get(route('public.offers.index', ['nursery_nearby' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('offers.data', 1)
                ->where('offers.data.0.id', $nearby->id)
                ->where('offers.data.0.nursery_distance_km', 1)
                ->where('filters.nursery_nearby', true),
            );
    }

    public function test_offers_can_be_searched_by_title_and_company_name(): void
    {
        $byTitle = JobOffer::factory()->published()->create(['title' => 'Księgowa']);
        $byCompany = JobOffer::factory()->published()
            ->for(Company::factory()->state(['name' => 'Biuro Rachunkowe Warta']))
            ->create(['title' => 'Asystentka']);
        JobOffer::factory()->published()->create(['title' => 'Programistka']);

        $this->get(route('public.offers.index', ['q' => 'Księgowa']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('offers.data', 1)
                ->where('offers.data.0.id', $byTitle->id),
            );

        $this->get(route('public.offers.index', ['q' => 'Warta']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('offers.data', 1)
                ->where('offers.data.0.id', $byCompany->id),
            );
    }

    public function test_location_search_matches_city_or_remote_offers(): void
    {
        $inPoznan = JobOffer::factory()->published()->create(['city' => 'Poznań', 'work_mode' => WorkMode::Onsite]);
        $remote = JobOffer::factory()->published()->create(['city' => 'Gdańsk', 'work_mode' => WorkMode::Remote]);
        JobOffer::factory()->published()->create(['city' => 'Kraków', 'work_mode' => WorkMode::Hybrid]);

        $this->get(route('public.offers.index', ['location' => 'Poznań']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('offers.data', 1)
                ->where('offers.data.0.id', $inPoznan->id),
            );

        $this->get(route('public.offers.index', ['location' => 'zdalnie']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('offers.data', 1)
                ->where('offers.data.0.id', $remote->id),
            );
    }

    public function test_offers_can_be_filtered_by_companies_with_reviews_and_childcare_subsidy(): void
    {
        $reviewed = Company::factory()->create();
        CompanyReview::factory()->for($reviewed)->create();
        $pendingOnly = Company::factory()->create();
        CompanyReview::factory()->for($pendingOnly)->create(['status' => ReviewStatus::Pending]);
        $match = JobOffer::factory()->published()->for($reviewed)->create(['childcare_subsidy' => true]);
        JobOffer::factory()->published()->for($reviewed)->create(['childcare_subsidy' => false]);
        JobOffer::factory()->published()->for($pendingOnly)->create(['childcare_subsidy' => true]);

        $this->get(route('public.offers.index', ['with_reviews' => 1, 'childcare_subsidy' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('offers.data', 1)
                ->where('offers.data.0.id', $match->id)
                ->where('filters.with_reviews', true)
                ->where('filters.childcare_subsidy', true),
            );
    }

    public function test_start_from_keeps_offers_the_candidate_can_still_make_within_the_tolerance(): void
    {
        $withinTolerance = JobOffer::factory()->published()->create(['start_date' => '2027-08-10']);
        $later = JobOffer::factory()->published()->create(['start_date' => '2027-10-01']);
        JobOffer::factory()->published()->create(['start_date' => '2027-07-01']);

        $this->get(route('public.offers.index', ['start_from' => '2027-09-01', 'sort' => 'start_date']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('offers.data', 2)
                ->where('offers.data.0.id', $withinTolerance->id)
                ->where('offers.data.1.id', $later->id)
                ->where('filters.start_from', '2027-09-01')
                ->where('filters.sort', 'start_date'),
            );
    }

    public function test_invalid_start_from_and_sort_are_ignored(): void
    {
        JobOffer::factory()->published()->create();

        $this->get(route('public.offers.index', ['start_from' => '2027-02-31', 'sort' => 'match']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('offers.data', 1)
                ->where('filters.start_from', null)
                ->where('filters.sort', 'newest'),
            );
    }

    public function test_offers_can_be_sorted_by_company_rating_with_unrated_companies_last(): void
    {
        $unrated = JobOffer::factory()->published()->create(['published_at' => now()]);
        $good = Company::factory()->create();
        CompanyReview::factory()->for($good)->create(['rating_return' => 4, 'rating_flexibility' => 4, 'rating_no_pregnancy_questions' => 4]);
        $best = Company::factory()->create();
        CompanyReview::factory()->for($best)->create(['rating_return' => 5, 'rating_flexibility' => 5, 'rating_no_pregnancy_questions' => 5]);
        CompanyReview::factory()->for($best)->create(['rating_return' => 1, 'rating_flexibility' => 1, 'rating_no_pregnancy_questions' => 1, 'status' => ReviewStatus::Rejected]);
        $goodOffer = JobOffer::factory()->published()->for($good)->create(['published_at' => now()->subDays(2)]);
        $bestOffer = JobOffer::factory()->published()->for($best)->create(['published_at' => now()->subDays(5)]);

        $this->get(route('public.offers.index', ['sort' => 'rating']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('offers.data.0.id', $bestOffer->id)
                ->where('offers.data.1.id', $goodOffer->id)
                ->where('offers.data.2.id', $unrated->id),
            );

        $this->get(route('public.offers.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('offers.data.0.id', $unrated->id)
                ->where('filters.sort', 'newest'),
            );
    }
}
