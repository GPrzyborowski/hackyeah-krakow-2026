<?php

namespace App\Policies;

use App\Enums\InvitationStatus;
use App\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CandidateProfilePolicy
{
    /**
     * The photo (like the phone) is contact data: only the candidate herself and members of a company
     * whose invitation she accepted may see it. Everyone else gets a 404, so its existence is not leaked.
     */
    public function viewPhoto(User $user, CandidateProfile $profile): Response
    {
        if ($profile->user_id === $user->id || $this->hasAcceptedInvitationFrom($user, $profile)) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

    private function hasAcceptedInvitationFrom(User $user, CandidateProfile $profile): bool
    {
        if (! $user->isEmployer() || $user->company_id === null) {
            return false;
        }

        return $profile->invitations()
            ->where('status', InvitationStatus::Accepted)
            ->whereHas('jobOffer', fn ($query) => $query->where('company_id', $user->company_id))
            ->exists();
    }
}
