<?php

namespace App\Http\Controllers\Candidate\Concerns;

use App\Models\CandidateProfile;
use App\Models\CompanyReview;
use App\Models\JobOffer;
use App\Services\JobSharing\Workday;
use App\Services\Matching\MatchResult;

trait PresentsOffers
{
    /**
     * Offer card data for the candidate views.
     *
     * @param  list<int>  $interestedOfferIds
     * @param  list<int>  $savedOfferIds
     * @return array<string, mixed>
     */
    protected function presentOffer(JobOffer $offer, MatchResult $match, array $interestedOfferIds = [], array $savedOfferIds = []): array
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
            'nursery_distance_km' => $offer->nursery_distance_km,
            'job_share' => Workday::presentOffer($offer),
            'published_at' => $offer->published_at?->toIso8601String(),
            'is_parent_friendly' => $offer->isParentFriendly(),
            'is_interested' => in_array($offer->id, $interestedOfferIds, true),
            'is_saved' => in_array($offer->id, $savedOfferIds, true),
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

    /**
     * Ids of the offers the candidate showed interest in, optionally narrowed to a single offer.
     *
     * @return list<int>
     */
    protected function interestedOfferIds(CandidateProfile $profile, ?JobOffer $offer = null): array
    {
        return array_values($profile->interests()
            ->when($offer, fn ($query, JobOffer $offer) => $query->where('job_offer_id', $offer->id))
            ->pluck('job_offer_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all());
    }

    /**
     * Ids of the offers the candidate saved, optionally narrowed to a single offer.
     *
     * @return list<int>
     */
    protected function savedOfferIds(CandidateProfile $profile, ?JobOffer $offer = null): array
    {
        return array_values($profile->savedOffers()
            ->when($offer, fn ($query, JobOffer $offer) => $query->whereKey($offer->id))
            ->pluck('job_offers.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all());
    }
}
