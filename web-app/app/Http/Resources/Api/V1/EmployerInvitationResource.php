<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\InvitationStatus;
use App\Http\Resources\RevealedCandidateResource;
use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An invitation sent by the company. Contact data (full name, email) and the conversation appear only once accepted.
 *
 * @property Invitation $resource
 */
class EmployerInvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $invitation = $this->resource;
        $candidate = $invitation->candidateProfile;
        $isAccepted = $invitation->status === InvitationStatus::Accepted;

        return [
            'id' => $invitation->id,
            'status' => $invitation->status->value,
            'status_label' => match ($invitation->status) {
                InvitationStatus::Pending => 'Czeka na odpowiedź',
                InvitationStatus::Accepted => 'Zaakceptowane',
                InvitationStatus::Declined => 'Odrzucone',
                InvitationStatus::Withdrawn => 'Wycofane',
            },
            'kind' => $invitation->kind->value,
            'kind_label' => $invitation->kind->label(),
            'message' => $invitation->message,
            'created_at' => $invitation->created_at->toIso8601String(),
            'responded_at' => $invitation->responded_at?->toIso8601String(),
            'job_share_pair_id' => $invitation->job_share_pair_id,
            'offer' => ['id' => $invitation->jobOffer->id, 'title' => $invitation->jobOffer->title],
            'candidate' => $isAccepted
                ? (new RevealedCandidateResource($candidate))->resolve($request)
                : ['id' => $candidate->id, 'anonymous_name' => $candidate->anonymousName()],
            'conversation_id' => $isAccepted ? $invitation->conversation?->id : null,
        ];
    }
}
