<?php

namespace App\Http\Resources\Api\V1;

use App\Models\JobOffer;
use App\Services\Offers\PublicOfferPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Offer card in the public (guest) offers list: no match score, no candidate data.
 * Expects `company.approvedReviews` to be loaded.
 *
 * @property JobOffer $resource
 */
class PublicOfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return app(PublicOfferPresenter::class)->card($this->resource);
    }
}
