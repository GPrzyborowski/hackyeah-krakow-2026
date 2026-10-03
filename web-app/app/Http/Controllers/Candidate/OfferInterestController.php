<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Models\JobOffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OfferInterestController extends Controller
{
    use ResolvesCandidateProfile;

    /**
     * "Pokaż zainteresowanie": signal interest in a published offer.
     */
    public function store(Request $request, JobOffer $offer): RedirectResponse
    {
        abort_unless($offer->isPublished(), 404);

        $this->candidateProfile($request)->interests()->firstOrCreate(['job_offer_id' => $offer->id]);

        return back();
    }

    /**
     * Withdraw the interest signal.
     */
    public function destroy(Request $request, JobOffer $offer): RedirectResponse
    {
        $this->candidateProfile($request)->interests()->where('job_offer_id', $offer->id)->delete();

        return back();
    }
}
