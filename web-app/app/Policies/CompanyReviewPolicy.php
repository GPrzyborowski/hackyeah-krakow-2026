<?php

namespace App\Policies;

use App\Enums\InvitationStatus;
use App\Enums\ReviewStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\CompanyReview;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;

class CompanyReviewPolicy
{
    /**
     * Companies whose invitation the candidate accepted (a conversation was opened), i.e. companies she actually talked to.
     *
     * @return Builder<Company>
     */
    public static function companiesKnownTo(User $user): Builder
    {
        return Company::query()->whereHas('jobOffers.invitations', fn (Builder $query) => $query
            ->where('status', InvitationStatus::Accepted)
            ->whereHas('conversation')
            ->whereHas('candidateProfile', fn (Builder $query) => $query->where('user_id', $user->id)));
    }

    /**
     * A candidate may review a company once, and only after accepting its invitation.
     */
    public function create(User $user, Company $company): Response
    {
        if ($user->role !== UserRole::Candidate || ! self::companiesKnownTo($user)->whereKey($company->id)->exists()) {
            return Response::deny('Możesz ocenić tylko firmę, której zaproszenie przyjęłaś.');
        }

        if ($company->reviews()->where('user_id', $user->id)->exists()) {
            return Response::deny('Ta firma ma już Twoją opinię.');
        }

        return Response::allow();
    }

    /**
     * The author may edit her review while it still waits for moderation.
     */
    public function update(User $user, CompanyReview $review): bool
    {
        return $review->user_id === $user->id && $review->status === ReviewStatus::Pending;
    }

    /**
     * Only administrators approve or reject reviews.
     */
    public function moderate(User $user, CompanyReview $review): bool
    {
        return $user->role === UserRole::Admin;
    }
}
