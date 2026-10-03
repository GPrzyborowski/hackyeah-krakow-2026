<?php

namespace App\Http\Controllers\Api\V1\Employer;

use App\Actions\Employer\InviteJobSharePair;
use App\Enums\JobSharePairStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Employer\Concerns\InteractsWithEmployerCompany;
use App\Http\Requests\Employer\StoreInvitationRequest;
use App\Http\Resources\Api\V1\EmployerPairResource;
use App\Models\JobOffer;
use App\Models\JobSharePair;
use App\Services\Employer\JobSharePairReview;
use App\Services\JobSharing\Workday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Pairs that applied together for a job-sharing offer, shown anonymously, and the employer's decision on them.
 */
class JobSharePairController extends Controller
{
    use InteractsWithEmployerCompany;

    public function __construct(private readonly JobSharePairReview $pairReview) {}

    public function index(Request $request, JobOffer $offer): AnonymousResourceCollection
    {
        Gate::authorize('reviewCandidates', $offer);
        abort_unless($offer->is_job_share, 404);

        $workday = Workday::forOffer($offer);

        return EmployerPairResource::collection($this->pairReview->rows($offer, $this->currentCompany($request)))
            ->additional(['offer' => [
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
                'workday_starts_at' => Workday::format($workday->startsAt),
                'workday_ends_at' => Workday::format($workday->endsAt),
            ]]);
    }

    /**
     * Invite both members of a submitted pair; each of them answers her own invitation.
     */
    public function invite(StoreInvitationRequest $request, JobSharePair $pair, InviteJobSharePair $inviteJobSharePair): JsonResponse
    {
        Gate::authorize('review', $pair);
        abort_unless($this->pairReview->isVisibleTo($pair, $this->currentCompany($request)), 404);

        $inviteJobSharePair->handle($pair, $request->user(), $request->validated('message'));

        return (new EmployerPairResource($this->pairReview->row($pair)))->response()->setStatusCode(201);
    }

    /**
     * Mark a pair as hired once both members accepted the invitation; 403 in any other status.
     */
    public function hire(Request $request, JobSharePair $pair): EmployerPairResource
    {
        Gate::authorize('hire', $pair);
        abort_unless($this->pairReview->isVisibleTo($pair, $this->currentCompany($request)), 404);

        $pair->update(['status' => JobSharePairStatus::Hired]);

        return new EmployerPairResource($this->pairReview->row($pair));
    }

    public function reject(Request $request, JobSharePair $pair): EmployerPairResource
    {
        Gate::authorize('review', $pair);
        abort_unless($this->pairReview->isVisibleTo($pair, $this->currentCompany($request)), 404);

        $pair->update(['status' => JobSharePairStatus::Rejected]);

        return new EmployerPairResource($this->pairReview->row($pair));
    }
}
