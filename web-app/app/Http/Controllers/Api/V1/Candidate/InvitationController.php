<?php

namespace App\Http\Controllers\Api\V1\Candidate;

use App\Enums\InvitationStatus;
use App\Http\Controllers\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\InvitationResource;
use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class InvitationController extends Controller
{
    use ResolvesCandidateProfile;

    /**
     * Invitations received from companies, pending ones first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $invitations = $this->candidateProfile($request)->invitations()
            ->with(['jobOffer.company.approvedReviews', 'conversation', 'jobSharePair.members.user'])
            ->orderByRaw('case when status = ? then 0 else 1 end', [InvitationStatus::Pending->value])
            ->latest()
            ->paginate(20);

        return InvitationResource::collection($invitations);
    }

    /**
     * Accept: opens the conversation (its id is in conversation_id) and reveals the candidate's name and e-mail to the company.
     */
    public function accept(Invitation $invitation): InvitationResource
    {
        Gate::authorize('respond', $invitation);

        $invitation->accept();

        return new InvitationResource($invitation->load('conversation'));
    }

    /**
     * Decline: the company is informed, the candidate stays anonymous.
     */
    public function decline(Invitation $invitation): InvitationResource
    {
        Gate::authorize('respond', $invitation);

        $invitation->decline();

        return new InvitationResource($invitation);
    }
}
