<?php

namespace App\Http\Resources\Api\V1;

use App\Models\JobOffer;
use App\Services\Offers\PublicOfferPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public offer detail: the card fields plus description, skills and the company's rating summary.
 * Expects `skills` and `company.approvedReviews` to be loaded.
 *
 * @property JobOffer $resource
 */
class PublicOfferDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return app(PublicOfferPresenter::class)->detail($this->resource);
    }
}
