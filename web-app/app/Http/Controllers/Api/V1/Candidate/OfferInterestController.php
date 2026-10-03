<?php

namespace App\Http\Controllers\Api\V1\Candidate;

use App\Http\Controllers\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Models\JobOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OfferInterestController extends Controller
{
    use ResolvesCandidateProfile;

    /**
     * "Pokaż zainteresowanie": signal interest in a published offer (idempotent).
     */
    public function store(Request $request, JobOffer $offer): Response
    {
        abort_unless($offer->isPublished(), 404);

        $this->candidateProfile($request)->interests()->firstOrCreate(['job_offer_id' => $offer->id]);

        return response()->noContent();
    }

    /**
     * Withdraw the interest signal (idempotent).
     */
    public function destroy(Request $request, JobOffer $offer): Response
    {
        $this->candidateProfile($request)->interests()->where('job_offer_id', $offer->id)->delete();

        return response()->noContent();
    }
}
