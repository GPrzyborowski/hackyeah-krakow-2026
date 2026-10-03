<?php

namespace App\Http\Controllers\Api\V1\Employer;

use App\Actions\Employer\InviteCandidate;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Employer\Concerns\InteractsWithEmployerCompany;
use App\Http\Requests\Api\V1\Employer\IndexInvitationsRequest;
use App\Http\Requests\Employer\StoreInvitationRequest;
use App\Http\Resources\Api\V1\EmployerInvitationResource;
use App\Models\CandidateProfile;
use App\Models\JobOffer;
use App\Services\Employer\CompanyInvitations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Invitations sent by the company; contact data is revealed only for accepted ones.
 */
class InvitationController extends Controller
{
    use InteractsWithEmployerCompany;

    public function index(IndexInvitationsRequest $request, CompanyInvitations $companyInvitations): AnonymousResourceCollection
    {
        $status = $request->status();

        $invitations = $companyInvitations->query($this->currentCompany($request))
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->latest()
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return EmployerInvitationResource::collection($invitations);
    }

    /**
     * Invite a matched candidate; the message is moderated (422 with `message` and `message_suggestion` when blocked).
     */
    public function store(StoreInvitationRequest $request, JobOffer $offer, CandidateProfile $candidate, InviteCandidate $inviteCandidate): JsonResponse
    {
        Gate::authorize('reviewCandidates', $offer);

        $invitation = $inviteCandidate->handle($offer, $candidate, $request->user(), $request->validated('message'));
        $invitation->load(['jobOffer', 'candidateProfile.user', 'conversation']);

        return (new EmployerInvitationResource($invitation))->response()->setStatusCode(201);
    }
}
