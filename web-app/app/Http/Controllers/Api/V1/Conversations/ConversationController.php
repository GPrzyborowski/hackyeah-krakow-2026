<?php

namespace App\Http\Controllers\Api\V1\Conversations;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ConversationDetailResource;
use App\Http\Resources\Api\V1\ConversationResource;
use App\Models\Conversation;
use App\Services\Conversations\ConversationInbox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Conversations opened by accepted invitations, for the candidate and for every member of the inviting company.
 */
class ConversationController extends Controller
{
    private const int PER_PAGE = 20;

    public function __construct(private readonly ConversationInbox $inbox) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return ConversationResource::collection(
            $this->inbox->conversationsFor($request->user())->paginate(self::PER_PAGE),
        );
    }

    /**
     * Conversation header; messages come from the messages endpoint.
     */
    public function show(Conversation $conversation): ConversationDetailResource
    {
        Gate::authorize('view', $conversation);

        $conversation->load(['invitation.jobOffer.company.approvedReviews', 'invitation.candidateProfile.user', 'invitation.jobSharePair.members.user']);

        return new ConversationDetailResource($conversation);
    }
}
