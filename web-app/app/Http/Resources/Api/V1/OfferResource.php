<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Controllers\Candidate\Concerns\PresentsOffers;
use App\Models\JobOffer;
use App\Services\Matching\MatchResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A published offer as a candidate sees it on an offer card: match, salary, chips, company rating and first quote.
 * Same shape as the web candidate offer card.
 *
 * @property JobOffer $resource
 */
class OfferResource extends JsonResource
{
    use PresentsOffers;

    public function __construct(JobOffer $resource, public MatchResult $match, public bool $isInterested = false, public bool $isSaved = false)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->presentOffer(
            $this->resource,
            $this->match,
            $this->isInterested ? [$this->resource->id] : [],
            $this->isSaved ? [$this->resource->id] : [],
        );
    }
}
