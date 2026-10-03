<?php

namespace App\Services\Conversations;

use App\Enums\UserRole;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\ConversationRead;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Conversation rules shared by the web inbox and the mobile API: who takes part, which messages count as unread,
 * how the counterpart is presented and how a new message is posted.
 *
 * Two kinds of conversations exist: the 1:1 chat opened by an accepted invitation, and the team chat of a job-sharing
 * pair ("Czat zespołu") shared by the inviting company and every pair member who accepted her invitation. A 1:1 chat
 * tracks reads on the message (`read_at`, one reader side); a team chat has several readers, so each participant's
 * progress is kept in `conversation_reads` (the message's `read_at` then means "read by the first other participant").
 */
class ConversationInbox
{
    /**
     * Relations needed to present conversations of both kinds.
     *
     * @var list<string>
     */
    public const array PRESENTATION_RELATIONS = [
        'invitation.jobOffer.company',
        'invitation.candidateProfile.user',
        'jobSharePair.jobOffer.company',
        'jobSharePair.members.user',
        'jobSharePair.acceptedInvitations.candidateProfile.user',
    ];

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
                ...self::PRESENTATION_RELATIONS,
                'messages' => fn ($query) => $query->latest('id')->limit(1),
            ])
            ->withCount(['messages as unread_count' => function (Builder $query) use ($user): void {
                $this->scopeToCounterpartMessages($query, $user);
                $query->where(fn (Builder $query) => $query
                    ->where(fn (Builder $query) => $query->whereNull('conversations.job_share_pair_id')->whereNull('messages.read_at'))
                    ->orWhere(fn (Builder $query) => $query
                        ->whereNotNull('conversations.job_share_pair_id')
                        ->where('messages.id', '>', ConversationRead::query()
                            ->selectRaw('coalesce(max(last_read_message_id), 0)')
                            ->whereColumn('conversation_reads.conversation_id', 'messages.conversation_id')
                            ->where('conversation_reads.user_id', $user->id))));
            }])
            ->orderByDesc('last_message_at');
    }

    /**
     * Mark the other side's unread messages in the thread as read (in a team chat: everything up to the newest message, for this participant).
     */
    public function markCounterpartMessagesRead(Conversation $conversation, User $user): void
    {
        $conversation->messages()
            ->whereNull('read_at')
            ->where(fn (Builder $query) => $this->scopeToCounterpartMessages($query, $user))
            ->update(['read_at' => now()]);

        if (! $conversation->isTeamChat()) {
            return;
        }

        $latestMessageId = (int) $conversation->messages()->max('id');
        $read = $conversation->reads()->firstOrNew(['user_id' => $user->id]);

        if ($read->exists && $read->last_read_message_id >= $latestMessageId) {
            return;
        }

        $read->last_read_message_id = $latestMessageId;
        $read->save();
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
     * Expects the message author (with role) to be loaded.
     */
    public function isOwnSide(Message $message, User $viewer): bool
    {
        return $viewer->isCandidate()
            ? $message->user_id === $viewer->id
            : $message->author->role !== UserRole::Candidate;
    }

    /**
     * Author as shown to the viewer: a job-sharing partner sees the other candidate only anonymously (first name + surname initial).
     */
    public function authorName(Message $message, User $viewer): string
    {
        $author = $message->author;

        if (! $viewer->isCandidate() || $author->role !== UserRole::Candidate || $author->id === $viewer->id) {
            return $author->name;
        }

        $profile = $author->candidateProfile;

        return $profile ? $profile->setRelation('user', $author)->anonymousName() : $author->name;
    }

    public function counterpartName(Conversation $conversation, User $user): string
    {
        if ($conversation->isTeamChat()) {
            return $this->teamChatName($conversation, $user);
        }

        return $user->isCandidate()
            ? $conversation->invitation->jobOffer->company->name
            : $conversation->invitation->candidateProfile->user->name;
    }

    /**
     * "Czat zespołu: Marta K. i Ewa N." – members named as the viewer may see them (see teamMembers()).
     */
    public function teamChatName(Conversation $conversation, User $user): string
    {
        $names = array_column($this->teamMembers($conversation, $user), 'name');

        return 'Czat zespołu: '.implode(' i ', $names);
    }

    /**
     * Header data: the employer sees the revealed candidate (invitation accepted, so contact data incl. phone and photo);
     * the candidate sees the company; a team chat shows the company and the pair members. $forApi picks the API photo
     * endpoint (bearer token) instead of the web one.
     *
     * @return array{type: 'candidate', name: string, email: string, phone: string|null, photo_url: string|null}|array{type: 'company', id: int, name: string, verified: bool, rating: float|null}|array{type: 'team', name: string, company: array{id: int, name: string, verified: bool, rating: float|null}, members: list<array{name: string, joined: bool, is_me: bool, email: string|null, phone: string|null, photo_url: string|null}>}
     */
    public function counterpart(Conversation $conversation, User $user, bool $forApi = false): array
    {
        if ($conversation->isTeamChat()) {
            return [
                'type' => 'team',
                'name' => $this->teamChatName($conversation, $user),
                'company' => $this->companySummary($conversation->jobOffer()->company),
                'members' => $this->teamMembers($conversation, $user, $forApi),
            ];
        }

        if ($user->isCandidate()) {
            return ['type' => 'company', ...$this->companySummary($conversation->invitation->jobOffer->company)];
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
     * Members of a team chat's pair. The company sees a member's full name and contact data only once she accepted
     * (joined the chat) – before that just her anonymous name; candidates see each other only anonymously.
     *
     * @return list<array{name: string, joined: bool, is_me: bool, email: string|null, phone: string|null, photo_url: string|null}>
     */
    public function teamMembers(Conversation $conversation, User $user, bool $forApi = false): array
    {
        $pair = $conversation->jobSharePair;
        $joinedProfileIds = $pair->acceptedInvitations->pluck('candidate_profile_id')->all();

        return array_values($pair->members->map(function (CandidateProfile $member) use ($user, $joinedProfileIds, $forApi): array {
            $joined = in_array($member->id, $joinedProfileIds, true);
            $revealed = $joined && ! $user->isCandidate();

            return [
                'name' => $revealed ? $member->user->name : $member->anonymousName(),
                'joined' => $joined,
                'is_me' => $member->user_id === $user->id,
                'email' => $revealed ? $member->user->email : null,
                'phone' => $revealed ? $member->phone : null,
                'photo_url' => $revealed ? $member->photoUrl($forApi) : null,
            ];
        })->all());
    }

    /**
     * For a job-sharing pair invitation's 1:1 chat: the candidate's partner, named anonymously (first name + surname initial).
     */
    public function pairPartnerName(Conversation $conversation): ?string
    {
        $invitation = $conversation->invitation;

        if ($invitation === null) {
            return null;
        }

        return $invitation->jobSharePair?->members
            ->first(fn (CandidateProfile $member): bool => $member->id !== $invitation->candidate_profile_id)
            ?->anonymousName();
    }

    /**
     * @return array{id: int, name: string, verified: bool, rating: float|null}
     */
    private function companySummary(Company $company): array
    {
        return ['id' => $company->id, 'name' => $company->name, 'verified' => $company->isVerified(), 'rating' => $company->averageRating()];
    }

    /**
     * @param  Builder<Conversation>  $query
     */
    private function scopeToParticipant(Builder $query, User $user): void
    {
        match ($user->role) {
            UserRole::Candidate => $query
                ->whereHas('invitation.candidateProfile', fn (Builder $query) => $query->where('user_id', $user->id))
                ->orWhereHas('jobSharePair.acceptedInvitations.candidateProfile', fn (Builder $query) => $query->where('user_id', $user->id)),
            UserRole::Employer => $query
                ->whereHas('invitation.jobOffer', fn (Builder $query) => $query->where('company_id', $user->company_id ?? 0))
                ->orWhereHas('jobSharePair.jobOffer', fn (Builder $query) => $query->where('company_id', $user->company_id ?? 0)),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Messages written by the other side: everybody else for a candidate (the company, and her partner in a team chat),
     * the candidates for company members.
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
