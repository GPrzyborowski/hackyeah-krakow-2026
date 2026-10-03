<?php

namespace App\Observers;

use App\Enums\UserRole;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessage;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Collection;

/**
 * Notifies the other participants of the conversation, at most once per unread thread: the other side of a 1:1 chat,
 * or everybody else in a job-sharing pair's team chat.
 */
class MessageObserver implements ShouldHandleEventsAfterCommit
{
    public function created(Message $message): void
    {
        $conversation = $message->conversation;
        $company = $conversation->jobOffer()->company;
        $authorIsCandidate = $message->author->role === UserRole::Candidate;

        /** @var Collection<int, User> $candidates */
        $candidates = $conversation->candidateParticipants()->map(fn (CandidateProfile $profile): User => $profile->user);

        /** @var Collection<int, User> $recipients */
        $recipients = match (true) {
            $conversation->isTeamChat() => $company->members->toBase()->concat($candidates),
            $authorIsCandidate => $company->members->toBase(),
            default => $candidates,
        };

        $recipients
            ->reject(fn (User $recipient): bool => $recipient->is($message->author) || $this->hasUnreadNotificationFor($recipient, $message))
            ->each(fn (User $recipient) => $recipient->notify(new NewMessage($message, $this->senderName($message, $recipient, $company))));
    }

    /**
     * The company name for company messages; a candidate's full name for the company, but only her anonymous name
     * (first name + surname initial) for her job-sharing partner.
     */
    private function senderName(Message $message, User $recipient, Company $company): string
    {
        $author = $message->author;

        if ($author->role !== UserRole::Candidate) {
            return $company->name;
        }

        return $recipient->isCandidate() ? ($author->candidateProfile?->anonymousName() ?? $author->name) : $author->name;
    }

    private function hasUnreadNotificationFor(User $recipient, Message $message): bool
    {
        return $recipient->unreadNotifications()
            ->where('type', NewMessage::class)
            ->where('data->conversation_id', $message->conversation_id)
            ->exists();
    }
}
