<?php

namespace App\Http\Controllers\Employer;

use App\Enums\CandidateDecisionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\StoreCandidateDecisionRequest;
use App\Models\CandidateDecision;
use App\Models\CandidateProfile;
use App\Models\JobOffer;
use App\Services\Matching\MatchScorer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CandidateDecisionController extends Controller
{
    /**
     * Skip a candidate or save her for later; only candidates matched to the offer can be decided on.
     */
    public function store(StoreCandidateDecisionRequest $request, JobOffer $offer, CandidateProfile $candidate, MatchScorer $scorer): RedirectResponse
    {
        Gate::authorize('reviewCandidates', $offer);

        abort_unless($scorer->matchingCandidates($offer)->whereKey($candidate->id)->exists(), 404);

        $existing = CandidateDecision::query()->where('job_offer_id', $offer->id)->where('candidate_profile_id', $candidate->id)->first();

        if ($existing?->decision !== CandidateDecisionType::Invited) {
            CandidateDecision::query()->updateOrCreate(
                ['job_offer_id' => $offer->id, 'candidate_profile_id' => $candidate->id],
                ['decision' => $request->validated('decision')],
            );
        }

        return to_route('employer.candidates.index', ['offer' => $offer->id]);
    }
}
