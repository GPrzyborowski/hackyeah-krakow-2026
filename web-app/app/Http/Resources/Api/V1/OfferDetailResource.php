<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CompanyReview;
use App\Models\JobOffer;
use App\Services\Matching\MatchResult;
use Illuminate\Http\Request;

/**
 * A single published offer for the candidate: the card data plus description, job-sharing panel and company reviews.
 *
 * @property JobOffer $resource
 */
class OfferDetailResource extends OfferResource
{
    /**
     * @param  array<string, mixed>|null  $jobSharing  Output of OfferJobSharePanel::present()
     */
    public function __construct(JobOffer $resource, MatchResult $match, bool $isInterested, bool $isSaved, public ?array $jobSharing = null)
    {
        parent::__construct($resource, $match, $isInterested, $isSaved);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $offer = $this->resource;

        return [
            ...parent::toArray($request),
            'description' => $offer->description,
            'company_description' => $offer->company->description,
            'job_sharing' => $this->jobSharing,
            'reviews' => array_values($offer->company->approvedReviews
                ->sortByDesc('created_at')
                ->map(fn (CompanyReview $review): array => [
                    'id' => $review->id,
                    'quote' => $review->quote,
                    'author_label' => $review->author_label,
                    'rating' => round($review->overallRating(), 1),
                    'rating_return' => $review->rating_return,
                    'rating_flexibility' => $review->rating_flexibility,
                    'rating_no_pregnancy_questions' => $review->rating_no_pregnancy_questions,
                    'created_at' => $review->created_at?->toIso8601String(),
                ])
                ->all()),
        ];
    }
}
