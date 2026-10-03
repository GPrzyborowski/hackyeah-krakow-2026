<?php

namespace App\Observers;

use App\Enums\UserRole;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessage;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Collection;

/**
 * Notifies the other side of the conversation, at most once per unread thread.
 */
class MessageObserver implements ShouldHandleEventsAfterCommit
{
    public function created(Message $message): void
    {
        $invitation = $message->conversation->invitation;
        $company = $invitation->jobOffer->company;
        $candidate = $invitation->candidateProfile->user;
        $authorIsCandidate = $message->author->role === UserRole::Candidate;

        /** @var Collection<int, User> $recipients */
        $recipients = $authorIsCandidate ? $company->members : collect([$candidate]);
        $senderName = $authorIsCandidate ? $candidate->name : $company->name;

        $recipients
            ->reject(fn (User $recipient): bool => $recipient->is($message->author) || $this->hasUnreadNotificationFor($recipient, $message))
            ->each(fn (User $recipient) => $recipient->notify(new NewMessage($message, $senderName)));
    }

    private function hasUnreadNotificationFor(User $recipient, Message $message): bool
    {
        return $recipient->unreadNotifications()
            ->where('type', NewMessage::class)
            ->where('data->conversation_id', $message->conversation_id)
            ->exists();
    }
}
