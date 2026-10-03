<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CompanyReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in candidate's own review with its moderation status. Expects `company` to be loaded.
 *
 * @property CompanyReview $resource
 */
class CompanyReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $review = $this->resource;

        return [
            'id' => $review->id,
            'company' => ['id' => $review->company->id, 'name' => $review->company->name],
            'rating_return' => $review->rating_return,
            'rating_flexibility' => $review->rating_flexibility,
            'rating_no_pregnancy_questions' => $review->rating_no_pregnancy_questions,
            'quote' => $review->quote,
            'author_label' => $review->author_label,
            'status' => $review->status->value,
            'status_label' => $review->status->label(),
            'can_edit' => $request->user()?->can('update', $review) ?? false,
            'created_at' => $review->created_at?->toIso8601String(),
        ];
    }
}
