<?php

namespace Tests\Feature\JobSharing;

use App\Enums\JobSharePairStatus;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\JobSharePair;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleFlowTest extends TestCase
{
    use InteractsWithJobSharingFixtures, RefreshDatabase;

    public function test_saving_a_proposal_stores_it_and_resets_both_confirmations()
    {
        [$pair, $marta, $ewa] = $this->formedPair();
        $pair->members()->updateExistingPivot($ewa->id, ['schedule_confirmed_at' => now()]);

        $this->actingAs($marta->user)
            ->put(route('job-sharing.pairs.schedule.update', $pair), ['schedule' => [
                ['candidate_profile_id' => $marta->id, 'starts_at' => '08:00', 'ends_at' => '13:00'],
                ['candidate_profile_id' => $ewa->id, 'starts_at' => '13:00', 'ends_at' => '16:00'],
            ]])
            ->assertSessionHasNoErrors();

        $pair->refresh();
        $this->assertSame('13:00', $pair->proposed_schedule[0]['ends_at'] ?? null);
        $this->assertSame(0, $pair->members()->wherePivotNotNull('schedule_confirmed_at')->count());
    }

    public function test_invalid_proposal_is_rejected()
    {
        [$pair, $marta, $ewa] = $this->formedPair();

        $this->actingAs($marta->user)
            ->put(route('job-sharing.pairs.schedule.update', $pair), ['schedule' => [
                ['candidate_profile_id' => $marta->id, 'starts_at' => '08:00', 'ends_at' => '11:00'],
                ['candidate_profile_id' => $ewa->id, 'starts_at' => '12:00', 'ends_at' => '16:00'],
            ]])
            ->assertSessionHasErrors('schedule');

        $this->assertNull($pair->fresh()?->proposed_schedule);
    }

    public function test_pair_is_sent_to_the_employer_only_after_both_confirm()
    {
        [$pair, $marta, $ewa] = $this->formedPair(withSchedule: true);

        $this->actingAs($marta->user)->post(route('job-sharing.pairs.schedule.confirm', $pair))->assertSessionHasNoErrors();
        $this->actingAs($marta->user)->post(route('job-sharing.pairs.submit', $pair))->assertSessionHasErrors('schedule');
        $this->assertSame(JobSharePairStatus::Formed, $pair->fresh()?->status);

        $this->actingAs($ewa->user)->post(route('job-sharing.pairs.schedule.confirm', $pair))->assertSessionHasNoErrors();
        $this->actingAs($ewa->user)->post(route('job-sharing.pairs.submit', $pair))->assertSessionHasNoErrors();

        $pair->refresh();
        $this->assertSame(JobSharePairStatus::Submitted, $pair->status);
        $this->assertNotNull($pair->submitted_at);

        $this->actingAs($marta->user)
            ->put(route('job-sharing.pairs.schedule.update', $pair), ['schedule' => $pair->proposed_schedule])
            ->assertForbidden();
    }

    public function test_confirming_requires_a_saved_proposal_and_a_formed_pair()
    {
        [$pair, $marta] = $this->formedPair();

        $this->actingAs($marta->user)->post(route('job-sharing.pairs.schedule.confirm', $pair))->assertSessionHasErrors('schedule');

        $recruitment = $this->skill('Rekrutacja IT');
        $forming = $this->pair($pair->jobOffer, $this->sharer([$recruitment], 'Anna Zielińska'), $this->sharer([$recruitment], 'Ola Mazur'), JobSharePairStatus::Forming);
        $initiator = $forming->members()->wherePivot('is_initiator', true)->sole();

        $this->actingAs($initiator->user)->post(route('job-sharing.pairs.schedule.confirm', $forming))->assertForbidden();
    }

    /**
     * @return array{0: JobSharePair, 1: CandidateProfile, 2: CandidateProfile}
     */
    private function formedPair(bool $withSchedule = false): array
    {
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer(Company::factory()->create(), [$recruitment]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $ewa = $this->sharer([$recruitment], 'Ewa Nowak');

        $pair = $withSchedule ? $this->scheduledPair($offer, $marta, $ewa) : $this->pair($offer, $marta, $ewa);

        return [$pair, $marta, $ewa];
    }
}
