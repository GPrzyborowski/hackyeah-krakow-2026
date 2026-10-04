<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\PresentsCompanyRatings;
use App\Models\Company;
use App\Models\CompanyReview;
use App\Models\JobOffer;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    use PresentsCompanyRatings;

    /**
     * Public company profile: description, approved reviews and published offers.
     */
    public function show(Company $company): Response
    {
        $company->load([
            'approvedReviews' => fn ($query) => $query->latest()->latest('id'),
            'jobOffers' => fn ($query) => $query->published()->latest('published_at')->latest('id'),
        ]);

        return Inertia::render('public/companies/Show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'city' => $company->city,
                'verified' => $company->isVerified(),
                'description' => $company->description,
                'rating' => $this->ratingSummary($company),
            ],
            'reviews' => $company->approvedReviews->map(fn (CompanyReview $review): array => [
                'id' => $review->id,
                'rating_return' => $review->rating_return,
                'rating_flexibility' => $review->rating_flexibility,
                'rating_no_pregnancy_questions' => $review->rating_no_pregnancy_questions,
                'overall' => round($review->overallRating(), 1),
                'quote' => $review->quote,
                'author_label' => $review->author_label,
            ]),
            'offers' => $company->jobOffers->map(fn (JobOffer $offer): array => [
                'id' => $offer->id,
                'title' => $offer->title,
                'category' => $offer->category->value,
                'category_label' => $offer->category->label(),
                'city' => $offer->city,
                'work_mode_label' => $offer->work_mode->label(),
                'employment_fraction_label' => $offer->employment_fraction->label(),
                'contract_types' => $offer->contractTypeValues(),
                'contract_type_labels' => $offer->contractTypeLabels(),
                'salary_min' => $offer->salary_min,
                'salary_max' => $offer->salary_max,
                'start_date' => $offer->start_date->toDateString(),
                'flexible_hours' => $offer->flexible_hours,
                'fixed_meeting_hours' => $offer->fixed_meeting_hours,
                'childcare_subsidy' => $offer->childcare_subsidy,
            ]),
        ]);
    }
}
