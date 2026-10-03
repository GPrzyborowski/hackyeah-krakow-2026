<?php

namespace App\Http\Controllers\Public;

use App\Enums\EmploymentFraction;
use App\Enums\WorkMode;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\PresentsCompanyRatings;
use App\Models\JobOffer;
use App\Services\JobSharing\Workday;
use App\Services\Offers\PublicOfferSearch;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OfferController extends Controller
{
    use PresentsCompanyRatings;

    /**
     * Public, match-free list of published offers with simple query-string filters.
     */
    public function index(Request $request, PublicOfferSearch $search): Response
    {
        $filters = $search->filters($request);

        $offers = $search->query($filters)
            ->paginate(10)
            ->withQueryString()
            ->through(fn (JobOffer $offer): array => $this->present($offer));

        return Inertia::render('public/offers/Index', [
            'offers' => $offers,
            'filters' => $filters,
            'workModes' => collect(WorkMode::cases())->map(fn (WorkMode $mode): array => ['value' => $mode->value, 'label' => $mode->label()]),
            'fractions' => collect(EmploymentFraction::cases())->map(fn (EmploymentFraction $fraction): array => ['value' => $fraction->value, 'label' => $fraction->label()]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(JobOffer $offer): array
    {
        $company = $offer->company;

        return [
            'id' => $offer->id,
            'title' => $offer->title,
            'city' => $offer->city,
            'work_mode' => $offer->work_mode->value,
            'work_mode_label' => $offer->work_mode->label(),
            'employment_fraction_label' => $offer->employment_fraction->label(),
            'salary_min' => $offer->salary_min,
            'salary_max' => $offer->salary_max,
            'start_date' => $offer->start_date->toDateString(),
            'flexible_hours' => $offer->flexible_hours,
            'fixed_meeting_hours' => $offer->fixed_meeting_hours,
            'childcare_subsidy' => $offer->childcare_subsidy,
            'nursery_distance_km' => $offer->nursery_distance_km,
            'is_parent_friendly' => $offer->isParentFriendly(),
            'job_share' => Workday::presentOffer($offer),
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'verified' => $company->isVerified(),
                'rating' => $company->averageRating(),
                'featured_quote' => $this->featuredQuote($company),
            ],
        ];
    }
}
