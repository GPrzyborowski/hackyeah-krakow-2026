<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\UserRole;
use App\Models\Message;
use App\Services\Conversations\ConversationInbox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Conversation message. `is_mine` is true for the viewer's side: her own messages for a candidate,
 * any company member's messages for an employer. Expects `author` (id, name, role) and `author.candidateProfile` to be loaded;
 * in a team chat a candidate sees her partner's name only anonymously.
 *
 * @property Message $resource
 */
class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $message = $this->resource;
        $viewer = $request->user();
        $authorIsCandidate = $message->author->role === UserRole::Candidate;

        return [
            'id' => $message->id,
            'body' => $message->body,
            'author_name' => app(ConversationInbox::class)->authorName($message, $viewer),
            'author_side' => $authorIsCandidate ? 'candidate' : 'company',
            'is_mine' => $viewer->isCandidate() ? $message->user_id === $viewer->id : ! $authorIsCandidate,
            'read_at' => $message->read_at?->toIso8601String(),
            'created_at' => $message->created_at->toIso8601String(),
        ];
    }
}
