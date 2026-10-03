<?php

namespace Tests\Feature\Api\Candidate;

use App\Models\CandidateProfile;
use App\Models\JobOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SavedOfferTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_saves_and_unsaves_an_offer(): void
    {
        $profile = CandidateProfile::factory()->published()->create();
        $offer = JobOffer::factory()->published()->create();
        Sanctum::actingAs($profile->user);

        $this->postJson("/api/v1/candidate/offers/{$offer->id}/save")->assertNoContent();
        $this->postJson("/api/v1/candidate/offers/{$offer->id}/save")->assertNoContent();
        $this->assertSame(1, $profile->savedOffers()->count());

        $this->deleteJson("/api/v1/candidate/offers/{$offer->id}/save")->assertNoContent();
        $this->assertSame(0, $profile->savedOffers()->count());
    }

    public function test_saving_unpublished_offer_returns_404(): void
    {
        $profile = CandidateProfile::factory()->published()->create();
        $draft = JobOffer::factory()->create(['status' => 'draft']);
        Sanctum::actingAs($profile->user);

        $this->postJson("/api/v1/candidate/offers/{$draft->id}/save")->assertNotFound();
    }
}
