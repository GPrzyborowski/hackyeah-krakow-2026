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
                    'is_team_chat' => $conversation->isTeamChat(),
                    'offer_title' => $conversation->jobOffer()->title,
                    'last_message' => $lastMessage ? Str::limit($lastMessage->body, 90) : null,
                    'last_message_at' => ($lastMessage->created_at ?? $conversation->last_message_at)?->toIso8601String(),
                    'has_unread' => $conversation->unread_count > 0,
                ];
            })->values(),
        ]);
    }

    /**
     * The thread (a 1:1 chat or a job-sharing pair's team chat); opening it marks the counterpart's messages as read.
     */
    public function show(Request $request, Conversation $conversation): Response
    {
        Gate::authorize('view', $conversation);

        $user = $request->user();
        $conversation->load([...ConversationInbox::PRESENTATION_RELATIONS, 'invitation.jobSharePair.members.user']);

        $this->inbox->markCounterpartMessagesRead($conversation, $user);

        $messages = $conversation->messages()->with(['author:id,name,role', 'author.candidateProfile'])->oldest('id')->get();

        return Inertia::render('conversations/Show', [
            'conversation' => [
                'id' => $conversation->id,
                'offer_title' => $conversation->jobOffer()->title,
                'is_team_chat' => $conversation->isTeamChat(),
                'counterpart' => $this->inbox->counterpart($conversation, $user),
                'pair_partner_name' => $this->inbox->pairPartnerName($conversation),
            ],
            'viewerRole' => $user->role,
            'messages' => $messages->map(fn (Message $message): array => [
                'id' => $message->id,
                'body' => $message->body,
                'author_name' => $this->inbox->authorName($message, $user),
                'is_mine' => $this->inbox->isOwnSide($message, $user),
                'created_at' => $message->created_at->toIso8601String(),
            ])->values(),
        ]);
    }
}
