<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CompanyInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A pending invitation to the company team; the secret token is never exposed (it only travels in the e-mail).
 *
 * @property CompanyInvitation $resource
 */
class CompanyInvitationResource extends JsonResource
{
    /**
     * @return array{id: int, email: string, invited_by: string|null, created_at: string, expires_at: string, is_expired: bool}
     */
    public function toArray(Request $request): array
    {
        $invitation = $this->resource;

        return [
            'id' => $invitation->id,
            'email' => $invitation->email,
            'invited_by' => $invitation->invitedBy?->name,
            'created_at' => $invitation->created_at->toIso8601String(),
            'expires_at' => $invitation->expires_at->toIso8601String(),
            'is_expired' => $invitation->isExpired(),
        ];
    }
}
