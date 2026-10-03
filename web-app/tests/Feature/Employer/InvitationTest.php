<?php

namespace Tests\Feature\Employer;

use App\Enums\CandidateDecisionType;
use App\Enums\InvitationStatus;
use App\Models\CandidateDecision;
use App\Models\Invitation;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use InteractsWithEmployerFixtures, RefreshDatabase;

    public function test_employer_invites_a_candidate()
    {
        $employer = $this->employer();
        $skill = Skill::factory()->create();
        $offer = $this->publishedOffer($employer->company, [$skill]);
        $candidate = $this->candidate([$skill]);

        $this->actingAs($employer)
            ->post("/employer/offers/{$offer->id}/candidates/{$candidate->id}/invitation", [
                'message' => 'Dzień dobry, zapraszamy na rozmowę o stanowisku. Widełki 8500–11000 zł, elastyczne godziny.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('employer.candidates.index', ['offer' => $offer->id]));

        $invitation = Invitation::sole();
        $this->assertSame($employer->id, $invitation->sent_by_user_id);
        $this->assertSame($candidate->id, $invitation->candidate_profile_id);
        $this->assertSame(InvitationStatus::Pending, $invitation->status);
        $this->assertSame(CandidateDecisionType::Invited, CandidateDecision::sole()->decision);
    }

    public function test_message_asking_about_pregnancy_plans_is_blocked_with_a_suggestion()
    {
        $employer = $this->employer();
        $skill = Skill::factory()->create();
        $offer = $this->publishedOffer($employer->company, [$skill]);
        $candidate = $this->candidate([$skill]);

        $this->actingAs($employer)
            ->post("/employer/offers/{$offer->id}/candidates/{$candidate->id}/invitation", ['message' => 'Czy planuje Pani dzieci?'])
            ->assertSessionHasErrors(['message', 'message_suggestion']);

        $this->assertSame(0, Invitation::count());
        $this->assertSame(0, CandidateDecision::count());
    }

    public function test_candidate_cannot_be_invited_twice_to_the_same_offer()
    {
        $employer = $this->employer();
        $skill = Skill::factory()->create();
        $offer = $this->publishedOffer($employer->company, [$skill]);
        $candidate = $this->candidate([$skill]);
        Invitation::factory()->for($offer)->for($candidate)->create();

        $this->actingAs($employer)
            ->post("/employer/offers/{$offer->id}/candidates/{$candidate->id}/invitation", ['message' => 'Zapraszamy na rozmowę.'])
            ->assertSessionHasErrors('message');

        $this->assertSame(1, Invitation::count());
    }

    public function test_employer_cannot_invite_for_another_company_offer()
    {
        $skill = Skill::factory()->create();
        $foreignOffer = $this->publishedOffer($this->employer()->company, [$skill]);
        $candidate = $this->candidate([$skill]);

        $this->actingAs($this->employer())
            ->post("/employer/offers/{$foreignOffer->id}/candidates/{$candidate->id}/invitation", ['message' => 'Zapraszamy na rozmowę.'])
            ->assertForbidden();

        $this->assertSame(0, Invitation::count());
    }

    public function test_contact_data_is_revealed_only_after_acceptance()
    {
        $employer = $this->employer();
        $skill = Skill::factory()->create();
        $offer = $this->publishedOffer($employer->company, [$skill]);
        $pendingCandidate = $this->candidate([$skill], name: 'Anna Nowakowska');
        $acceptedCandidate = $this->candidate([$skill], name: 'Marta Kowalska');
        Invitation::factory()->for($offer)->for($pendingCandidate)->create(['status' => InvitationStatus::Pending, 'created_at' => now()->subDay()]);
        $accepted = Invitation::factory()->for($offer)->for($acceptedCandidate)->create(['status' => InvitationStatus::Pending]);
        $conversation = $accepted->accept();
        Invitation::factory()->accepted()->create();

        $response = $this->actingAs($employer)->get('/employer/invitations');

        $response->assertInertia(fn (Assert $page) => $page
            ->component('employer/invitations/Index')
            ->has('invitations', 2)
            ->where('invitations.0.candidate.full_name', 'Marta Kowalska')
            ->where('invitations.0.candidate.email', $acceptedCandidate->user->email)
            ->where('invitations.0.conversation_url', route('conversations.show', $conversation))
            ->where('invitations.1.candidate.anonymous_name', 'Anna N.')
            ->missing('invitations.1.candidate.email')
            ->where('invitations.1.conversation_url', null));

        $payload = json_encode($response->viewData('page')['props']['invitations']);
        $this->assertStringNotContainsString('Nowakowska', $payload);
        $this->assertStringNotContainsString($pendingCandidate->user->email, $payload);
    }
}
