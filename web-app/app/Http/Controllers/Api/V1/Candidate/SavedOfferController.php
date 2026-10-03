<?php

namespace App\Http\Controllers\Api\V1\Candidate;

use App\Http\Controllers\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Models\JobOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SavedOfferController extends Controller
{
    use ResolvesCandidateProfile;

    /**
     * "Zapisz": bookmark a published offer (idempotent).
     */
    public function store(Request $request, JobOffer $offer): Response
    {
        abort_unless($offer->isPublished(), 404);

        $this->candidateProfile($request)->savedOffers()->syncWithoutDetaching([$offer->id]);

        return response()->noContent();
    }

    /**
     * Remove the bookmark (idempotent).
     */
    public function destroy(Request $request, JobOffer $offer): Response
    {
        $this->candidateProfile($request)->savedOffers()->detach($offer->id);

        return response()->noContent();
    }
}
