<?php

namespace App\Http\Resources;

use App\Models\CandidateProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Contact data (full name, e-mail, phone, photo) shown to a company only after the candidate accepted its invitation.
 * photo_url points to the authorised photo endpoint: the API one for api/* requests, the web one otherwise.
 *
 * @mixin CandidateProfile
 */
class RevealedCandidateResource extends JsonResource
{
    /**
     * @return array{id: int, anonymous_name: string, full_name: string, email: string, phone: string|null, photo_url: string|null, headline: string|null, years_of_experience: int|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'anonymous_name' => $this->resource->anonymousName(),
            'full_name' => $this->resource->user->name,
            'email' => $this->resource->user->email,
            'phone' => $this->resource->phone,
            'photo_url' => $this->resource->photoUrl($request->is('api/*')),
            'headline' => $this->resource->headline,
            'years_of_experience' => $this->resource->years_of_experience,
        ];
    }
}
