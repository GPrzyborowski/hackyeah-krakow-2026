<?php

namespace App\Observers;

use App\Models\Conversation;
use App\Notifications\InvitationAccepted;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Notification;

/**
 * A conversation is opened only when the candidate accepts an invitation.
 */
class ConversationObserver implements ShouldHandleEventsAfterCommit
{
    public function created(Conversation $conversation): void
    {
        Notification::send($conversation->invitation->jobOffer->company->members, new InvitationAccepted($conversation));
    }
}
