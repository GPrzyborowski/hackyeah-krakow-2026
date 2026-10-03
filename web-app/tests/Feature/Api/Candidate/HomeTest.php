<?php

namespace Tests\Feature\Api\Candidate;

use App\Models\CandidateProfile;
use App\Models\Invitation;
use App\Models\JobOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_returns_calendar_invitations_and_top_offers(): void
    {
        Date::setTestNow('2026-10-03');
        $user = User::factory()->create(['name' => 'Marta Kowalska']);
        $profile = CandidateProfile::factory()->published()->for($user)->create([
            'due_date' => '2027-01-02',
            'leave_starts_on' => '2026-12-01',
            'available_from' => '2027-09-01',
        ]);
        $invitation = Invitation::factory()->create(['candidate_profile_id' => $profile->id]);
        Invitation::factory()->accepted()->create(['candidate_profile_id' => $profile->id]);
        $offers = JobOffer::factory()->published()->count(4)->create();
        $profile->savedOffers()->attach($offers->first());
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/candidate/home')
            ->assertOk()
            ->assertJsonPath('data.greeting.first_name', 'Marta')
            ->assertJsonPath('data.profile.published', true)
            ->assertJsonPath('data.calendar.pregnancy_week', 27)
            ->assertJsonPath('data.calendar.current_phase', 'pregnancy')
            ->assertJsonPath('data.calendar.available_from', '2027-09-01')
            ->assertJsonPath('data.invitations.pending_count', 1)
            ->assertJsonPath('data.invitations.company_names', [$invitation->jobOffer->company->name])
            ->assertJsonPath('data.pair_invitations_count', 0)
            ->assertJsonPath('data.saved_offers_count', 1)
            ->assertJsonCount(3, 'data.top_offers')
            ->assertJsonStructure(['data' => ['top_offers' => [['id', 'title', 'match' => ['score'], 'company' => ['name'], 'is_saved']]]]);
    }

    public function test_unpublished_candidate_is_flagged_for_onboarding(): void
    {
        $profile = CandidateProfile::factory()->create(['onboarding_step' => 2]);
        Sanctum::actingAs($profile->user);

        $this->getJson('/api/v1/candidate/home')
            ->assertOk()
            ->assertJsonPath('data.profile.published', false)
            ->assertJsonPath('data.profile.onboarding_step', 2);
    }

    public function test_guests_get_401_and_employers_403(): void
    {
        $this->getJson('/api/v1/candidate/home')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->employer()->create());
        $this->getJson('/api/v1/candidate/home')->assertForbidden();
    }
}
