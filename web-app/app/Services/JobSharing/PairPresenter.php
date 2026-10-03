<?php

namespace App\Services\JobSharing;

use App\Enums\DayPart;
use App\Enums\JobSharePairStatus;
use App\Models\CandidateProfile;
use App\Models\JobSharePair;
use Illuminate\Database\Eloquent\Collection;

/**
 * Shared read helpers for job-sharing pairs: member order, pivot flags and the day split.
 */
class PairPresenter
{
    /**
     * Members in a stable order: the initiator first ("A", peach), the partner second ("B", yellow).
     *
     * @return Collection<int, CandidateProfile>
     */
    public function members(JobSharePair $pair): Collection
    {
        $pair->loadMissing('members.user');

        return $pair->members
            ->sortBy(fn (CandidateProfile $member): array => [$this->isInitiator($member) ? 0 : 1, $member->id])
            ->values();
    }

    public function isInitiator(CandidateProfile $member): bool
    {
        return (bool) $this->pivot($member, 'is_initiator');
    }

    public function hasAccepted(CandidateProfile $member): bool
    {
        return $this->pivot($member, 'accepted_at') !== null;
    }

    public function hasConfirmedSchedule(CandidateProfile $member): bool
    {
        return $this->pivot($member, 'schedule_confirmed_at') !== null;
    }

    public function firstName(CandidateProfile $member): string
    {
        $parts = preg_split('/\s+/', trim($member->user->name)) ?: [];

        return $parts[0] ?? $member->user->name;
    }

    /**
     * The stored proposal, or an even split that respects the members' preferred parts of the day.
     *
     * @param  Collection<int, CandidateProfile>  $members
     * @return list<array{candidate_profile_id: int, starts_at: string, ends_at: string}>
     */
    public function schedule(JobSharePair $pair, Collection $members): array
    {
        if ($pair->proposed_schedule !== null) {
            return $pair->proposed_schedule;
        }

        if ($members->count() < JobSharePair::MAX_MEMBERS) {
            return [];
        }

        $workday = Workday::forOffer($pair->jobOffer);
        $first = $members[0];
        $second = $members[1];

        if ($first->preferred_day_part === DayPart::Afternoon || $second->preferred_day_part === DayPart::Morning) {
            [$first, $second] = [$second, $first];
        }

        return [
            ['candidate_profile_id' => $first->id, 'starts_at' => Workday::format($workday->startsAt), 'ends_at' => Workday::format($workday->midpoint())],
            ['candidate_profile_id' => $second->id, 'starts_at' => Workday::format($workday->midpoint()), 'ends_at' => Workday::format($workday->endsAt)],
        ];
    }

    /**
     * How a candidate relates to her active pair, loaded through her own pairs relation (pivot = her membership).
     *
     * @return 'pair'|'invite_sent'|'invite_received'
     */
    public function viewerState(JobSharePair $pair): string
    {
        if ($pair->status !== JobSharePairStatus::Forming) {
            return 'pair';
        }

        return $pair->getRelationValue('pivot')?->getAttribute('accepted_at') !== null ? 'invite_sent' : 'invite_received';
    }

    private function pivot(CandidateProfile $member, string $key): mixed
    {
        $pivot = $member->getRelationValue('pivot');

        return $pivot?->getAttribute($key);
    }
}
