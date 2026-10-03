<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\OfferStatus;
use App\Http\Resources\EmployerJobOfferResource;
use App\Models\JobOffer;
use Illuminate\Http\Request;

/**
 * A job offer as its own company sees it in the mobile app: the web panel shape plus funnel statistics.
 *
 * @property JobOffer $resource
 */
class EmployerOfferResource extends EmployerJobOfferResource
{
    /**
     * @param  array{matched_count: int, to_review_count: int, invited_count: int, responded_count: int, accepted_count: int}  $statistics
     */
    public function __construct(JobOffer $resource, public array $statistics, public int $submittedPairsCount, public bool $isParentFriendly)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $offer = $this->resource;

        return [
            ...parent::toArray($request),
            'status_label' => match ($offer->status) {
                OfferStatus::Draft => 'Szkic',
                OfferStatus::Published => 'Opublikowana',
                OfferStatus::Closed => 'Zamknięta',
            },
            'is_parent_friendly' => $this->isParentFriendly,
            'statistics' => $this->statistics,
            'submitted_pairs_count' => $this->submittedPairsCount,
            'created_at' => $offer->created_at?->toIso8601String(),
            'updated_at' => $offer->updated_at?->toIso8601String(),
        ];
    }
}
