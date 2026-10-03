<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Company team management: every employer member of the company may manage the team (no owner role in the MVP).
 */
class CompanyTeamPolicy
{
    public function manageTeam(User $user, Company $company): bool
    {
        return $user->role === UserRole::Employer && $user->company_id === $company->id;
    }

    /**
     * Only a pending invitation of the member's own company can be revoked.
     */
    public function revokeInvitation(User $user, Company $company, CompanyInvitation $invitation): Response
    {
        if (! $this->manageTeam($user, $company) || $invitation->company_id !== $company->id) {
            return Response::deny();
        }

        return $invitation->isAccepted()
            ? Response::deny('To zaproszenie zostało już przyjęte.')
            : Response::allow();
    }

    /**
     * A member can remove a colleague, never themselves, and at least one member must remain.
     */
    public function removeMember(User $user, Company $company, User $member): Response
    {
        if (! $this->manageTeam($user, $company) || $member->company_id !== $company->id) {
            return Response::deny();
        }

        if ($member->is($user)) {
            return Response::deny('Nie możesz usunąć z zespołu własnego konta.');
        }

        return $company->members()->count() > 1
            ? Response::allow()
            : Response::deny('W zespole musi zostać co najmniej jedna osoba.');
    }
}
