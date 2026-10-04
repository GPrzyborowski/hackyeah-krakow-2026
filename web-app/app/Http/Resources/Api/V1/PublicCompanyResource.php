<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Controllers\Public\Concerns\PresentsCompanyRatings;
use App\Models\Company;
use App\Models\JobOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public company profile: description, rating summary, approved reviews only and published offers.
 * Expects `approvedReviews` and published `jobOffers` to be loaded.
 *
 * @property Company $resource
 */
class PublicCompanyResource extends JsonResource
{
    use PresentsCompanyRatings;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $company = $this->resource;

        return [
            'id' => $company->id,
            'name' => $company->name,
            'city' => $company->city,
            'verified' => $company->isVerified(),
            'description' => $company->description,
            'rating' => $this->ratingSummary($company),
            'reviews' => PublicCompanyReviewResource::collection($company->approvedReviews)->toArray($request),
            'offers' => $company->jobOffers->map(fn (JobOffer $offer): array => [
                'id' => $offer->id,
                'title' => $offer->title,
                'category' => $offer->category->value,
                'category_label' => $offer->category->label(),
                'city' => $offer->city,
                'work_mode' => $offer->work_mode->value,
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
                'is_job_share' => $offer->is_job_share,
            ])->values()->all(),
        ];
    }
}
