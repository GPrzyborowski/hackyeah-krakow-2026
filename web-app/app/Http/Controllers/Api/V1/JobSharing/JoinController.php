<?php

namespace App\Http\Controllers\Api\V1\JobSharing;

use App\Http\Controllers\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PairResource;
use App\Models\JobSharePairInvitation;
use App\Services\JobSharing\PairJoinLinks;
use App\Services\JobSharing\PairLifecycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The link a candidate shared with a friend: preview (also for guests) and joining the pair.
 */
class JoinController extends Controller
{
    use ResolvesCandidateProfile;

    public function __construct(private readonly PairJoinLinks $joinLinks) {}

    /**
     * Offer and inviter behind the link and whether the caller (a bearer token is optional) can join.
     */
    public function show(Request $request, string $token): JsonResponse
    {
        $invitation = $this->findOrFail($token);
        $user = $request->user('sanctum');
        $problem = $this->joinLinks->problemWith($invitation, $user);

        return response()->json(['data' => [
            ...$this->joinLinks->preview($invitation),
            'can_join' => $user !== null && $problem === null,
            'requires_sign_in' => $user === null && $problem === null,
            'reason' => $problem?->value,
            'reason_message' => $problem?->message(),
        ]]);
    }

    /**
     * The signed-in candidate joins the pair.
     */
    public function store(Request $request, string $token, PairLifecycle $lifecycle): PairResource
    {
        $pair = $lifecycle->joinByLink($this->candidateProfile($request), $this->findOrFail($token));

        return new PairResource($pair->fresh() ?? $pair);
    }

    private function findOrFail(string $token): JobSharePairInvitation
    {
        $invitation = $this->joinLinks->find($token);

        abort_if($invitation === null, 404);

        return $invitation;
    }
}
