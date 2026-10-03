<?php

namespace App\Observers;

use App\Models\Conversation;
use App\Notifications\InvitationAccepted;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Notification;

/**
 * A conversation is opened only when the candidate accepts an invitation. A pair's team chat is opened by the same
 * acceptance as her 1:1 chat, which already announces it, so it sends nothing.
 */
class ConversationObserver implements ShouldHandleEventsAfterCommit
{
    public function created(Conversation $conversation): void
    {
        if ($conversation->isTeamChat()) {
            return;
        }

        Notification::send($conversation->invitation->jobOffer->company->members, new InvitationAccepted($conversation));
    }
}
