<?php

namespace Tests\Feature\Api\Shared;

use App\Enums\JobSharePairStatus;
use App\Models\Company;
use App\Models\JobShareMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\JobSharing\InteractsWithJobSharingFixtures;
use Tests\TestCase;

class JobSharingPairMessageTest extends TestCase
{
    use InteractsWithJobSharingFixtures, RefreshDatabase;

    public function test_members_chat_and_poll_for_new_messages(): void
    {
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer(Company::factory()->create(), [$recruitment]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $ewa = $this->sharer([$recruitment], 'Ewa Nowak');
        $pair = $this->pair($offer, $marta, $ewa);

        Sanctum::actingAs($marta->user);
        $firstId = $this->postJson("/api/v1/job-sharing/pairs/{$pair->id}/messages", ['body' => ' Cześć Ewa! '])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Cześć Ewa!')
            ->assertJsonPath('data.author_name', 'Marta')
            ->json('data.id');

        Sanctum::actingAs($ewa->user);
        $this->postJson("/api/v1/job-sharing/pairs/{$pair->id}/messages", ['body' => 'Hej!'])->assertCreated();

        $this->getJson("/api/v1/job-sharing/pairs/{$pair->id}/messages")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.is_mine', true)
            ->assertJsonPath('data.1.is_mine', false)
            ->assertDontSee('Kowalska');

        $this->getJson("/api/v1/job-sharing/pairs/{$pair->id}/messages?after_id={$firstId}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.body', 'Hej!');
    }

    public function test_invited_partner_who_has_not_accepted_and_outsiders_cannot_chat(): void
    {
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer(Company::factory()->create(), [$recruitment]);
        $ewa = $this->sharer([$recruitment], 'Ewa Nowak');
        $pair = $this->pair($offer, $this->sharer([$recruitment], 'Marta Kowalska'), $ewa, JobSharePairStatus::Forming);
        JobShareMessage::factory()->create(['job_share_pair_id' => $pair->id, 'body' => 'Prywatna wiadomość']);

        foreach ([$ewa->user, $this->sharer([$recruitment], 'Anna Zielińska')->user] as $user) {
            Sanctum::actingAs($user);
            $this->getJson("/api/v1/job-sharing/pairs/{$pair->id}/messages")->assertForbidden();
            $this->postJson("/api/v1/job-sharing/pairs/{$pair->id}/messages", ['body' => 'Hej'])->assertForbidden();
        }

        $this->assertDatabaseCount('job_share_messages', 1);
    }

    public function test_ended_pair_chat_is_read_only(): void
    {
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer(Company::factory()->create(), [$recruitment]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $pair = $this->pair($offer, $marta, $this->sharer([$recruitment], 'Ewa Nowak'), JobSharePairStatus::Rejected);
        JobShareMessage::factory()->create(['job_share_pair_id' => $pair->id, 'body' => 'Stara wiadomość']);
        Sanctum::actingAs($marta->user);

        $this->getJson("/api/v1/job-sharing/pairs/{$pair->id}/messages")->assertOk()->assertJsonCount(1, 'data');
        $this->postJson("/api/v1/job-sharing/pairs/{$pair->id}/messages", ['body' => 'Hej'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Ta para została zakończona – czat jest już tylko do odczytu.');

        $this->getJson("/api/v1/job-sharing/pairs/{$pair->id}")
            ->assertJsonPath('data.can.chat', true)
            ->assertJsonPath('data.can.send_message', false);
    }

    public function test_body_is_required(): void
    {
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer(Company::factory()->create(), [$recruitment]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $pair = $this->pair($offer, $marta, $this->sharer([$recruitment], 'Ewa Nowak'));
        Sanctum::actingAs($marta->user);

        $this->postJson("/api/v1/job-sharing/pairs/{$pair->id}/messages", ['body' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');
    }
}
