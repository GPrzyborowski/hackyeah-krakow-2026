<?php

namespace Tests\Feature\Public;

use App\Enums\EmploymentFraction;
use App\Enums\OfferStatus;
use App\Enums\WorkMode;
use App\Models\Company;
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
}
