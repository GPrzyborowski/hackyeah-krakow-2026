<?php

namespace App\Http\Resources;

use App\Models\CandidateProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Contact data shown to a company only after the candidate accepted its invitation.
 *
 * @mixin CandidateProfile
 */
class RevealedCandidateResource extends JsonResource
{
    /**
     * @return array{id: int, anonymous_name: string, full_name: string, email: string, headline: string|null, years_of_experience: int|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'anonymous_name' => $this->resource->anonymousName(),
            'full_name' => $this->resource->user->name,
            'email' => $this->resource->user->email,
            'headline' => $this->resource->headline,
            'years_of_experience' => $this->resource->years_of_experience,
        ];
    }
}
