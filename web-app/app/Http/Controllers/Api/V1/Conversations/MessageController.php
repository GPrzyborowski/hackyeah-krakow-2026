<?php

namespace App\Http\Controllers\Api\V1\Conversations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PollRequest;
use App\Http\Requests\Conversations\StoreMessageRequest;
use App\Http\Resources\Api\V1\MessageResource;
use App\Models\Conversation;
use App\Services\Conversations\ConversationInbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class MessageController extends Controller
{
    private const int PER_PAGE = 20;

    public function __construct(private readonly ConversationInbox $inbox) {}

    /**
     * Messages newest first (poll with `since` / `after_id`); reading marks the counterpart's messages as read.
     */
    public function index(PollRequest $request, Conversation $conversation): AnonymousResourceCollection
    {
        Gate::authorize('view', $conversation);

        $this->inbox->markCounterpartMessagesRead($conversation, $request->user());

        $messages = $request->applyTo($conversation->messages()->getQuery())
            ->with(['author:id,name,role', 'author.candidateProfile'])
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return MessageResource::collection($messages);
    }

    /**
     * Post a message; employer messages are moderated (422 with `body` and `body_suggestion`).
     */
    public function store(StoreMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $message = $this->inbox->post($conversation, $request->user(), $request->validated('body'));
        $message->load(['author:id,name,role', 'author.candidateProfile']);

        return (new MessageResource($message))->response()->setStatusCode(201);
    }
}
