<?php

namespace App\Policies;

use App\Enums\OfferStatus;
use App\Models\JobOffer;
use App\Models\User;

class JobOfferPolicy
{
    /**
     * Employers attached to a company may list their company's offers.
     */
    public function viewAny(User $user): bool
    {
        return $this->belongsToCompany($user);
    }

    /**
     * Only members of the owning company may see an offer in the employer panel.
     */
    public function view(User $user, JobOffer $offer): bool
    {
        return $this->belongsToCompany($user) && $offer->company_id === $user->company_id;
    }

    public function create(User $user): bool
    {
        return $this->belongsToCompany($user);
    }

    public function update(User $user, JobOffer $offer): bool
    {
        return $this->view($user, $offer);
    }

    public function close(User $user, JobOffer $offer): bool
    {
        return $this->view($user, $offer) && $offer->status !== OfferStatus::Closed;
    }

    /**
     * Reviewing candidates (swipe, decisions, invitations) requires a published offer of the user's company.
     */
    public function reviewCandidates(User $user, JobOffer $offer): bool
    {
        return $this->view($user, $offer) && $offer->isPublished();
    }

    private function belongsToCompany(User $user): bool
    {
        return $user->isEmployer() && $user->company_id !== null;
    }
}
