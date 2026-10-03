<?php

namespace App\Services\Employer;

use App\Enums\JobSharePairStatus;
use App\Enums\OfferStatus;
use App\Models\Company;
use App\Models\JobOffer;
use Illuminate\Database\Eloquent\Builder;

/**
 * The company's own offers as listed in the employer panel: published first, then drafts, then closed.
 */
class CompanyOffers
{
    /**
     * Eager loads everything the funnel statistics need and counts pairs sent to the company.
     *
     * @return Builder<JobOffer>
     */
    public function listing(Company $company): Builder
    {
        return $company->jobOffers()
            ->getQuery()
            ->with(['skills', 'invitations', 'decisions:id,job_offer_id,candidate_profile_id', 'company'])
            ->withCount(['jobSharePairs as submitted_pairs_count' => fn ($query) => $query->where('status', JobSharePairStatus::Submitted)->visibleToCompany($company)])
            ->orderByRaw('case status when ? then 0 when ? then 1 else 2 end', [OfferStatus::Published->value, OfferStatus::Draft->value])
            ->latest()
            ->orderByDesc('id');
    }

    /**
     * "Przyjazna rodzicom" without extra queries: salary range, flexible hours and an approved review of the company.
     */
    public function isParentFriendly(JobOffer $offer, bool $companyHasApprovedReview): bool
    {
        return $offer->salary_min !== null && $offer->salary_max !== null && $offer->flexible_hours && $companyHasApprovedReview;
    }
}
