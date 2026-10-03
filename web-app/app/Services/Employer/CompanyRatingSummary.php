<?php

namespace App\Services\Employer;

use App\Models\Company;
use App\Models\CompanyReview;
use Illuminate\Database\Eloquent\Collection;

/**
 * Approved parent reviews of a company with per-category averages.
 */
class CompanyRatingSummary
{
    /**
     * @return Collection<int, CompanyReview>
     */
    public function reviews(Company $company): Collection
    {
        return $company->approvedReviews()->latest()->get();
    }

    /**
     * @param  Collection<int, CompanyReview>  $reviews
     * @return array{count: int, overall: float|null, return: float|null, flexibility: float|null, no_pregnancy_questions: float|null}
     */
    public function ratings(Company $company, Collection $reviews): array
    {
        $averageOf = fn (string $column): ?float => $reviews->isEmpty() ? null : round((float) $reviews->avg($column), 1);

        return [
            'count' => $reviews->count(),
            'overall' => $company->averageRating(),
            'return' => $averageOf('rating_return'),
            'flexibility' => $averageOf('rating_flexibility'),
            'no_pregnancy_questions' => $averageOf('rating_no_pregnancy_questions'),
        ];
    }
}
