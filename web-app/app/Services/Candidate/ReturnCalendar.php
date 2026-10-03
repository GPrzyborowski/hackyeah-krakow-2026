<?php

namespace App\Services\Candidate;

use App\Models\CandidateProfile;

/**
 * Three-phase return calendar (pregnancy, leave, ready) built from the candidate's private dates.
 * Only ever shown to the candidate herself.
 */
class ReturnCalendar
{
    private const int PREGNANCY_WEEKS = 40;

    /**
     * Any of the dates may be missing.
     *
     * @return array{pregnancy_week: int|null, due_date: string|null, leave_starts_on: string|null, available_from: string|null, current_phase: string}
     */
    public function forProfile(CandidateProfile $profile): array
    {
        $today = now()->startOfDay();
        $pregnancyWeek = null;

        if ($profile->due_date !== null && $profile->due_date->isAfter($today)) {
            $weeksLeft = (int) floor($today->diffInDays($profile->due_date) / 7);
            $pregnancyWeek = max(1, self::PREGNANCY_WEEKS - $weeksLeft);
        }

        $currentPhase = match (true) {
            $profile->available_from !== null && ! $profile->available_from->isAfter($today) => 'ready',
            $profile->leave_starts_on !== null && ! $profile->leave_starts_on->isAfter($today) => 'leave',
            $pregnancyWeek !== null => 'pregnancy',
            default => 'leave',
        };

        return [
            'pregnancy_week' => $pregnancyWeek,
            'due_date' => $profile->due_date?->toDateString(),
            'leave_starts_on' => $profile->leave_starts_on?->toDateString(),
            'available_from' => $profile->available_from?->toDateString(),
            'current_phase' => $currentPhase,
        ];
    }
}
