<?php

namespace Tests\Feature\Candidate;

use App\Enums\InvitationStatus;
use App\Models\CandidateProfile;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvitationsTest extends TestCase
{
    use RefreshDatabase;

    private CandidateProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profile = CandidateProfile::factory()->published()->create();
    }

    public function test_candidate_sees_only_her_invitations(): void
    {
        $own = Invitation::factory()->create(['candidate_profile_id' => $this->profile->id]);
        Invitation::factory()->create();

        $this->actingAs($this->profile->user)
            ->get(route('candidate.invitations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('candidate/Invitations')
                ->has('invitations', 1)
                ->where('invitations.0.id', $own->id)
                ->where('invitations.0.status', 'pending'));
    }

    public function test_accepting_opens_a_conversation_and_redirects_to_it(): void
    {
        $invitation = Invitation::factory()->create(['candidate_profile_id' => $this->profile->id]);

        $response = $this->actingAs($this->profile->user)
            ->post(route('candidate.invitations.accept', $invitation));

        $invitation->refresh();
        $this->assertSame(InvitationStatus::Accepted, $invitation->status);
        $this->assertNotNull($invitation->responded_at);
        $this->assertNotNull($invitation->conversation);
        $response->assertRedirect('/conversations/'.$invitation->conversation->id);
    }

    public function test_declining_marks_invitation_declined_without_conversation(): void
    {
        $invitation = Invitation::factory()->create(['candidate_profile_id' => $this->profile->id]);

        $this->actingAs($this->profile->user)
            ->post(route('candidate.invitations.decline', $invitation))
            ->assertRedirect(route('candidate.invitations.index'));

        $invitation->refresh();
        $this->assertSame(InvitationStatus::Declined, $invitation->status);
        $this->assertNull($invitation->conversation);
    }

    public function test_other_candidate_cannot_answer_the_invitation(): void
    {
        $invitation = Invitation::factory()->create(['candidate_profile_id' => $this->profile->id]);
        $otherCandidate = CandidateProfile::factory()->published()->create()->user;

        $this->actingAs($otherCandidate)->post(route('candidate.invitations.accept', $invitation))->assertForbidden();
        $this->actingAs($otherCandidate)->post(route('candidate.invitations.decline', $invitation))->assertForbidden();

        $this->assertSame(InvitationStatus::Pending, $invitation->refresh()->status);
        $this->assertNull($invitation->conversation);
    }

    public function test_employer_cannot_accept_on_behalf_of_candidate(): void
    {
        $invitation = Invitation::factory()->create(['candidate_profile_id' => $this->profile->id]);

        $this->actingAs(User::factory()->employer()->create())
            ->post(route('candidate.invitations.accept', $invitation))
            ->assertForbidden();

        $this->assertSame(InvitationStatus::Pending, $invitation->refresh()->status);
    }

    public function test_non_pending_invitation_cannot_be_answered(): void
    {
        $declined = Invitation::factory()->create([
            'candidate_profile_id' => $this->profile->id,
            'status' => InvitationStatus::Declined,
        ]);
        $withdrawn = Invitation::factory()->create([
            'candidate_profile_id' => $this->profile->id,
            'status' => InvitationStatus::Withdrawn,
        ]);

        $this->actingAs($this->profile->user)->post(route('candidate.invitations.accept', $declined))->assertForbidden();
        $this->actingAs($this->profile->user)->post(route('candidate.invitations.accept', $withdrawn))->assertForbidden();
        $this->actingAs($this->profile->user)->post(route('candidate.invitations.decline', $withdrawn))->assertForbidden();

        $this->assertSame(InvitationStatus::Declined, $declined->refresh()->status);
        $this->assertNull($declined->conversation);
        $this->assertSame(InvitationStatus::Withdrawn, $withdrawn->refresh()->status);
    }
}
