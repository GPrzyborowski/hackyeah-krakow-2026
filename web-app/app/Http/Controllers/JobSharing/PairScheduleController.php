<?php

namespace App\Http\Controllers\JobSharing;

use App\Http\Controllers\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\JobSharing\UpdatePairScheduleRequest;
use App\Models\JobSharePair;
use App\Services\JobSharing\PairLifecycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class PairScheduleController extends Controller
{
    use ResolvesCandidateProfile;

    public function __construct(private readonly PairLifecycle $lifecycle) {}

    /**
     * Save a new proposal of the day split; both members have to accept it again.
     */
    public function update(UpdatePairScheduleRequest $request, JobSharePair $pair): RedirectResponse
    {
        Gate::authorize('planSchedule', $pair);

        $this->lifecycle->saveSchedule($pair, $request->blocks());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Podział zapisany. Teraz każda z Was musi go zaakceptować.']);

        return back();
    }

    /**
     * The signed-in member accepts the current proposal.
     */
    public function confirm(Request $request, JobSharePair $pair): RedirectResponse
    {
        Gate::authorize('planSchedule', $pair);

        $this->lifecycle->confirmSchedule($pair, $this->candidateProfile($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Zaakceptowałaś podział dnia.']);

        return back();
    }

    /**
     * Send the pair to the employer once both members accepted the split.
     */
    public function submit(JobSharePair $pair): RedirectResponse
    {
        Gate::authorize('planSchedule', $pair);

        $this->lifecycle->submit($pair);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Wysłane do firmy. Firma zobaczy Was jako parę, nadal anonimowo.']);

        return back();
    }
}
