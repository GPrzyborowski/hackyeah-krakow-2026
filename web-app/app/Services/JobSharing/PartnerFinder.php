<?php

namespace App\Services\JobSharing;

use App\Enums\DayPart;
use App\Enums\JobSharePairStatus;
use App\Models\CandidateProfile;
use App\Models\JobOffer;
use App\Models\JobSharePair;
use App\Models\OfferInterest;
use App\Services\Matching\MatchResult;
use App\Services\Matching\MatchScorer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Finds candidates who could share a job-sharing position with the searching candidate.
 */
class PartnerFinder
{
    /**
     * Pairs in these states still hold their members for the offer.
     */
    public const array ACTIVE_STATUSES = [
        JobSharePairStatus::Forming,
        JobSharePairStatus::Formed,
        JobSharePairStatus::Submitted,
        JobSharePairStatus::Invited,
    ];

    public function __construct(private readonly MatchScorer $scorer) {}

    /**
     * The candidate's pair for the offer that is still in progress, if any.
     */
    public function activePairFor(CandidateProfile $candidate, JobOffer $offer): ?JobSharePair
    {
        return $candidate->jobSharePairs()
            ->where('job_offer_id', $offer->id)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->latest('job_share_pairs.id')
            ->first();
    }

    /**
     * Possible partners, candidates interested in the offer first, then by match score.
     *
     * @return Collection<int, array{candidate: CandidateProfile, match: MatchResult, is_interested: bool, is_complementary: bool}>
     */
    public function partnersFor(CandidateProfile $searcher, JobOffer $offer): Collection
    {
        if (! $offer->is_job_share || ! $offer->isPublished() || $this->activePairFor($searcher, $offer) !== null) {
            return collect();
        }

        $excludedIds = [$searcher->id, ...$this->pairedCandidateIds($offer)];
        $interestedIds = array_values($offer->interests()->get(['candidate_profile_id'])
            ->map(fn (OfferInterest $interest): int => $interest->candidate_profile_id)
            ->all());

        [$interested, $others] = $this->scorer->rankCandidatesFor($offer, $excludedIds)
            ->filter(fn (array $row): bool => $row['candidate']->open_to_job_sharing)
            ->map(fn (array $row): array => [
                'candidate' => $row['candidate'],
                'match' => $row['match'],
                'is_interested' => in_array($row['candidate']->id, $interestedIds, true),
                'is_complementary' => $this->areComplementary($searcher->preferred_day_part, $row['candidate']->preferred_day_part),
            ])
            ->partition(fn (array $row): bool => $row['is_interested']);

        return $interested->concat($others)->values();
    }

    public function isPossiblePartner(CandidateProfile $searcher, JobOffer $offer, CandidateProfile $partner): bool
    {
        return $this->partnersFor($searcher, $offer)->contains(fn (array $row): bool => $row['candidate']->id === $partner->id);
    }

    /**
     * One prefers mornings and the other afternoons.
     */
    public function areComplementary(?DayPart $first, ?DayPart $second): bool
    {
        return ($first === DayPart::Morning && $second === DayPart::Afternoon)
            || ($first === DayPart::Afternoon && $second === DayPart::Morning);
    }

    /**
     * Candidates already in an active pair for the offer.
     *
     * @return list<int>
     */
    private function pairedCandidateIds(JobOffer $offer): array
    {
        return array_values(CandidateProfile::query()
            ->whereHas('jobSharePairs', fn (Builder $query) => $query
                ->where('job_offer_id', $offer->id)
                ->whereIn('status', self::ACTIVE_STATUSES))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all());
    }
}
