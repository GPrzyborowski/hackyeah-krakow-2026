<?php

namespace App\Observers;

use App\Enums\JobSharePairStatus;
use App\Models\CandidateProfile;
use App\Models\JobSharePair;
use App\Notifications\PairHired;
use App\Notifications\PairHiredForCompany;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Notification;

/**
 * Congratulates both members and informs the company once a job-sharing pair is hired.
 */
class JobSharePairObserver implements ShouldHandleEventsAfterCommit
{
    public function updated(JobSharePair $pair): void
    {
        if (! $pair->wasChanged('status') || $pair->status !== JobSharePairStatus::Hired) {
            return;
        }

        $pair->loadMissing(['jobOffer.company.members', 'members.user']);

        Notification::send(
            $pair->members->map(fn (CandidateProfile $member) => $member->user),
            new PairHired($pair),
        );
        Notification::send($pair->jobOffer->company->members, new PairHiredForCompany($pair));
    }
}
