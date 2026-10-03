<?php

namespace App\Http\Controllers\Employer\Concerns;

use App\Enums\InvitationStatus;
use App\Models\CandidateDecision;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\JobOffer;
use App\Services\Matching\MatchScorer;
use Illuminate\Http\Request;

trait InteractsWithEmployerCompany
{
    /**
     * The company of the signed-in employer; employers without a company cannot use the panel.
     */
    protected function currentCompany(Request $request): Company
    {
        $company = $request->user()->company;

        abort_if($company === null, 403);

        return $company;
    }

    /**
     * Candidate funnel counters for an offer.
     *
     * @return array{matched_count: int, to_review_count: int, invited_count: int, responded_count: int, accepted_count: int}
     */
    protected function offerStatistics(JobOffer $offer, MatchScorer $scorer): array
    {
        $decidedIds = collect($this->reviewedCandidateIds($offer));
        $matchedIds = $scorer->matchingCandidates($offer)->pluck('id');
        $invitations = $offer->relationLoaded('invitations') ? $offer->invitations : $offer->invitations()->get();

        return [
            'matched_count' => $matchedIds->count(),
            'to_review_count' => $matchedIds->diff($decidedIds)->count(),
            'invited_count' => $invitations->count(),
            'responded_count' => $invitations->whereNotNull('responded_at')->count(),
            'accepted_count' => $invitations->where('status', InvitationStatus::Accepted)->count(),
        ];
    }

    /**
     * Candidates already skipped, saved or invited for the offer; they leave the review queue.
     *
     * @return list<int>
     */
    protected function reviewedCandidateIds(JobOffer $offer): array
    {
        $decisions = $offer->relationLoaded('decisions') ? $offer->decisions : $offer->decisions()->get(['candidate_profile_id']);
        $invitations = $offer->relationLoaded('invitations') ? $offer->invitations : $offer->invitations()->get(['candidate_profile_id']);

        $decidedIds = $decisions->toBase()->map(fn (CandidateDecision $decision): int => $decision->candidate_profile_id);
        $invitedIds = $invitations->toBase()->map(fn (Invitation $invitation): int => $invitation->candidate_profile_id);

        return array_values($decidedIds->merge($invitedIds)->unique()->all());
    }
}
