<?php

namespace App\Observers;

use App\Enums\JobSharePairStatus;
use App\Models\CandidateProfile;
use App\Models\JobSharePair;
use App\Notifications\PairAcceptedForCompany;
use App\Notifications\PairHired;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Notification;

/**
 * Informs the company once both members of an invited pair accepted, and congratulates both members once the company hires the pair.
 */
class JobSharePairObserver implements ShouldHandleEventsAfterCommit
{
    public function updated(JobSharePair $pair): void
    {
        if (! $pair->wasChanged('status')) {
            return;
        }

        if ($pair->status === JobSharePairStatus::Accepted) {
            $pair->loadMissing(['jobOffer.company.members', 'members.user']);

            Notification::send($pair->jobOffer->company->members, new PairAcceptedForCompany($pair));

            return;
        }

        if ($pair->status === JobSharePairStatus::Hired) {
            $pair->loadMissing(['jobOffer.company', 'members.user']);

            Notification::send(
                $pair->members->map(fn (CandidateProfile $member) => $member->user),
                new PairHired($pair),
            );
        }
    }
}
