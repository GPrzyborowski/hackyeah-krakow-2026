<?php

namespace Tests\Feature\Api\Candidate;

use App\Models\CandidateProfile;
use App\Models\JobOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OfferInterestTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_shows_and_withdraws_interest(): void
    {
        $profile = CandidateProfile::factory()->published()->create();
        $offer = JobOffer::factory()->published()->create();
        Sanctum::actingAs($profile->user);

        $this->postJson("/api/v1/candidate/offers/{$offer->id}/interest")->assertNoContent();
        $this->postJson("/api/v1/candidate/offers/{$offer->id}/interest")->assertNoContent();
        $this->assertSame(1, $profile->interests()->count());

        $this->deleteJson("/api/v1/candidate/offers/{$offer->id}/interest")->assertNoContent();
        $this->assertSame(0, $profile->interests()->count());
    }

    public function test_interest_in_unpublished_offer_returns_404(): void
    {
        $profile = CandidateProfile::factory()->published()->create();
        $draft = JobOffer::factory()->create(['status' => 'draft']);
        Sanctum::actingAs($profile->user);

        $this->postJson("/api/v1/candidate/offers/{$draft->id}/interest")->assertNotFound();
        $this->assertSame(0, $profile->interests()->count());
    }
}
