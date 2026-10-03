<?php

namespace App\Services\Employer;

use App\Enums\InvitationStatus;
use App\Models\Company;
use App\Models\Invitation;
use Illuminate\Database\Eloquent\Builder;

/**
 * Invitations sent by a company as the employer may list them.
 */
class CompanyInvitations
{
    /**
     * Unanswered and declined invitations disappear once the candidate hides her profile from the company;
     * accepted ones stay because the contact was already shared.
     *
     * @return Builder<Invitation>
     */
    public function query(Company $company): Builder
    {
        return Invitation::query()
            ->whereRelation('jobOffer', 'company_id', $company->id)
            ->where(fn (Builder $query) => $query
                ->where('status', InvitationStatus::Accepted)
                ->orWhereHas('candidateProfile', fn (Builder $candidate) => $candidate->where(fn (Builder $candidate) => $candidate
                    ->whereNull('hidden_from_company_id')
                    ->orWhere('hidden_from_company_id', '!=', $company->id))))
            ->with(['jobOffer', 'candidateProfile.user', 'conversation']);
    }
}
