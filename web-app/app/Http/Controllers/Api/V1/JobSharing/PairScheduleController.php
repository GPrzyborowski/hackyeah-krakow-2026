<?php

namespace App\Http\Controllers\Api\V1\JobSharing;

use App\Http\Controllers\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\JobSharing\UpdatePairScheduleRequest;
use App\Http\Resources\Api\V1\PairResource;
use App\Models\JobSharePair;
use App\Services\JobSharing\PairLifecycle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Splitting the workday inside a formed pair and sending the pair to the employer (ScheduleValidator rules).
 */
class PairScheduleController extends Controller
{
    use ResolvesCandidateProfile;

    public function __construct(private readonly PairLifecycle $lifecycle) {}

    /**
     * Save a new proposal; both members' confirmations are reset.
     */
    public function update(UpdatePairScheduleRequest $request, JobSharePair $pair): PairResource
    {
        Gate::authorize('planSchedule', $pair);

        $this->lifecycle->saveSchedule($pair, $request->blocks());

        return new PairResource($pair->fresh() ?? $pair);
    }

    public function confirm(Request $request, JobSharePair $pair): PairResource
    {
        Gate::authorize('planSchedule', $pair);

        $this->lifecycle->confirmSchedule($pair, $this->candidateProfile($request));

        return new PairResource($pair->fresh() ?? $pair);
    }

    /**
     * Send the pair to the employer (both members must have confirmed the current split).
     */
    public function submit(JobSharePair $pair): PairResource
    {
        Gate::authorize('planSchedule', $pair);

        $this->lifecycle->submit($pair);

        return new PairResource($pair->fresh() ?? $pair);
    }
}
