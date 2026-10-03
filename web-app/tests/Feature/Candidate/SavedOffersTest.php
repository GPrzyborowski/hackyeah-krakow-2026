<?php

namespace Tests\Feature\Candidate;

use App\Enums\OfferStatus;
use App\Models\CandidateProfile;
use App\Models\JobOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SavedOffersTest extends TestCase
{
    use RefreshDatabase;

    private CandidateProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profile = CandidateProfile::factory()->published()->create(['available_from' => '2027-09-01']);
    }

    public function test_candidate_saves_and_unsaves_an_offer(): void
    {
        $offer = $this->offer();

        $this->actingAs($this->profile->user)
            ->from(route('candidate.offers.index'))
            ->post(route('candidate.offers.save', $offer))
            ->assertRedirect(route('candidate.offers.index'));

        $this->assertDatabaseHas('saved_offers', ['candidate_profile_id' => $this->profile->id, 'job_offer_id' => $offer->id]);

        $this->actingAs($this->profile->user)->delete(route('candidate.offers.unsave', $offer))->assertRedirect();

        $this->assertDatabaseMissing('saved_offers', ['candidate_profile_id' => $this->profile->id, 'job_offer_id' => $offer->id]);
    }

    public function test_saving_twice_keeps_a_single_bookmark(): void
    {
        $offer = $this->offer();

        $this->actingAs($this->profile->user)->post(route('candidate.offers.save', $offer))->assertRedirect();
        $this->actingAs($this->profile->user)->post(route('candidate.offers.save', $offer))->assertRedirect();

        $this->assertDatabaseCount('saved_offers', 1);
    }

    public function test_unsaving_only_touches_the_current_candidates_bookmark(): void
    {
        $offer = $this->offer();
        $otherProfile = CandidateProfile::factory()->published()->create();
        $otherProfile->savedOffers()->attach($offer);
        $this->profile->savedOffers()->attach($offer);

        $this->actingAs($this->profile->user)->delete(route('candidate.offers.unsave', $offer))->assertRedirect();

        $this->assertDatabaseMissing('saved_offers', ['candidate_profile_id' => $this->profile->id]);
        $this->assertDatabaseHas('saved_offers', ['candidate_profile_id' => $otherProfile->id, 'job_offer_id' => $offer->id]);
    }

    public function test_employers_cannot_save_offers(): void
    {
        $offer = $this->offer();

        $this->actingAs(User::factory()->employer()->create())
            ->post(route('candidate.offers.save', $offer))
            ->assertForbidden();

        $this->assertDatabaseCount('saved_offers', 0);
    }

    public function test_unpublished_offers_cannot_be_saved(): void
    {
        $draft = $this->offer(published: false);

        $this->actingAs($this->profile->user)
            ->post(route('candidate.offers.save', $draft))
            ->assertNotFound();

        $this->assertDatabaseCount('saved_offers', 0);
    }

    public function test_listing_marks_saved_offers_and_filters_by_them(): void
    {
        $saved = $this->offer(['title' => 'Zapisana']);
        $this->offer(['title' => 'Inna']);
        $unpublishedSaved = $this->offer(['title' => 'Wycofana']);
        $this->profile->savedOffers()->attach([$saved->id, $unpublishedSaved->id]);
        $unpublishedSaved->update(['status' => OfferStatus::Closed]);

        $this->actingAs($this->profile->user)
            ->get(route('candidate.offers.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('offers', 2)
                ->where('filters.saved', false)
                ->where('offers', fn ($offers): bool => collect($offers)->firstWhere('id', $saved->id)['is_saved'] === true
                    && collect($offers)->where('is_saved', true)->count() === 1));

        $this->actingAs($this->profile->user)
            ->get(route('candidate.offers.index', ['saved' => 1]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.saved', true)
                ->has('offers', 1)
                ->where('offers.0.id', $saved->id)
                ->where('offers.0.is_saved', true));
    }

    public function test_offer_detail_and_home_reflect_saved_offers(): void
    {
        $offer = $this->offer();
        $unpublishedSaved = $this->offer();
        $this->profile->savedOffers()->attach([$offer->id, $unpublishedSaved->id]);
        $unpublishedSaved->update(['status' => OfferStatus::Closed]);

        $this->actingAs($this->profile->user)
            ->get(route('candidate.offers.show', $offer))
            ->assertInertia(fn (Assert $page) => $page->where('offer.is_saved', true));

        $this->actingAs($this->profile->user)
            ->get(route('candidate.home'))
            ->assertInertia(fn (Assert $page) => $page->where('savedOffersCount', 1));
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
