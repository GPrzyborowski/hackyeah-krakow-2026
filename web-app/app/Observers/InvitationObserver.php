<?php

namespace App\Observers;

use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Notifications\InvitationDeclined;
use App\Notifications\InvitationReceived;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Notification;

/**
 * Notifies the candidate about a new invitation (also when a question becomes an invitation) and the company about a declined one.
 * Acceptance is announced by ConversationObserver, once the conversation exists.
 */
class InvitationObserver implements ShouldHandleEventsAfterCommit
{
    public function created(Invitation $invitation): void
    {
        if (! $invitation->isPending()) {
            return;
        }

        $invitation->candidateProfile->user->notify(new InvitationReceived($invitation));
    }

    public function updated(Invitation $invitation): void
    {
        if ($invitation->wasChanged('kind') && ! $invitation->isDirectMessage() && $invitation->isPending()) {
            $invitation->candidateProfile->user->notify(new InvitationReceived($invitation));

            return;
        }

        if (! $invitation->wasChanged('status') || $invitation->status !== InvitationStatus::Declined) {
            return;
        }

        Notification::send($invitation->jobOffer->company->members, new InvitationDeclined($invitation));
    }
}
