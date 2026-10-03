<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Company;
use App\Models\CompanyReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * The employer's own company profile with approved parent reviews and rating averages.
 *
 * @property Company $resource
 */
class CompanyResource extends JsonResource
{
    /**
     * @param  array{count: int, overall: float|null, return: float|null, flexibility: float|null, no_pregnancy_questions: float|null}  $ratings
     * @param  Collection<int, CompanyReview>  $reviews
     */
    public function __construct(Company $resource, public array $ratings, public Collection $reviews)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $company = $this->resource;

        return [
            'id' => $company->id,
            'name' => $company->name,
            'nip' => $company->nip,
            'city' => $company->city,
            'description' => $company->description,
            'ratings' => $this->ratings,
            'reviews' => $this->reviews->map(fn (CompanyReview $review): array => [
                'id' => $review->id,
                'quote' => $review->quote,
                'author_label' => $review->author_label,
                'rating_return' => $review->rating_return,
                'rating_flexibility' => $review->rating_flexibility,
                'rating_no_pregnancy_questions' => $review->rating_no_pregnancy_questions,
                'overall' => round($review->overallRating(), 1),
                'created_at' => $review->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
