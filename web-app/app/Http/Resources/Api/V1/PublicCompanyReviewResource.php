<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CompanyReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An approved review as shown publicly: anonymous (no author id, only the optional self-chosen label).
 *
 * @property CompanyReview $resource
 */
class PublicCompanyReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $review = $this->resource;

        return [
            'id' => $review->id,
            'rating_return' => $review->rating_return,
            'rating_flexibility' => $review->rating_flexibility,
            'rating_no_pregnancy_questions' => $review->rating_no_pregnancy_questions,
            'overall' => round($review->overallRating(), 1),
            'quote' => $review->quote,
            'author_label' => $review->author_label,
        ];
    }
}
