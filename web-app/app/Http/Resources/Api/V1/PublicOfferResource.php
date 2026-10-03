<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Controllers\Public\Concerns\PresentsCompanyRatings;
use App\Models\JobOffer;
use App\Services\JobSharing\Workday;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Offer card in the public (guest) offers list: no match score, no candidate data.
 * Expects `company.approvedReviews` to be loaded.
 *
 * @property JobOffer $resource
 */
class PublicOfferResource extends JsonResource
{
    use PresentsCompanyRatings;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $offer = $this->resource;
        $company = $offer->company;

        return [
            'id' => $offer->id,
            'title' => $offer->title,
            'city' => $offer->city,
            'work_mode' => $offer->work_mode->value,
            'work_mode_label' => $offer->work_mode->label(),
            'employment_fraction' => $offer->employment_fraction->value,
            'employment_fraction_label' => $offer->employment_fraction->label(),
            'salary_min' => $offer->salary_min,
            'salary_max' => $offer->salary_max,
            'start_date' => $offer->start_date->toDateString(),
            'flexible_hours' => $offer->flexible_hours,
            'fixed_meeting_hours' => $offer->fixed_meeting_hours,
            'childcare_subsidy' => $offer->childcare_subsidy,
            'is_parent_friendly' => $offer->isParentFriendly(),
            'job_share' => Workday::presentOffer($offer),
            'published_at' => $offer->published_at?->toIso8601String(),
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'rating' => $company->averageRating(),
                'featured_quote' => $this->featuredQuote($company),
            ],
        ];
    }
}
