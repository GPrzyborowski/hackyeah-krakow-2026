<?php

namespace Tests\Feature\Api\Shared;

use App\Enums\JobSharePairStatus;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\JobSharing\InteractsWithJobSharingFixtures;
use Tests\TestCase;

class JobSharingScheduleTest extends TestCase
{
    use InteractsWithJobSharingFixtures, RefreshDatabase;

    public function test_members_save_confirm_and_submit_the_split(): void
    {
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer(Company::factory()->create(), [$recruitment]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $ewa = $this->sharer([$recruitment], 'Ewa Nowak');
        $pair = $this->pair($offer, $marta, $ewa);

        Sanctum::actingAs($marta->user);
        $this->putJson("/api/v1/job-sharing/pairs/{$pair->id}/schedule", ['schedule' => [
            ['candidate_profile_id' => $marta->id, 'starts_at' => '08:00', 'ends_at' => '12:00'],
            ['candidate_profile_id' => $ewa->id, 'starts_at' => '12:00', 'ends_at' => '16:00'],
        ]])
            ->assertOk()
            ->assertJsonPath('data.has_saved_schedule', true)
            ->assertJsonPath('data.schedule.0.starts_at', '08:00');

        $this->postJson("/api/v1/job-sharing/pairs/{$pair->id}/schedule/confirm")
            ->assertOk()
            ->assertJsonPath('data.members.0.has_confirmed_schedule', true);

        $this->postJson("/api/v1/job-sharing/pairs/{$pair->id}/submit")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('schedule');

        Sanctum::actingAs($ewa->user);
        $this->postJson("/api/v1/job-sharing/pairs/{$pair->id}/schedule/confirm")->assertOk();
        $this->postJson("/api/v1/job-sharing/pairs/{$pair->id}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.can.plan_schedule', false);
    }

    public function test_invalid_split_is_rejected_by_the_schedule_validator(): void
    {
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer(Company::factory()->create(), [$recruitment]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $ewa = $this->sharer([$recruitment], 'Ewa Nowak');
        $pair = $this->pair($offer, $marta, $ewa);
        Sanctum::actingAs($marta->user);

        $this->putJson("/api/v1/job-sharing/pairs/{$pair->id}/schedule", ['schedule' => [
            ['candidate_profile_id' => $marta->id, 'starts_at' => '08:00', 'ends_at' => '11:00'],
            ['candidate_profile_id' => $ewa->id, 'starts_at' => '12:00', 'ends_at' => '16:00'],
        ]])->assertUnprocessable()->assertJsonValidationErrors('schedule');

        $this->putJson("/api/v1/job-sharing/pairs/{$pair->id}/schedule", ['schedule' => [
            ['candidate_profile_id' => $marta->id, 'starts_at' => '8 rano', 'ends_at' => '12:00'],
        ]])->assertUnprocessable()->assertJsonValidationErrors('schedule.0.starts_at');

        $this->postJson("/api/v1/job-sharing/pairs/{$pair->id}/schedule/confirm")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('schedule');

        $this->assertNull($pair->fresh()?->proposed_schedule);
    }

    public function test_split_cannot_be_planned_by_non_members_or_in_a_forming_pair(): void
    {
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer(Company::factory()->create(), [$recruitment]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $pair = $this->pair($offer, $marta, $this->sharer([$recruitment], 'Ewa Nowak'), JobSharePairStatus::Forming);

        Sanctum::actingAs($marta->user);
        $this->postJson("/api/v1/job-sharing/pairs/{$pair->id}/schedule/confirm")->assertForbidden();

        Sanctum::actingAs($this->sharer([$recruitment], 'Anna Zielińska')->user);
        $this->postJson("/api/v1/job-sharing/pairs/{$pair->id}/submit")->assertForbidden();
    }
}
