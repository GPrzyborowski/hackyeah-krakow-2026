<?php

namespace Tests\Feature\Api\Candidate;

use App\Enums\InvitationStatus;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\CompanyReview;
use App\Models\Invitation;
use App\Models\JobOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_lists_her_invitations_pending_first(): void
    {
        $profile = CandidateProfile::factory()->published()->create();
        $company = Company::factory()->create(['name' => 'Zielone Biuro']);
        CompanyReview::factory()->for($company)->create(['rating_return' => 5, 'rating_flexibility' => 5, 'rating_no_pregnancy_questions' => 5]);
        $accepted = Invitation::factory()->accepted()->create(['candidate_profile_id' => $profile->id]);
        $pending = Invitation::factory()->create([
            'candidate_profile_id' => $profile->id,
            'job_offer_id' => JobOffer::factory()->published()->for($company)->create()->id,
            'created_at' => now()->subDay(),
        ]);
        Invitation::factory()->create();
        Sanctum::actingAs($profile->user);

        $this->getJson('/api/v1/candidate/invitations')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $pending->id)
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.message', $pending->message)
            ->assertJsonPath('data.0.company.name', 'Zielone Biuro')
            ->assertJsonPath('data.0.company.average_rating', 5)
            ->assertJsonPath('data.0.offer.is_published', true)
            ->assertJsonPath('data.0.job_share_pair', null)
            ->assertJsonPath('data.1.id', $accepted->id)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_unverified_candidate_cannot_accept_yet(): void
    {
        $invitation = Invitation::factory()->create();
        $invitation->candidateProfile->user->forceFill(['email_verified_at' => null])->save();
        Sanctum::actingAs($invitation->candidateProfile->user);

        $this->postJson("/api/v1/candidate/invitations/{$invitation->id}/accept")
            ->assertForbidden()
            ->assertJsonPath('email_verification_required', true);

        $this->assertSame(InvitationStatus::Pending, $invitation->refresh()->status);
    }

    public function test_accepting_opens_a_conversation(): void
    {
        $invitation = Invitation::factory()->create();
        Sanctum::actingAs($invitation->candidateProfile->user);

        $response = $this->postJson("/api/v1/candidate/invitations/{$invitation->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $invitation->refresh();
        $this->assertSame(InvitationStatus::Accepted, $invitation->status);
        $this->assertNotNull($invitation->conversation);
        $this->assertSame($invitation->conversation->id, $response->json('data.conversation_id'));
    }

    public function test_declining_keeps_the_candidate_anonymous(): void
    {
        $invitation = Invitation::factory()->create();
        Sanctum::actingAs($invitation->candidateProfile->user);

        $this->postJson("/api/v1/candidate/invitations/{$invitation->id}/decline")
            ->assertOk()
            ->assertJsonPath('data.status', 'declined')
            ->assertJsonPath('data.conversation_id', null);

        $this->assertNull($invitation->refresh()->conversation);
    }

    public function test_candidate_cannot_respond_to_another_candidates_invitation(): void
    {
        $invitation = Invitation::factory()->create();
        Sanctum::actingAs(CandidateProfile::factory()->create()->user);

        $this->postJson("/api/v1/candidate/invitations/{$invitation->id}/accept")->assertForbidden();
        $this->assertSame(InvitationStatus::Pending, $invitation->refresh()->status);
    }

    public function test_answered_invitation_cannot_be_answered_again(): void
    {
        $invitation = Invitation::factory()->accepted()->create();
        Sanctum::actingAs($invitation->candidateProfile->user);

        $this->postJson("/api/v1/candidate/invitations/{$invitation->id}/decline")
            ->assertForbidden()
            ->assertJsonPath('message', 'Na to zaproszenie już odpowiedziano.');
    }

    public function test_employers_get_403(): void
    {
        Sanctum::actingAs(User::factory()->employer()->create());

        $this->getJson('/api/v1/candidate/invitations')->assertForbidden();
    }
}
