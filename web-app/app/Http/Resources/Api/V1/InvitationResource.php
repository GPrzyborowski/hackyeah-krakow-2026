<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\InvitationStatus;
use App\Models\CandidateProfile;
use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An invitation as the invited candidate sees it. The job-sharing partner is shown only by her anonymous name.
 *
 * @property Invitation $resource
 */
class InvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $invitation = $this->resource;
        $invitation->loadMissing(['jobOffer.company.approvedReviews', 'conversation', 'jobSharePair.members.user', 'jobSharePair.conversation']);
        $offer = $invitation->jobOffer;
        $company = $offer->company;

        return [
            'id' => $invitation->id,
            'status' => $invitation->status->value,
            'status_label' => $this->statusLabel($invitation->status),
            'kind' => $invitation->kind->value,
            'kind_label' => $invitation->kind->label(),
            'message' => $invitation->message,
            'created_at' => $invitation->created_at->toIso8601String(),
            'responded_at' => $invitation->responded_at?->toIso8601String(),
            'conversation_id' => $invitation->conversation?->id,
            'job_share_pair' => $invitation->jobSharePair ? [
                'id' => $invitation->jobSharePair->id,
                'status' => $invitation->jobSharePair->status->value,
                'status_label' => $invitation->jobSharePair->status->label(),
                'partner_name' => $invitation->jobSharePair->members
                    ->first(fn (CandidateProfile $member): bool => $member->id !== $invitation->candidate_profile_id)
                    ?->anonymousName(),
                'team_conversation_id' => $invitation->status === InvitationStatus::Accepted ? $invitation->jobSharePair->conversation?->id : null,
            ] : null,
            'offer' => [
                'id' => $offer->id,
                'title' => $offer->title,
                'city' => $offer->city,
                'work_mode' => $offer->work_mode->value,
                'work_mode_label' => $offer->work_mode->label(),
                'employment_fraction' => $offer->employment_fraction->value,
                'employment_fraction_label' => $offer->employment_fraction->label(),
                'is_published' => $offer->isPublished(),
            ],
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'verified' => $company->isVerified(),
                'average_rating' => $company->averageRating(),
                'reviews_count' => $company->approvedReviews->count(),
            ],
        ];
    }

    private function statusLabel(InvitationStatus $status): string
    {
        return match ($status) {
            InvitationStatus::Pending => 'Oczekuje na odpowiedź',
            InvitationStatus::Accepted => 'Przyjęte',
            InvitationStatus::Declined => 'Odrzucone',
            InvitationStatus::Withdrawn => 'Wycofane',
        };
    }
}
