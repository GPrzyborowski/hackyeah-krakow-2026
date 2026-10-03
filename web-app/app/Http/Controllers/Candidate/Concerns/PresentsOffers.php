<?php

namespace App\Http\Controllers\Candidate\Concerns;

use App\Models\CompanyReview;
use App\Models\JobOffer;
use App\Services\Matching\MatchResult;

trait PresentsOffers
{
    /**
     * Offer card data for the candidate views.
     *
     * @param  list<int>  $interestedOfferIds
     * @return array<string, mixed>
     */
    protected function presentOffer(JobOffer $offer, MatchResult $match, array $interestedOfferIds = []): array
    {
        $offer->loadMissing('company.approvedReviews');

        /** @var CompanyReview|null $firstReview */
        $firstReview = $offer->company->approvedReviews->first(fn (CompanyReview $review): bool => filled($review->quote));

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
            'published_at' => $offer->published_at?->toIso8601String(),
            'is_parent_friendly' => $offer->isParentFriendly(),
            'is_interested' => in_array($offer->id, $interestedOfferIds, true),
            'match' => $match->toArray(),
            'company' => [
                'id' => $offer->company->id,
                'name' => $offer->company->name,
                'city' => $offer->company->city,
                'average_rating' => $offer->company->averageRating(),
                'reviews_count' => $offer->company->approvedReviews->count(),
                'first_review' => $firstReview ? [
                    'quote' => $firstReview->quote,
                    'author_label' => $firstReview->author_label,
                ] : null,
            ],
        ];
    }
}
