<?php

namespace Tests\Feature\Candidate;

use App\Enums\EmploymentFraction;
use App\Enums\OfferCategory;
use App\Enums\SkillImportance;
use App\Enums\WorkMode;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\CompanyReview;
use App\Models\JobOffer;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OffersTest extends TestCase
{
    use RefreshDatabase;

    private CandidateProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profile = CandidateProfile::factory()->published()->create(['available_from' => '2027-09-01']);
    }

    public function test_listing_shows_only_published_offers_ranked_by_match(): void
    {
        $skill = Skill::factory()->create(['name' => 'Rekrutacja IT']);
        $this->profile->skills()->attach($skill, ['source' => 'manual', 'confirmed_at' => now()]);

        $weakMatch = $this->offer(['title' => 'Księgowa']);
        $weakMatch->skills()->attach(Skill::factory()->create(), ['importance' => SkillImportance::Required->value]);
        $strongMatch = $this->offer(['title' => 'Specjalistka ds. rekrutacji']);
        $strongMatch->skills()->attach($skill, ['importance' => SkillImportance::Required->value]);
        $this->offer(['title' => 'Szkic'], published: false);

        $this->actingAs($this->profile->user)
            ->get(route('candidate.offers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('candidate/offers/Index')
                ->has('offers', 2)
                ->where('offers.0.id', $strongMatch->id)
                ->where('offers.1.id', $weakMatch->id)
                ->where('filters.start_from', '2027-09-01'));
    }

    public function test_filters_narrow_the_listing(): void
    {
        $remoteFlexible = $this->offer(['work_mode' => WorkMode::Remote, 'flexible_hours' => true, 'employment_fraction' => EmploymentFraction::Half]);
        $this->offer(['work_mode' => WorkMode::Onsite, 'flexible_hours' => false, 'employment_fraction' => EmploymentFraction::Full]);

        $this->actingAs($this->profile->user)
            ->get(route('candidate.offers.index', ['work_modes' => ['remote'], 'flexible_hours' => 1, 'employment_fractions' => ['1/2']]))
            ->assertInertia(fn (Assert $page) => $page->has('offers', 1)->where('offers.0.id', $remoteFlexible->id));

        $this->actingAs($this->profile->user)
            ->get(route('candidate.offers.index', ['location' => 'zdalnie']))
            ->assertInertia(fn (Assert $page) => $page->has('offers', 1)->where('offers.0.id', $remoteFlexible->id));
    }

    public function test_category_filter_keeps_only_offers_from_the_chosen_industries(): void
    {
        $itOffer = $this->offer(['category' => OfferCategory::It]);
        $this->offer(['category' => OfferCategory::Sales]);

        $this->actingAs($this->profile->user)
            ->get(route('candidate.offers.index', ['categories' => ['it']]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('offers', 1)
                ->where('offers.0.id', $itOffer->id)
                ->where('offers.0.category_label', 'IT i technologie')
                ->where('filters.categories', ['it'])
                ->has('categories', count(OfferCategory::cases())));

        $this->actingAs($this->profile->user)
            ->get(route('candidate.offers.index', ['categories' => ['astronomy']]))
            ->assertSessionHasErrors('categories.0');
    }

    public function test_listing_exposes_the_parent_friendly_conditions_shown_as_card_chips(): void
    {
        $offer = $this->offer(['fixed_meeting_hours' => true, 'childcare_subsidy' => true, 'flexible_hours' => false]);

        $this->actingAs($this->profile->user)
            ->get(route('candidate.offers.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('offers.0.id', $offer->id)
                ->where('offers.0.fixed_meeting_hours', true)
                ->where('offers.0.childcare_subsidy', true)
                ->where('offers.0.flexible_hours', false));
    }

    public function test_nursery_nearby_filter_keeps_offers_with_a_nursery_within_three_km(): void
    {
        $nearby = $this->offer(['nursery_distance_km' => 2]);
        $this->offer(['nursery_distance_km' => 6]);
        $this->offer(['nursery_distance_km' => null]);

        $this->actingAs($this->profile->user)
            ->get(route('candidate.offers.index', ['nursery_nearby' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('offers', 1)
                ->where('offers.0.id', $nearby->id)
                ->where('offers.0.nursery_distance_km', 2)
                ->where('filters.nursery_nearby', true));

        $this->actingAs($this->profile->user)
            ->get(route('candidate.offers.index'))
            ->assertInertia(fn (Assert $page) => $page->has('offers', 3));
    }

    public function test_text_search_matches_title_and_skills(): void
    {
        $this->offer(['title' => 'Koordynatorka projektów']);
        $bySkill = $this->offer(['title' => 'Asystentka']);
        $bySkill->skills()->attach(Skill::factory()->create(['name' => 'Projekty UE']), ['importance' => SkillImportance::Required->value]);
        $this->offer(['title' => 'Księgowa']);

        $this->actingAs($this->profile->user)
            ->get(route('candidate.offers.index', ['q' => 'projekt']))
            ->assertInertia(fn (Assert $page) => $page->has('offers', 2));
    }

    public function test_start_date_filter_is_consistent_with_match_scorer_tolerance(): void
    {
        $this->offer(['start_date' => '2027-07-01']);
        $withinTolerance = $this->offer(['start_date' => '2027-08-10']);

        $this->actingAs($this->profile->user)
            ->get(route('candidate.offers.index'))
            ->assertInertia(fn (Assert $page) => $page->has('offers', 1)->where('offers.0.id', $withinTolerance->id));

        $this->actingAs($this->profile->user)
            ->get(route('candidate.offers.index', ['start_from' => '']))
            ->assertInertia(fn (Assert $page) => $page->has('offers', 2));
    }

    public function test_reviews_filter_and_parent_friendly_badge(): void
    {
        $company = Company::factory()->create();
        CompanyReview::factory()->create(['company_id' => $company->id, 'quote' => 'Miesiąc na wdrożenie.']);
        $friendly = $this->offer(['company_id' => $company->id, 'flexible_hours' => true, 'salary_min' => 8500, 'salary_max' => 11000]);
        $this->offer();

        $this->actingAs($this->profile->user)
            ->get(route('candidate.offers.index', ['with_reviews' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('offers', 1)
                ->where('offers.0.id', $friendly->id)
                ->where('offers.0.is_parent_friendly', true)
                ->where('offers.0.company.first_review.quote', 'Miesiąc na wdrożenie.'));
    }

    public function test_offer_detail_shows_published_offer_with_match_breakdown(): void
    {
        $offer = $this->offer();
        $offer->skills()->attach(Skill::factory()->create(['name' => 'Excel']), ['importance' => SkillImportance::Required->value]);

        $this->actingAs($this->profile->user)
            ->get(route('candidate.offers.show', $offer))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('candidate/offers/Show')
                ->where('offer.match.missing_required', ['Excel'])
                ->where('offer.match.start_date_compatible', true));
    }

    public function test_draft_offer_detail_is_not_found(): void
    {
        $this->actingAs($this->profile->user)
            ->get(route('candidate.offers.show', $this->offer(published: false)))
            ->assertNotFound();
    }

    public function test_candidate_can_toggle_interest(): void
    {
        $offer = $this->offer();

        $this->actingAs($this->profile->user)->post(route('candidate.offers.interest.store', $offer))->assertRedirect();
        $this->actingAs($this->profile->user)->post(route('candidate.offers.interest.store', $offer));
        $this->assertSame(1, $this->profile->interests()->count());

        $this->actingAs($this->profile->user)
            ->get(route('candidate.offers.index'))
            ->assertInertia(fn (Assert $page) => $page->where('offers.0.is_interested', true));

        $this->actingAs($this->profile->user)->delete(route('candidate.offers.interest.destroy', $offer))->assertRedirect();
        $this->assertSame(0, $this->profile->interests()->count());
    }

    public function test_interest_in_draft_offer_is_rejected(): void
    {
        $this->actingAs($this->profile->user)
            ->post(route('candidate.offers.interest.store', $this->offer(published: false)))
            ->assertNotFound();

        $this->assertSame(0, $this->profile->interests()->count());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function offer(array $attributes = [], bool $published = true): JobOffer
    {
        $factory = JobOffer::factory()->state(['start_date' => '2027-09-01', ...$attributes]);

        return ($published ? $factory->published() : $factory)->create();
    }
}
