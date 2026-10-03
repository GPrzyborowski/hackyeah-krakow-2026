<?php

namespace App\Services\Employer;

use App\Enums\CandidateDecisionType;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\JobOffer;
use App\Models\OfferInterest;
use App\Services\Matching\MatchResult;
use App\Services\Matching\MatchScorer;
use Illuminate\Support\Collection;

/**
 * The employer's swipe queue for one published offer: undecided matches (interested candidates first) and saved ones.
 */
class CandidateReviewQueue
{
    public function __construct(private readonly MatchScorer $scorer) {}

    /**
     * Candidates who expressed interest in the offer.
     *
     * @return list<int>
     */
    public function interestedCandidateIds(JobOffer $offer): array
    {
        return array_values($offer->interests()->get(['candidate_profile_id'])->map(fn (OfferInterest $interest): int => $interest->candidate_profile_id)->all());
    }

    /**
     * Undecided matching candidates; those who expressed interest in the offer come first.
     *
     * @param  list<int>  $reviewedCandidateIds
     * @param  list<int>  $interestedCandidateIds
     * @return Collection<int, array{candidate: CandidateProfile, match: MatchResult}>
     */
    public function pending(JobOffer $offer, array $reviewedCandidateIds, array $interestedCandidateIds): Collection
    {
        [$interested, $others] = $this->scorer->rankCandidatesFor($offer, $reviewedCandidateIds)
            ->partition(fn (array $row): bool => in_array($row['candidate']->id, $interestedCandidateIds, true));

        return $interested->concat($others)->values();
    }

    /**
     * Candidates saved for later who are still visible to the company, best match first.
     *
     * @return Collection<int, array{candidate: CandidateProfile, match: MatchResult}>
     */
    public function saved(JobOffer $offer, Company $company): Collection
    {
        $savedIds = $offer->decisions()->where('decision', CandidateDecisionType::Saved)->pluck('candidate_profile_id');

        return CandidateProfile::query()
            ->visibleTo($company)
            ->whereKey($savedIds)
            ->with(['user', 'confirmedSkills'])
            ->get()
            ->map(fn (CandidateProfile $candidate): array => ['candidate' => $candidate, 'match' => $this->scorer->score($candidate, $offer)])
            ->sortByDesc(fn (array $row): int => $row['match']->score)
            ->values();
    }
}
