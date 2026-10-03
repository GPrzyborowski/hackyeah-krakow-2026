<?php

namespace App\Http\Controllers\Api\V1\JobSharing;

use App\Enums\JobSharePairStatus;
use App\Http\Controllers\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\JobSharing\StorePairRequest;
use App\Http\Resources\Api\V1\PairListItemResource;
use App\Http\Resources\Api\V1\PairOfferResource;
use App\Http\Resources\Api\V1\PairResource;
use App\Models\CandidateProfile;
use App\Models\JobOffer;
use App\Models\JobSharePair;
use App\Services\JobSharing\PairLifecycle;
use App\Services\JobSharing\PairPresenter;
use App\Services\JobSharing\PartnerFinder;
use App\Services\Matching\MatchScorer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Job-sharing pairs of the signed-in candidate: the hub, inviting a partner and answering pair invitations.
 */
class PairController extends Controller
{
    use ResolvesCandidateProfile;

    public function __construct(
        private readonly PairLifecycle $lifecycle,
        private readonly PairPresenter $presenter,
    ) {}

    /**
     * Pair invitations waiting for her answer, her pairs and job-sharing offers with her active pair state.
     */
    public function index(Request $request, MatchScorer $scorer, PartnerFinder $finder): JsonResponse
    {
        $profile = $this->candidateProfile($request);

        $pairs = $profile->jobSharePairs()
            ->with(['jobOffer.company', 'members.user'])
            ->where('status', '!=', JobSharePairStatus::Cancelled)
            ->latest('job_share_pairs.updated_at')
            ->get();

        [$invitations, $ownPairs] = $pairs->partition(
            fn (JobSharePair $pair): bool => $pair->status === JobSharePairStatus::Forming && ! $this->isAcceptedMember($pair, $profile),
        );

        $offers = $scorer->rankOffersFor($profile, JobOffer::query()->where('is_job_share', true))
            ->map(fn (array $row): array => [
                'offer' => $row['offer'],
                'score' => $row['match']->score,
                'active_pair' => $finder->activePairFor($profile, $row['offer']),
            ]);

        return response()->json(['data' => [
            'is_open_to_job_sharing' => $profile->open_to_job_sharing,
            'is_profile_published' => $profile->isPublished(),
            'invitations' => PairListItemResource::collection($invitations->values())->toArray($request),
            'pairs' => PairListItemResource::collection($ownPairs->values())->toArray($request),
            'offers' => PairOfferResource::collection($offers->values())->toArray($request),
        ]]);
    }

    public function show(JobSharePair $pair): PairResource
    {
        Gate::authorize('view', $pair);

        return new PairResource($pair);
    }

    /**
     * Invite another candidate (an id from the partners endpoint) to apply together; the partner is notified.
     */
    public function store(StorePairRequest $request, JobOffer $offer): JsonResponse
    {
        abort_unless($offer->is_job_share && $offer->isPublished(), 404);

        $pair = $this->lifecycle->invite($this->candidateProfile($request), $offer, $request->integer('partner_id'));

        return (new PairResource($pair))->response()->setStatusCode(201);
    }

    /**
     * The invited partner joins the pair; the initiator is notified.
     */
    public function accept(Request $request, JobSharePair $pair): PairResource
    {
        Gate::authorize('respond', $pair);

        $this->lifecycle->accept($this->candidateProfile($request), $pair);

        return new PairResource($pair->fresh() ?? $pair);
    }

    public function decline(JobSharePair $pair): PairResource
    {
        Gate::authorize('respond', $pair);

        $pair->update(['status' => JobSharePairStatus::Cancelled]);

        return new PairResource($pair);
    }

    /**
     * Leave a pair that has not been sent to the employer yet.
     */
    public function cancel(JobSharePair $pair): PairResource
    {
        Gate::authorize('cancel', $pair);

        $pair->update(['status' => JobSharePairStatus::Cancelled]);

        return new PairResource($pair);
    }

    private function isAcceptedMember(JobSharePair $pair, CandidateProfile $profile): bool
    {
        $member = $pair->members->firstWhere('id', $profile->id);

        return $member !== null && $this->presenter->hasAccepted($member);
    }
}
