<?php

namespace Tests\Feature\Candidate;

use App\Models\CandidateProfile;
use App\Models\Invitation;
use App\Models\JobOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HomeAndAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function candidatePages(): array
    {
        return [
            'home' => ['/candidate'],
            'onboarding' => ['/candidate/onboarding'],
            'offers' => ['/candidate/offers'],
            'invitations' => ['/candidate/invitations'],
        ];
    }

    #[DataProvider('candidatePages')]
    public function test_guests_are_redirected_to_login(string $uri): void
    {
        $this->get($uri)->assertRedirect(route('login'));
    }

    #[DataProvider('candidatePages')]
    public function test_employers_are_forbidden(string $uri): void
    {
        $this->actingAs(User::factory()->employer()->create())->get($uri)->assertForbidden();
    }

    public function test_unpublished_candidate_is_sent_to_onboarding(): void
    {
        $profile = CandidateProfile::factory()->create();

        $this->actingAs($profile->user)
            ->get(route('candidate.home'))
            ->assertRedirect(route('candidate.onboarding.show'));
    }

    public function test_home_shows_calendar_invitations_and_top_offers(): void
    {
        Date::setTestNow('2026-10-03');

        $user = User::factory()->create(['name' => 'Marta Kowalska']);
        $profile = CandidateProfile::factory()->published()->pregnant()->for($user)->create([
            'due_date' => '2027-01-02',
            'leave_starts_on' => '2026-12-01',
            'available_from' => '2027-09-01',
        ]);
        $invitation = Invitation::factory()->create(['candidate_profile_id' => $profile->id]);
        Invitation::factory()->accepted()->create(['candidate_profile_id' => $profile->id]);
        JobOffer::factory()->published()->count(4)->create();

        $this->actingAs($user)
            ->get(route('candidate.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('candidate/Home')
                ->where('firstName', 'Marta')
                ->where('calendar.pregnancy_week', 27)
                ->where('calendar.current_phase', 'pregnancy')
                ->where('calendar.available_from', '2027-09-01')
                ->where('invitations.pending_count', 1)
                ->where('invitations.company_names', [$invitation->jobOffer->company->name])
                ->has('topOffers', 3));
    }

    public function test_home_renders_without_private_dates(): void
    {
        $profile = CandidateProfile::factory()->published()->create(['due_date' => null, 'leave_starts_on' => null]);

        $this->actingAs($profile->user)
            ->get(route('candidate.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('calendar.pregnancy_week', null)
                ->where('calendar.leave_starts_on', null));
    }
}
