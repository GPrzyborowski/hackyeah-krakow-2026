<?php

namespace App\Http\Controllers\Employer;

use App\Actions\Employer\RecordCandidateDecision;
use App\Enums\CandidateDecisionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\StoreCandidateDecisionRequest;
use App\Models\CandidateProfile;
use App\Models\JobOffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CandidateDecisionController extends Controller
{
    /**
     * Skip a candidate or save her for later; only candidates matched to the offer can be decided on.
     */
    public function store(StoreCandidateDecisionRequest $request, JobOffer $offer, CandidateProfile $candidate, RecordCandidateDecision $recordCandidateDecision): RedirectResponse
    {
        Gate::authorize('reviewCandidates', $offer);

        $recordCandidateDecision->handle($offer, $candidate, CandidateDecisionType::from($request->validated('decision')));

        return to_route('employer.candidates.index', ['offer' => $offer->id]);
    }
}
