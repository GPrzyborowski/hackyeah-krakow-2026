<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Models\JobOffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SavedOfferController extends Controller
{
    use ResolvesCandidateProfile;

    /**
     * "Zapisz": bookmark a published offer.
     */
    public function store(Request $request, JobOffer $offer): RedirectResponse
    {
        abort_unless($offer->isPublished(), 404);

        $this->candidateProfile($request)->savedOffers()->syncWithoutDetaching([$offer->id]);

        return back();
    }

    /**
     * Remove the bookmark.
     */
    public function destroy(Request $request, JobOffer $offer): RedirectResponse
    {
        $this->candidateProfile($request)->savedOffers()->detach($offer->id);

        return back();
    }
}
