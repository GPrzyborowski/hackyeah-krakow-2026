<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in user. Only ever returned to the user herself.
 *
 * @property User $resource
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->email_verified_at !== null,
            'role' => $user->role->value,
            'company' => $user->company ? ['id' => $user->company->id, 'name' => $user->company->name] : null,
            'candidate_profile' => $user->isCandidate() && $user->candidateProfile ? [
                'id' => $user->candidateProfile->id,
                'published' => $user->candidateProfile->isPublished(),
                'onboarding_step' => $user->candidateProfile->onboarding_step,
            ] : null,
        ];
    }
}
