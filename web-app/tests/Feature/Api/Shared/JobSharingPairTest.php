<?php

namespace Tests\Feature\Api\Shared;

use App\Enums\JobSharePairStatus;
use App\Models\Company;
use App\Models\JobSharePair;
use App\Notifications\PairInvitationAccepted;
use App\Notifications\PairInvitationReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\JobSharing\InteractsWithJobSharingFixtures;
use Tests\TestCase;

class JobSharingPairTest extends TestCase
{
    use InteractsWithJobSharingFixtures, RefreshDatabase;

    public function test_guests_and_employers_are_rejected(): void
    {
        $this->getJson('/api/v1/job-sharing')->assertUnauthorized();

        Sanctum::actingAs($this->employer());
        $this->getJson('/api/v1/job-sharing')->assertForbidden();
    }

    public function test_candidate_invites_a_partner_who_sees_and_accepts_the_invitation(): void
    {
        Notification::fake();
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer(Company::factory()->create(), [$recruitment]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $ewa = $this->sharer([$recruitment], 'Ewa Nowak');

        Sanctum::actingAs($marta->user);
        $pairId = $this->postJson("/api/v1/job-sharing/offers/{$offer->id}/pairs", ['partner_id' => $ewa->id])
            ->assertCreated()
            ->assertJsonPath('data.status', 'forming')
            ->assertJsonPath('data.members.0.is_me', true)
            ->assertJsonPath('data.members.1.display_name', 'Ewa N.')
            ->assertJsonPath('data.can.chat', true)
            ->json('data.id');
        Notification::assertSentTo($ewa->user, PairInvitationReceived::class);

        $this->getJson('/api/v1/job-sharing')
            ->assertOk()
            ->assertJsonPath('data.pairs.0.id', $pairId)
            ->assertJsonPath('data.offers.0.active_pair_state', 'invite_sent');

        Sanctum::actingAs($ewa->user);
        $this->getJson('/api/v1/job-sharing')
            ->assertOk()
            ->assertJsonCount(1, 'data.invitations')
            ->assertJsonPath('data.invitations.0.partner.display_name', 'Marta K.')
            ->assertJsonPath('data.offers.0.active_pair_state', 'invite_received')
            ->assertDontSee('Kowalska')
            ->assertDontSee($marta->user->email);

        $this->postJson("/api/v1/job-sharing/pairs/{$pairId}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'formed')
            ->assertJsonPath('data.can.plan_schedule', true);
        Notification::assertSentTo($marta->user, PairInvitationAccepted::class);
    }

    public function test_invite_validates_the_partner(): void
    {
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer(Company::factory()->create(), [$recruitment]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $notOpen = $this->candidate([$recruitment], name: 'Anna Zielińska');
        Sanctum::actingAs($marta->user);

        $this->postJson("/api/v1/job-sharing/offers/{$offer->id}/pairs", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('partner_id');
        $this->postJson("/api/v1/job-sharing/offers/{$offer->id}/pairs", ['partner_id' => $notOpen->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('partner_id');

        $this->assertDatabaseCount('job_share_pairs', 0);
    }

    public function test_partner_declines_and_initiator_cannot_answer_her_own_invitation(): void
    {
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer(Company::factory()->create(), [$recruitment]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $ewa = $this->sharer([$recruitment], 'Ewa Nowak');
        $pair = $this->pair($offer, $marta, $ewa, JobSharePairStatus::Forming);

        Sanctum::actingAs($marta->user);
        $this->postJson("/api/v1/job-sharing/pairs/{$pair->id}/accept")->assertForbidden();

        Sanctum::actingAs($ewa->user);
        $this->postJson("/api/v1/job-sharing/pairs/{$pair->id}/decline")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
        $this->postJson("/api/v1/job-sharing/pairs/{$pair->id}/accept")->assertForbidden();
    }

    public function test_member_cancels_a_formed_pair(): void
    {
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer(Company::factory()->create(), [$recruitment]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $pair = $this->pair($offer, $marta, $this->sharer([$recruitment], 'Ewa Nowak'));
        Sanctum::actingAs($marta->user);

        $this->postJson("/api/v1/job-sharing/pairs/{$pair->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
        $this->assertSame(JobSharePairStatus::Cancelled, $pair->fresh()?->status);
    }

    public function test_non_members_get_403(): void
    {
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer(Company::factory()->create(), [$recruitment]);
        $pair = $this->pair($offer, $this->sharer([$recruitment], 'Marta Kowalska'), $this->sharer([$recruitment], 'Ewa Nowak'));
        Sanctum::actingAs($this->sharer([$recruitment], 'Anna Zielińska')->user);

        $this->getJson("/api/v1/job-sharing/pairs/{$pair->id}")->assertForbidden();
        $this->postJson("/api/v1/job-sharing/pairs/{$pair->id}/cancel")->assertForbidden();
        $this->assertSame(JobSharePairStatus::Formed, JobSharePair::sole()->status);
    }
}
