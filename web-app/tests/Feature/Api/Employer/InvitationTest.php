<?php

namespace Tests\Feature\Api\Employer;

use App\Enums\CandidateDecisionType;
use App\Enums\InvitationStatus;
use App\Models\CandidateDecision;
use App\Models\Invitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Employer\InteractsWithEmployerFixtures;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use InteractsWithEmployerFixtures, RefreshDatabase;

    private const string MESSAGE = 'Dzień dobry, zapraszamy na rozmowę o stanowisku. Widełki 8500–11000 zł, elastyczne godziny.';

    public function test_employer_invites_a_matched_candidate_anonymously(): void
    {
        $employer = $this->employer();
        $skill = $this->skill('Rekrutacja IT');
        $offer = $this->publishedOffer($employer->company, [$skill]);
        $candidate = $this->candidate([$skill]);
        Sanctum::actingAs($employer);

        $response = $this->postJson("/api/v1/employer/offers/{$offer->id}/candidates/{$candidate->id}/invitation", ['message' => self::MESSAGE])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.status_label', 'Czeka na odpowiedź')
            ->assertJsonPath('data.offer.id', $offer->id)
            ->assertJsonPath('data.candidate', ['id' => $candidate->id, 'anonymous_name' => 'Marta K.'])
            ->assertJsonPath('data.conversation_id', null);

        $this->assertStringNotContainsString($candidate->user->email, (string) $response->getContent());
        $invitation = Invitation::sole();
        $this->assertSame($employer->id, $invitation->sent_by_user_id);
        $this->assertSame(CandidateDecisionType::Invited, CandidateDecision::sole()->decision);
    }

    public function test_message_asking_about_pregnancy_is_blocked_with_a_suggestion(): void
    {
        $employer = $this->employer();
        $skill = $this->skill('Rekrutacja IT');
        $offer = $this->publishedOffer($employer->company, [$skill]);
        $candidate = $this->candidate([$skill]);
        Sanctum::actingAs($employer);

        $this->postJson("/api/v1/employer/offers/{$offer->id}/candidates/{$candidate->id}/invitation", ['message' => 'Czy planuje Pani dzieci?'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['message', 'message_suggestion']);

        $this->assertSame(0, Invitation::count());
        $this->assertSame(0, CandidateDecision::count());
    }

    public function test_candidate_cannot_be_invited_twice(): void
    {
        $employer = $this->employer();
        $skill = $this->skill('Rekrutacja IT');
        $offer = $this->publishedOffer($employer->company, [$skill]);
        $candidate = $this->candidate([$skill]);
        Invitation::factory()->for($offer)->for($candidate)->create();
        Sanctum::actingAs($employer);

        $this->postJson("/api/v1/employer/offers/{$offer->id}/candidates/{$candidate->id}/invitation", ['message' => self::MESSAGE])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['message' => 'Ta kandydatka ma już zaproszenie do tej oferty.']);

        $this->assertSame(1, Invitation::count());
    }

    public function test_employer_cannot_invite_for_another_company_offer_or_a_hidden_candidate(): void
    {
        $employer = $this->employer();
        $skill = $this->skill('Rekrutacja IT');
        $foreignOffer = $this->publishedOffer($this->employer()->company, [$skill]);
        $ownOffer = $this->publishedOffer($employer->company, [$skill]);
        $candidate = $this->candidate([$skill]);
        $hidden = $this->candidate([$skill], ['hidden_from_company_id' => $employer->company_id], 'Ewa Lis');
        Sanctum::actingAs($employer);

        $this->postJson("/api/v1/employer/offers/{$foreignOffer->id}/candidates/{$candidate->id}/invitation", ['message' => self::MESSAGE])->assertForbidden();
        $this->postJson("/api/v1/employer/offers/{$ownOffer->id}/candidates/{$hidden->id}/invitation", ['message' => self::MESSAGE])->assertNotFound();

        $this->assertSame(0, Invitation::count());
    }

    public function test_contact_data_is_revealed_only_after_acceptance(): void
    {
        $employer = $this->employer();
        $skill = $this->skill('Rekrutacja IT');
        $offer = $this->publishedOffer($employer->company, [$skill]);
        $pendingCandidate = $this->candidate([$skill], ['due_date' => '2027-01-15'], 'Anna Nowakowska');
        $acceptedCandidate = $this->candidate([$skill], name: 'Marta Kowalska');
        Invitation::factory()->for($offer)->for($pendingCandidate)->create(['status' => InvitationStatus::Pending, 'created_at' => now()->subDay()]);
        $accepted = Invitation::factory()->for($offer)->for($acceptedCandidate)->create(['status' => InvitationStatus::Pending]);
        $conversation = $accepted->accept();
        Invitation::factory()->accepted()->create();
        Sanctum::actingAs($employer);

        $response = $this->getJson('/api/v1/employer/invitations')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.candidate.full_name', 'Marta Kowalska')
            ->assertJsonPath('data.0.candidate.email', $acceptedCandidate->user->email)
            ->assertJsonPath('data.0.conversation_id', $conversation->id)
            ->assertJsonPath('data.1.candidate', ['id' => $pendingCandidate->id, 'anonymous_name' => 'Anna N.'])
            ->assertJsonPath('data.1.conversation_id', null)
            ->assertJsonPath('meta.total', 2);

        $payload = (string) $response->getContent();
        $this->assertStringNotContainsString('Nowakowska', $payload);
        $this->assertStringNotContainsString($pendingCandidate->user->email, $payload);
        $this->assertStringNotContainsString('2027-01-15', $payload);
    }

    public function test_index_filters_by_status(): void
    {
        $employer = $this->employer();
        $skill = $this->skill('Rekrutacja IT');
        $offer = $this->publishedOffer($employer->company, [$skill]);
        $declined = Invitation::factory()->for($offer)->for($this->candidate([$skill], name: 'Ewa Lis'))->create(['status' => InvitationStatus::Declined]);
        Invitation::factory()->for($offer)->for($this->candidate([$skill]))->create(['status' => InvitationStatus::Pending]);
        Sanctum::actingAs($employer);

        $this->getJson('/api/v1/employer/invitations?status=declined')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $declined->id);

        $this->getJson('/api/v1/employer/invitations?status=maybe')->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_unanswered_invitations_of_candidates_who_hid_their_profile_are_not_listed(): void
    {
        $employer = $this->employer();
        $skill = $this->skill('Rekrutacja IT');
        $offer = $this->publishedOffer($employer->company, [$skill]);
        $hiddenAttributes = ['hidden_from_company_id' => $employer->company_id];
        Invitation::factory()->for($offer)->for($this->candidate([$skill], $hiddenAttributes, 'Anna Nowak'))->create(['status' => InvitationStatus::Pending]);
        Invitation::factory()->for($offer)->for($this->candidate([$skill], $hiddenAttributes, 'Ewa Lis'))->create(['status' => InvitationStatus::Declined]);
        $accepted = Invitation::factory()->for($offer)->for($this->candidate([$skill], $hiddenAttributes, 'Marta Kowalska'))->create(['status' => InvitationStatus::Pending]);
        $accepted->accept();
        $visible = Invitation::factory()->for($offer)->for($this->candidate([$skill], name: 'Ola Mazur'))->create(['status' => InvitationStatus::Pending]);
        Sanctum::actingAs($employer);

        $ids = $this->getJson('/api/v1/employer/invitations')->assertOk()->json('data.*.id');

        $this->assertEqualsCanonicalizing([$accepted->id, $visible->id], $ids);
    }
}
