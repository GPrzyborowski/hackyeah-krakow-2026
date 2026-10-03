<?php

namespace App\Http\Controllers\Conversations;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Conversations\ConversationInbox;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function __construct(private readonly ConversationInbox $inbox) {}

    /**
     * Conversations of the signed-in candidate or of the employer's company.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $conversations = $this->inbox->conversationsFor($user)->get();

        return Inertia::render('conversations/Index', [
            'conversations' => $conversations->map(function (Conversation $conversation) use ($user): array {
                /** @var Message|null $lastMessage */
                $lastMessage = $conversation->messages->first();

                return [
                    'id' => $conversation->id,
                    'counterpart_name' => $this->inbox->counterpartName($conversation, $user),
                    'offer_title' => $conversation->invitation->jobOffer->title,
                    'last_message' => $lastMessage ? Str::limit($lastMessage->body, 90) : null,
                    'last_message_at' => ($lastMessage->created_at ?? $conversation->last_message_at)?->toIso8601String(),
                    'has_unread' => $conversation->unread_count > 0,
                ];
            })->values(),
        ]);
    }

    /**
     * The thread; opening it marks the counterpart's messages as read.
     */
    public function show(Request $request, Conversation $conversation): Response
    {
        Gate::authorize('view', $conversation);

        $user = $request->user();
        $conversation->load(['invitation.jobOffer.company', 'invitation.candidateProfile.user', 'invitation.jobSharePair.members.user']);
        $candidateUserId = $conversation->invitation->candidateProfile->user_id;

        $this->inbox->markCounterpartMessagesRead($conversation, $user);

        $messages = $conversation->messages()->with('author:id,name')->oldest('id')->get();

        return Inertia::render('conversations/Show', [
            'conversation' => [
                'id' => $conversation->id,
                'offer_title' => $conversation->invitation->jobOffer->title,
                'counterpart' => $this->inbox->counterpart($conversation, $user),
                'pair_partner_name' => $this->inbox->pairPartnerName($conversation),
            ],
            'viewerRole' => $user->role,
            'messages' => $messages->map(fn (Message $message): array => [
                'id' => $message->id,
                'body' => $message->body,
                'author_name' => $message->author->name,
                'is_mine' => $this->inbox->isOwnSide($message, $user, $candidateUserId),
                'created_at' => $message->created_at->toIso8601String(),
            ])->values(),
        ]);
    }
}
