<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Employer\Concerns\InteractsWithEmployerCompany;
use App\Http\Requests\Employer\UpdateCompanyRequest;
use App\Models\CompanyReview;
use App\Services\Employer\CompanyRatingSummary;
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
    public function edit(Request $request, CompanyRatingSummary $ratingSummary): Response
    {
        $company = $this->currentCompany($request);
        $reviews = $ratingSummary->reviews($company);

        return Inertia::render('employer/company/Edit', [
            'company' => $company->only(['id', 'name', 'nip', 'city', 'description']),
            'ratings' => $ratingSummary->ratings($company, $reviews),
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
