<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Employer\Concerns\InteractsWithEmployerCompany;
use App\Http\Requests\Employer\UpdateCompanyRequest;
use App\Models\CompanyReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    use InteractsWithEmployerCompany;

    /**
     * Company profile form with approved parent reviews.
     */
    public function edit(Request $request): Response
    {
        $company = $this->currentCompany($request);
        $reviews = $company->approvedReviews()->latest()->get();

        $averageOf = fn (string $column): ?float => $reviews->isEmpty() ? null : round((float) $reviews->avg($column), 1);

        return Inertia::render('employer/company/Edit', [
            'company' => $company->only(['id', 'name', 'nip', 'city', 'description']),
            'ratings' => [
                'count' => $reviews->count(),
                'overall' => $company->averageRating(),
                'return' => $averageOf('rating_return'),
                'flexibility' => $averageOf('rating_flexibility'),
                'no_pregnancy_questions' => $averageOf('rating_no_pregnancy_questions'),
            ],
            'reviews' => $reviews->map(fn (CompanyReview $review): array => [
                'id' => $review->id,
                'quote' => $review->quote,
                'author_label' => $review->author_label,
                'rating_return' => $review->rating_return,
                'rating_flexibility' => $review->rating_flexibility,
                'rating_no_pregnancy_questions' => $review->rating_no_pregnancy_questions,
                'overall' => round($review->overallRating(), 1),
            ])->values(),
        ]);
    }

    public function update(UpdateCompanyRequest $request): RedirectResponse
    {
        $this->currentCompany($request)->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dane firmy zapisane.']);

        return to_route('employer.company.edit');
    }
}
