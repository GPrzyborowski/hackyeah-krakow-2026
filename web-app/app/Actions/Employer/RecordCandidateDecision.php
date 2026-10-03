<?php

namespace App\Actions\Employer;

use App\Enums\CandidateDecisionType;
use App\Models\CandidateDecision;
use App\Models\CandidateProfile;
use App\Models\JobOffer;
use App\Services\Matching\MatchScorer;

/**
 * Skips a candidate or saves her for later; only candidates matched to the offer can be decided on
 * and an invitation is never downgraded to a skip or save.
 */
class RecordCandidateDecision
{
    public function __construct(private readonly MatchScorer $scorer) {}

    public function handle(JobOffer $offer, CandidateProfile $candidate, CandidateDecisionType $decision): CandidateDecision
    {
        abort_unless($this->scorer->matchingCandidates($offer)->whereKey($candidate->id)->exists(), 404);

        $existing = CandidateDecision::query()->where('job_offer_id', $offer->id)->where('candidate_profile_id', $candidate->id)->first();

        if ($existing?->decision === CandidateDecisionType::Invited) {
            return $existing;
        }

        return CandidateDecision::query()->updateOrCreate(
            ['job_offer_id' => $offer->id, 'candidate_profile_id' => $candidate->id],
            ['decision' => $decision],
        );
    }
}
