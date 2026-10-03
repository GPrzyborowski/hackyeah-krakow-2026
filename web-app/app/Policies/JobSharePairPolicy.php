<?php

namespace App\Policies;

use App\Enums\JobSharePairStatus;
use App\Models\CandidateProfile;
use App\Models\JobSharePair;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class JobSharePairPolicy
{
    /**
     * Only the two candidates of the pair (including an invited partner who has not answered yet) may open it.
     */
    public function view(User $user, JobSharePair $pair): bool
    {
        $profile = $this->profileOf($user);

        return $profile !== null && $pair->hasMember($profile);
    }

    /**
     * The invited partner answers the pair invitation while the pair is still forming.
     */
    public function respond(User $user, JobSharePair $pair): Response
    {
        $profile = $this->profileOf($user);

        if ($profile === null || ! $pair->hasMember($profile)) {
            return Response::deny();
        }

        return $pair->status === JobSharePairStatus::Forming && ! $pair->hasAcceptedMember($profile)
            ? Response::allow()
            : Response::deny('Na to zaproszenie do pary już odpowiedziano.');
    }

    /**
     * Accepted members chat with each other.
     */
    public function chat(User $user, JobSharePair $pair): bool
    {
        $profile = $this->profileOf($user);

        return $profile !== null && $pair->hasAcceptedMember($profile);
    }

    /**
     * The split of the day may be changed only once both are in and before it was sent to the employer.
     */
    public function planSchedule(User $user, JobSharePair $pair): Response
    {
        if (! $this->chat($user, $pair)) {
            return Response::deny();
        }

        return $pair->status === JobSharePairStatus::Formed
            ? Response::allow()
            : Response::deny('Podział dnia można zmieniać tylko w parze, która nie została jeszcze wysłana.');
    }

    /**
     * Any accepted member may leave a pair that has not been sent to the employer yet;
     * once the pair is submitted, invited or hired it can no longer be dissolved by a member.
     */
    public function cancel(User $user, JobSharePair $pair): Response
    {
        if (! $this->chat($user, $pair)) {
            return Response::deny();
        }

        return in_array($pair->status, [JobSharePairStatus::Forming, JobSharePairStatus::Formed], true)
            ? Response::allow()
            : Response::deny('Tej pary nie można już rozwiązać – została wysłana do pracodawcy lub zakończyła się decyzją.');
    }

    /**
     * Employers of the offer's company decide on pairs that were sent to them.
     */
    public function review(User $user, JobSharePair $pair): Response
    {
        if (! $user->isEmployer() || $user->company_id === null || $pair->jobOffer->company_id !== $user->company_id) {
            return Response::denyAsNotFound();
        }

        return $pair->status === JobSharePairStatus::Submitted
            ? Response::allow()
            : Response::deny('O tej parze już zdecydowano.');
    }

    private function profileOf(User $user): ?CandidateProfile
    {
        return $user->isCandidate() ? $user->candidateProfile : null;
    }
}
