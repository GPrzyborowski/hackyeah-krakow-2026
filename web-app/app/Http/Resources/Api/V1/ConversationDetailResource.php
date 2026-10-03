<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Conversation;
use App\Services\Conversations\ConversationInbox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Conversation header: the company for a candidate; the revealed candidate (name, e-mail, phone, photo URL; invitation accepted)
 * for the employer; plus the anonymous job-sharing partner when the invitation was for a pair. A pair's team chat has
 * `counterpart.type: "team"` with the company and the pair members (see ConversationInbox::teamMembers()).
 *
 * @property Conversation $resource
 */
class ConversationDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $conversation = $this->resource;
        $inbox = app(ConversationInbox::class);
        $user = $request->user();
        $offer = $conversation->jobOffer();

        return [
            'id' => $conversation->id,
            'offer' => ['id' => $offer->id, 'title' => $offer->title],
            'is_team_chat' => $conversation->isTeamChat(),
            'counterpart' => $inbox->counterpart($conversation, $user, forApi: true),
            'pair_partner_name' => $inbox->pairPartnerName($conversation),
            'viewer_role' => $user->role->value,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
        ];
    }
}
