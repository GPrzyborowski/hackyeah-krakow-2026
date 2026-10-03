<?php

namespace App\Http\Controllers\Conversations;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\CandidateProfile;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    /**
     * Conversations of the signed-in candidate or of the employer's company.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $conversations = Conversation::query()
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
            ->orderByDesc('last_message_at')
            ->get();

        return Inertia::render('conversations/Index', [
            'conversations' => $conversations->map(function (Conversation $conversation) use ($user): array {
                /** @var Message|null $lastMessage */
                $lastMessage = $conversation->messages->first();

                return [
                    'id' => $conversation->id,
                    'counterpart_name' => $this->counterpartName($conversation, $user),
                    'offer_title' => $conversation->invitation->jobOffer->title,
                    'last_message' => $lastMessage ? Str::limit($lastMessage->body, 90) : null,
                    'last_message_at' => ($lastMessage->created_at ?? $conversation->last_message_at)?->toIso8601String(),
                    'has_unread' => $conversation->unread_count > 0,
                ];
            })->values(),
        ]);
    }

    /**
     * The thread; opening it marks the counterpart's messages as read.
     */
    public function show(Request $request, Conversation $conversation): Response
    {
        Gate::authorize('view', $conversation);

        $user = $request->user();
        $conversation->load(['invitation.jobOffer.company', 'invitation.candidateProfile.user', 'invitation.jobSharePair.members.user']);
        $candidateUserId = $conversation->invitation->candidateProfile->user_id;

        $conversation->messages()
            ->whereNull('read_at')
            ->where(fn (Builder $query) => $this->scopeToCounterpartMessages($query, $user))
            ->update(['read_at' => now()]);

        $messages = $conversation->messages()->with('author:id,name')->oldest('id')->get();

        return Inertia::render('conversations/Show', [
            'conversation' => [
                'id' => $conversation->id,
                'offer_title' => $conversation->invitation->jobOffer->title,
                'counterpart' => $this->counterpart($conversation, $user),
                'pair_partner_name' => $this->pairPartnerName($conversation),
            ],
            'viewerRole' => $user->role,
            'messages' => $messages->map(fn (Message $message): array => [
                'id' => $message->id,
                'body' => $message->body,
                'author_name' => $message->author->name,
                'is_mine' => $user->isCandidate()
                    ? $message->user_id === $user->id
                    : $message->user_id !== $candidateUserId,
                'created_at' => $message->created_at->toIso8601String(),
            ])->values(),
        ]);
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

    private function counterpartName(Conversation $conversation, User $user): string
    {
        return $user->isCandidate()
            ? $conversation->invitation->jobOffer->company->name
            : $conversation->invitation->candidateProfile->user->name;
    }

    /**
     * For a job-sharing pair invitation: the candidate's partner, named anonymously (first name + surname initial).
     */
    private function pairPartnerName(Conversation $conversation): ?string
    {
        $invitation = $conversation->invitation;

        return $invitation->jobSharePair?->members
            ->first(fn (CandidateProfile $member): bool => $member->id !== $invitation->candidate_profile_id)
            ?->anonymousName();
    }

    /**
     * Header data: the employer sees the revealed candidate (invitation accepted); the candidate sees the company.
     *
     * @return array{type: 'candidate', name: string, email: string}|array{type: 'company', name: string, rating: float|null}
     */
    private function counterpart(Conversation $conversation, User $user): array
    {
        if ($user->isCandidate()) {
            $company = $conversation->invitation->jobOffer->company;

            return ['type' => 'company', 'name' => $company->name, 'rating' => $company->averageRating()];
        }

        $candidate = $conversation->invitation->candidateProfile->user;

        return ['type' => 'candidate', 'name' => $candidate->name, 'email' => $candidate->email];
    }
}
