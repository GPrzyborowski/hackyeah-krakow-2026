<?php

namespace App\Services\Candidate;

use App\Enums\CandidateStage;
use App\Models\CandidateProfile;
use Carbon\CarbonImmutable;

/**
 * Three-phase return calendar built from the candidate's private stage and dates.
 * Pregnant: pregnancy -> leave -> ready. After leave: leave -> return -> ready (no pregnancy week).
 * Only ever shown to the candidate herself.
 */
class ReturnCalendar
{
    private const int PREGNANCY_WEEKS = 40;

    /**
     * How long before her start date a candidate after leave is in the "return" (preparing to come back) phase.
     */
    private const int RETURN_WINDOW_DAYS = 60;

    /**
     * Any of the dates (and the stage of older profiles) may be missing.
     *
     * @return array{stage: string|null, stage_label: string|null, phases: list<string>, pregnancy_week: int|null, due_date: string|null, leave_starts_on: string|null, available_from: string|null, current_phase: string}
     */
    public function forProfile(CandidateProfile $profile): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $stage = $profile->stage;
        $pregnancyWeek = $stage === CandidateStage::AfterLeave ? null : $this->pregnancyWeek($profile, $today);

        return [
            'stage' => $stage?->value,
            'stage_label' => $stage?->label(),
            'phases' => $stage === CandidateStage::AfterLeave ? ['leave', 'return', 'ready'] : ['pregnancy', 'leave', 'ready'],
            'pregnancy_week' => $pregnancyWeek,
            'due_date' => $stage === CandidateStage::AfterLeave ? null : $profile->due_date?->toDateString(),
            'leave_starts_on' => $profile->leave_starts_on?->toDateString(),
            'available_from' => $profile->available_from?->toDateString(),
            'current_phase' => match ($stage) {
                CandidateStage::Pregnant => $this->pregnantPhase($profile, $today),
                CandidateStage::AfterLeave => $this->afterLeavePhase($profile, $today),
                null => $this->phaseWithoutStage($profile, $today, $pregnancyWeek),
            },
        ];
    }

    private function pregnancyWeek(CandidateProfile $profile, CarbonImmutable $today): ?int
    {
        if ($profile->due_date === null || ! $profile->due_date->isAfter($today)) {
            return null;
        }

        $weeksLeft = (int) floor($today->diffInDays($profile->due_date) / 7);

        return max(1, self::PREGNANCY_WEEKS - $weeksLeft);
    }

    /**
     * Pregnant until the leave starts (or the due date passes); without any dates she is simply pregnant.
     */
    private function pregnantPhase(CandidateProfile $profile, CarbonImmutable $today): string
    {
        return match (true) {
            $this->hasStarted($profile->available_from, $today) => 'ready',
            $this->hasStarted($profile->leave_starts_on, $today), $this->hasStarted($profile->due_date, $today) => 'leave',
            default => 'pregnancy',
        };
    }

    /**
     * On leave while the start date is far away, preparing to return in the last weeks before it;
     * without any dates she is returning.
     */
    private function afterLeavePhase(CandidateProfile $profile, CarbonImmutable $today): string
    {
        return match (true) {
            $this->hasStarted($profile->available_from, $today) => 'ready',
            $profile->available_from !== null && $profile->available_from->isAfter($today->addDays(self::RETURN_WINDOW_DAYS)) => 'leave',
            default => 'return',
        };
    }

    /**
     * Older profiles without a stage: derived from the dates alone, otherwise ready.
     */
    private function phaseWithoutStage(CandidateProfile $profile, CarbonImmutable $today, ?int $pregnancyWeek): string
    {
        return match (true) {
            $this->hasStarted($profile->available_from, $today) => 'ready',
            $this->hasStarted($profile->leave_starts_on, $today) => 'leave',
            $pregnancyWeek !== null => 'pregnancy',
            default => 'ready',
        };
    }

    private function hasStarted(?CarbonImmutable $date, CarbonImmutable $today): bool
    {
        return $date !== null && ! $date->isAfter($today);
    }
}
