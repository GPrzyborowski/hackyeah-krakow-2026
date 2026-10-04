<?php

namespace App\Http\Controllers\JobSharing;

use App\Http\Controllers\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Models\JobOffer;
use App\Services\JobSharing\PairLifecycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JoinLinkController extends Controller
{
    use ResolvesCandidateProfile;

    /**
     * Create (or reuse) the link the candidate sends to a friend to join her pair; the page shows it afterwards.
     */
    public function store(Request $request, JobOffer $offer, PairLifecycle $lifecycle): RedirectResponse
    {
        abort_unless($offer->is_job_share && $offer->isPublished(), 404);

        $lifecycle->createJoinLink($this->candidateProfile($request), $offer);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Link gotowy. Wyślij go koleżance.']);

        return back();
    }
}
