<?php

namespace App\Http\Controllers\Api\V1\Employer;

use App\Actions\Employer\RecordCandidateDecision;
use App\Enums\CandidateDecisionType;
use App\Enums\JobSharePairStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Employer\Concerns\InteractsWithEmployerCompany;
use App\Http\Requests\Employer\StoreCandidateDecisionRequest;
use App\Http\Resources\Api\V1\EmployerCandidateCardResource;
use App\Http\Resources\Api\V1\EmployerCandidateDecisionResource;
use App\Http\Resources\Api\V1\EmployerSavedCandidateResource;
use App\Models\CandidateProfile;
use App\Models\JobOffer;
use App\Services\Employer\CandidateReviewQueue;
use App\Services\Matching\MatchResult;
use App\Services\Matching\MatchScorer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * The swipe queue of anonymous candidates for one published offer.
 */
class CandidateController extends Controller
{
    use InteractsWithEmployerCompany;

    public function __construct(private readonly CandidateReviewQueue $reviewQueue) {}

    /**
     * The next undecided candidate (interested ones first) with counters and the saved strip.
     * Pass `?candidate=<id>` to bring a saved candidate back onto the card.
     */
    public function next(Request $request, JobOffer $offer, MatchScorer $scorer): JsonResponse
    {
        Gate::authorize('reviewCandidates', $offer);

        $company = $this->currentCompany($request);
        $offer->load(['skills', 'company', 'invitations', 'decisions:id,job_offer_id,candidate_profile_id']);

        $interestedIds = $this->reviewQueue->interestedCandidateIds($offer);
        $queue = $this->reviewQueue->pending($offer, $this->reviewedCandidateIds($offer), $interestedIds);
        $saved = $this->reviewQueue->saved($offer, $company);

        $broughtBack = $request->filled('candidate') ? $saved->firstWhere('candidate.id', $request->integer('candidate')) : null;
        $current = $broughtBack ?? $queue->first();

        return response()->json([
            'data' => [
                'offer' => [
                    'id' => $offer->id,
                    'title' => $offer->title,
                    'city' => $offer->city,
                    'start_date' => $offer->start_date->toDateString(),
                    'salary_min' => $offer->salary_min,
                    'salary_max' => $offer->salary_max,
                    'employment_fraction_label' => $offer->employment_fraction->label(),
                    'work_mode_label' => $offer->work_mode->label(),
                    'flexible_hours' => $offer->flexible_hours,
                    'fixed_meeting_hours' => $offer->fixed_meeting_hours,
                    'is_job_share' => $offer->is_job_share,
                    'submitted_pairs_count' => $offer->is_job_share
                        ? $offer->jobSharePairs()->where('status', JobSharePairStatus::Submitted)->visibleToCompany($company)->count()
                        : 0,
                ],
                'candidate' => $current
                    ? (new EmployerCandidateCardResource($current['candidate'], $current['match'], in_array($current['candidate']->id, $interestedIds, true)))->resolve($request)
                    : null,
                'is_saved_candidate' => $broughtBack !== null,
                'remaining_count' => $queue->count(),
                'saved' => $saved->map(fn (array $row): array => (new EmployerSavedCandidateResource($row['candidate'], $row['match']))->resolve($request))->values(),
                'invitation_stats' => [
                    'invited' => $offer->invitations->count(),
                    'responded' => $offer->invitations->whereNotNull('responded_at')->count(),
                ],
                'statistics' => $this->offerStatistics($offer, $scorer),
            ],
        ]);
    }

    /**
     * Full anonymous cards of candidates saved for later, best match first.
     */
    public function saved(Request $request, JobOffer $offer): AnonymousResourceCollection
    {
        Gate::authorize('reviewCandidates', $offer);

        $interestedIds = $this->reviewQueue->interestedCandidateIds($offer);

        return EmployerCandidateCardResource::collection(
            $this->reviewQueue->saved($offer, $this->currentCompany($request))
                ->map(fn (array $row): EmployerCandidateCardResource => $this->card($row['candidate'], $row['match'], $interestedIds)),
        );
    }

    /**
     * Skip a candidate or save her for later (always 200, the decision is an upsert); an existing invitation is never downgraded.
     */
    public function decide(StoreCandidateDecisionRequest $request, JobOffer $offer, CandidateProfile $candidate, RecordCandidateDecision $recordCandidateDecision): JsonResponse
    {
        Gate::authorize('reviewCandidates', $offer);

        $decision = $recordCandidateDecision->handle($offer, $candidate, CandidateDecisionType::from($request->validated('decision')));

        return (new EmployerCandidateDecisionResource($decision))->response()->setStatusCode(200);
    }

    /**
     * @param  list<int>  $interestedIds
     */
    private function card(CandidateProfile $candidate, MatchResult $match, array $interestedIds): EmployerCandidateCardResource
    {
        return new EmployerCandidateCardResource($candidate, $match, in_array($candidate->id, $interestedIds, true));
    }
}
