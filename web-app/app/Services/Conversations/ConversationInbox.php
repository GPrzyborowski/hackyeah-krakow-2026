<?php

namespace App\Services\Conversations;

use App\Enums\UserRole;
use App\Models\CandidateProfile;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Conversation rules shared by the web inbox and the mobile API: who takes part, which messages count as unread,
 * how the counterpart is presented and how a new message is posted.
 */
class ConversationInbox
{
    /**
     * Conversations of the candidate or of the employer's company, newest activity first, with the last message and unread count.
     *
     * @return Builder<Conversation>
     */
    public function conversationsFor(User $user): Builder
    {
        return Conversation::query()
            ->where(fn (Builder $query) => $this->scopeToParticipant($query, $user))
            ->with([
                'invitation.jobOffer.company',
                'invitation.candidateProfile.user',
                'messages' => fn ($query) => $query->latest('id')->limit(1),
            ])
            ->withCount(['messages as unread_count' => function (Builder $query) use ($user): void {
                $query->whereNull('read_at');
                $this->scopeToCounterpartMessages($query, $user);
            }])
            ->orderByDesc('last_message_at');
    }

    /**
     * Mark the other side's unread messages in the thread as read.
     */
    public function markCounterpartMessagesRead(Conversation $conversation, User $user): void
    {
        $conversation->messages()
            ->whereNull('read_at')
            ->where(fn (Builder $query) => $this->scopeToCounterpartMessages($query, $user))
            ->update(['read_at' => now()]);
    }

    /**
     * Post a message and bump the thread; authorization and moderation happen before (form request).
     */
    public function post(Conversation $conversation, User $author, string $body): Message
    {
        $message = $conversation->messages()->create([
            'user_id' => $author->id,
            'body' => trim($body),
        ]);

        $conversation->update(['last_message_at' => $message->created_at]);

        return $message;
    }

    /**
     * Whether the viewer's side wrote the message: her own for a candidate, any company member's for an employer.
     */
    public function isOwnSide(Message $message, User $viewer, int $candidateUserId): bool
    {
        return $viewer->isCandidate()
            ? $message->user_id === $viewer->id
            : $message->user_id !== $candidateUserId;
    }

    public function counterpartName(Conversation $conversation, User $user): string
    {
        return $user->isCandidate()
            ? $conversation->invitation->jobOffer->company->name
            : $conversation->invitation->candidateProfile->user->name;
    }

    /**
     * Header data: the employer sees the revealed candidate (invitation accepted, so contact data incl. phone and photo);
     * the candidate sees the company. $forApi picks the API photo endpoint (bearer token) instead of the web one.
     *
     * @return array{type: 'candidate', name: string, email: string, phone: string|null, photo_url: string|null}|array{type: 'company', id: int, name: string, verified: bool, rating: float|null}
     */
    public function counterpart(Conversation $conversation, User $user, bool $forApi = false): array
    {
        if ($user->isCandidate()) {
            $company = $conversation->invitation->jobOffer->company;

            return ['type' => 'company', 'id' => $company->id, 'name' => $company->name, 'verified' => $company->isVerified(), 'rating' => $company->averageRating()];
        }

        $profile = $conversation->invitation->candidateProfile;

        return [
            'type' => 'candidate',
            'name' => $profile->user->name,
            'email' => $profile->user->email,
            'phone' => $profile->phone,
            'photo_url' => $profile->photoUrl($forApi),
        ];
    }

    /**
     * For a job-sharing pair invitation: the candidate's partner, named anonymously (first name + surname initial).
     */
    public function pairPartnerName(Conversation $conversation): ?string
    {
        $invitation = $conversation->invitation;

        return $invitation->jobSharePair?->members
            ->first(fn (CandidateProfile $member): bool => $member->id !== $invitation->candidate_profile_id)
            ?->anonymousName();
    }

    /**
     * @param  Builder<Conversation>  $query
     */
    private function scopeToParticipant(Builder $query, User $user): void
    {
        match ($user->role) {
            UserRole::Candidate => $query->whereHas(
                'invitation.candidateProfile',
                fn (Builder $query) => $query->where('user_id', $user->id),
            ),
            UserRole::Employer => $query->whereHas(
                'invitation.jobOffer',
                fn (Builder $query) => $query->where('company_id', $user->company_id ?? 0),
            ),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Messages written by the other side: the company for a candidate, the candidate for company members.
     *
     * @param  Builder<Message>  $query
     */
    private function scopeToCounterpartMessages(Builder $query, User $user): void
    {
        if ($user->isCandidate()) {
            $query->where('user_id', '!=', $user->id);

            return;
        }

        $query->whereHas('author', fn (Builder $query) => $query->where('role', UserRole::Candidate));
    }
}
