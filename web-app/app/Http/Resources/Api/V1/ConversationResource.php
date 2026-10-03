<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\Conversations\ConversationInbox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Conversation list item for the signed-in participant (loaded via ConversationInbox::conversationsFor()).
 *
 * @property Conversation $resource
 */
class ConversationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $conversation = $this->resource;
        $offer = $conversation->invitation->jobOffer;

        /** @var Message|null $lastMessage */
        $lastMessage = $conversation->messages->first();
        $unreadCount = (int) $conversation->unread_count;

        return [
            'id' => $conversation->id,
            'counterpart_name' => app(ConversationInbox::class)->counterpartName($conversation, $request->user()),
            'offer' => ['id' => $offer->id, 'title' => $offer->title],
            'last_message' => $lastMessage ? [
                'id' => $lastMessage->id,
                'excerpt' => Str::limit($lastMessage->body, 90),
                'created_at' => $lastMessage->created_at->toIso8601String(),
            ] : null,
            'last_message_at' => ($lastMessage->created_at ?? $conversation->last_message_at)?->toIso8601String(),
            'unread_count' => $unreadCount,
            'has_unread' => $unreadCount > 0,
        ];
    }
}
