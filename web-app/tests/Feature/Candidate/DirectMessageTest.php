<?php

namespace Tests\Feature\Candidate;

use App\Enums\InvitationStatus;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\JobOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DirectMessageTest extends TestCase
{
    use RefreshDatabase;

    private CandidateProfile $profile;

    private User $employer;

    private Invitation $question;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $company = Company::factory()->create();
        $this->employer = User::factory()->employer($company)->create();
        $this->profile = CandidateProfile::factory()->published()->create(['allow_direct_messages' => true]);
        $this->question = Invitation::factory()->directMessage()
            ->for(JobOffer::factory()->published()->for($company))
            ->for($this->profile)
            ->create(['sent_by_user_id' => $this->employer->id]);
    }

    public function test_candidate_sees_the_direct_question_labelled_in_her_invitations(): void
    {
        $this->actingAs($this->profile->user)
            ->get(route('candidate.invitations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invitations.0.id', $this->question->id)
                ->where('invitations.0.kind', 'direct_message')
                ->where('invitations.0.kind_label', 'Pytanie od firmy')
                ->where('invitations.0.message', $this->question->message));
    }

    public function test_answering_opens_a_conversation_and_reveals_the_candidate_to_the_company(): void
    {
        $this->actingAs($this->employer)
            ->get(route('employer.invitations.index'))
            ->assertDontSee($this->profile->user->email);

        $this->actingAs($this->profile->user)
            ->post(route('candidate.invitations.accept', $this->question))
            ->assertRedirect();

        $this->question->refresh();
        $this->assertSame(InvitationStatus::Accepted, $this->question->status);
        $this->assertSame($this->question->message, $this->question->conversation->messages()->sole()->body);

        $this->actingAs($this->employer)
            ->get(route('employer.invitations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invitations.0.candidate.email', $this->profile->user->email));
    }

    public function test_ignoring_declines_the_question_and_keeps_the_candidate_anonymous(): void
    {
        $this->actingAs($this->profile->user)
            ->post(route('candidate.invitations.decline', $this->question))
            ->assertRedirect(route('candidate.invitations.index'));

        $this->question->refresh();
        $this->assertSame(InvitationStatus::Declined, $this->question->status);
        $this->assertNull($this->question->conversation);

        $this->actingAs($this->employer)
            ->get(route('employer.invitations.index'))
            ->assertDontSee($this->profile->user->email);
    }

    public function test_onboarding_shows_the_direct_messages_privacy_toggle_state(): void
    {
        $this->actingAs($this->profile->user)
            ->get(route('candidate.onboarding.show', ['step' => 4]))
            ->assertInertia(fn (Assert $page) => $page->where('profile.allow_direct_messages', true));
    }
}
