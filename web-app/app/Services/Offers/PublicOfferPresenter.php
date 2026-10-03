<?php

namespace App\Services\Offers;

use App\Enums\SkillImportance;
use App\Http\Controllers\Public\Concerns\PresentsCompanyRatings;
use App\Models\CompanyReview;
use App\Models\JobOffer;
use App\Models\Skill;
use App\Services\JobSharing\Workday;

/**
 * Match-free offer shapes shared by the public web pages and the mobile API (no candidate data).
 */
class PublicOfferPresenter
{
    use PresentsCompanyRatings;

    /**
     * Offer card for public lists. Expects `company.approvedReviews` to be loaded.
     *
     * @return array<string, mixed>
     */
    public function card(JobOffer $offer): array
    {
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
            'nursery_distance_km' => $offer->nursery_distance_km,
            'is_parent_friendly' => $offer->isParentFriendly(),
            'job_share' => Workday::presentOffer($offer),
            'published_at' => $offer->published_at?->toIso8601String(),
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'verified' => $company->isVerified(),
                'rating' => $company->averageRating(),
                'featured_quote' => $this->featuredQuote($company),
            ],
        ];
    }

    /**
     * Offer detail: the card plus description, skills, workday and the company's rating summary.
     * Expects `skills` and `company.approvedReviews` to be loaded.
     *
     * @return array<string, mixed>
     */
    public function detail(JobOffer $offer): array
    {
        $company = $offer->company;
        $skillNames = fn (SkillImportance $importance): array => array_values($offer->skills
            ->filter(fn (Skill $skill): bool => $skill->getRelationValue('pivot')?->getAttribute('importance') === $importance->value)
            ->map(fn (Skill $skill): string => $skill->name)
            ->all());

        return [
            ...$this->card($offer),
            'description' => $offer->description,
            'required_skills' => $skillNames(SkillImportance::Required),
            'nice_to_have_skills' => $skillNames(SkillImportance::NiceToHave),
            'workday_starts_at' => $offer->is_job_share ? Workday::format(Workday::forOffer($offer)->startsAt) : null,
            'workday_ends_at' => $offer->is_job_share ? Workday::format(Workday::forOffer($offer)->endsAt) : null,
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'city' => $company->city,
                'verified' => $company->isVerified(),
                'rating' => $this->ratingSummary($company),
                'featured_quote' => $this->featuredQuote($company),
            ],
        ];
    }

    /**
     * The company's approved reviews, newest first, anonymous (only the author's self-chosen label).
     * Expects `company.approvedReviews` to be loaded.
     *
     * @return list<array{id: int, rating_return: int, rating_flexibility: int, rating_no_pregnancy_questions: int, overall: float, quote: string|null, author_label: string|null}>
     */
    public function reviews(JobOffer $offer): array
    {
        return array_values($offer->company->approvedReviews
            ->sortByDesc(fn (CompanyReview $review): array => [$review->created_at?->getTimestamp() ?? 0, $review->id])
            ->map(fn (CompanyReview $review): array => [
                'id' => $review->id,
                'rating_return' => $review->rating_return,
                'rating_flexibility' => $review->rating_flexibility,
                'rating_no_pregnancy_questions' => $review->rating_no_pregnancy_questions,
                'overall' => round($review->overallRating(), 1),
                'quote' => $review->quote,
                'author_label' => $review->author_label,
            ])
            ->all());
    }
}
