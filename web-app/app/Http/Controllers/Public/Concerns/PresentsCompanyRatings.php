<?php

namespace App\Http\Controllers\Public\Concerns;

use App\Models\Company;
use App\Models\CompanyReview;
use Illuminate\Support\Collection;

trait PresentsCompanyRatings
{
    /**
     * Summarise a company's approved reviews (expects the `approvedReviews` relation to be loaded).
     *
     * @return array{overall: float|null, count: int, categories: array{return: float|null, flexibility: float|null, no_pregnancy_questions: float|null}}
     */
    protected function ratingSummary(Company $company): array
    {
        /** @var Collection<int, CompanyReview> $reviews */
        $reviews = $company->approvedReviews;

        $average = fn (string $column): ?float => $reviews->isEmpty() ? null : round((float) $reviews->avg($column), 1);

        return [
            'overall' => $company->averageRating(),
            'count' => $reviews->count(),
            'categories' => [
                'return' => $average('rating_return'),
                'flexibility' => $average('rating_flexibility'),
                'no_pregnancy_questions' => $average('rating_no_pregnancy_questions'),
            ],
        ];
    }

    /**
     * The first approved review that carries a quote, shaped for display.
     *
     * @return array{quote: string, author_label: string|null}|null
     */
    protected function featuredQuote(Company $company): ?array
    {
        $review = $company->approvedReviews->first(fn (CompanyReview $review): bool => filled($review->quote));

        return $review === null ? null : [
            'quote' => $review->quote,
            'author_label' => $review->author_label,
        ];
    }
}
