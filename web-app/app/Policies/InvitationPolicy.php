<?php

namespace App\Policies;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class InvitationPolicy
{
    /**
     * Only the invited candidate may accept or decline, and only while the invitation is pending.
     */
    public function respond(User $user, Invitation $invitation): Response
    {
        if (! $user->isCandidate() || $invitation->candidateProfile?->user_id !== $user->id) {
            return Response::deny();
        }

        return $invitation->isPending()
            ? Response::allow()
            : Response::deny('Na to zaproszenie już odpowiedziano.');
    }
}
