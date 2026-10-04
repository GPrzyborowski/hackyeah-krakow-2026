<?php

namespace App\Http\Controllers\Api\V1\JobSharing;

use App\Http\Controllers\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Models\JobOffer;
use App\Services\JobSharing\PairJoinLinks;
use App\Services\JobSharing\PairLifecycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JoinLinkController extends Controller
{
    use ResolvesCandidateProfile;

    /**
     * Create (or reuse) the link the candidate shares with a friend to join her pair for the offer.
     */
    public function store(Request $request, JobOffer $offer, PairLifecycle $lifecycle, PairJoinLinks $joinLinks): JsonResponse
    {
        abort_unless($offer->is_job_share && $offer->isPublished(), 404);

        $link = $lifecycle->createJoinLink($this->candidateProfile($request), $offer);

        return response()->json(['data' => [
            ...$joinLinks->presentLink($link),
            'pair_id' => $link->job_share_pair_id,
        ]], $link->wasRecentlyCreated ? 201 : 200);
    }
}
