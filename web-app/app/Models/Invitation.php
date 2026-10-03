<?php

namespace App\Models;

use App\Enums\InvitationKind;
use App\Enums\InvitationStatus;
use App\Enums\JobSharePairStatus;
use Carbon\CarbonImmutable;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $job_offer_id
 * @property int $candidate_profile_id
 * @property int|null $job_share_pair_id
 * @property int|null $sent_by_user_id
 * @property string $message
 * @property InvitationStatus $status
 * @property InvitationKind $kind
 * @property CarbonImmutable|null $responded_at
 * @property CarbonImmutable $created_at
 */
#[Fillable(['job_offer_id', 'candidate_profile_id', 'job_share_pair_id', 'sent_by_user_id', 'message', 'status', 'kind', 'responded_at'])]
class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'kind' => 'invitation',
    ];

    /**
     * @return BelongsTo<JobOffer, $this>
     */
    public function jobOffer(): BelongsTo
    {
        return $this->belongsTo(JobOffer::class);
    }

    /**
     * @return BelongsTo<CandidateProfile, $this>
     */
    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }

    /**
     * Set when the employer invited a whole job-sharing pair.
     *
     * @return BelongsTo<JobSharePair, $this>
     */
    public function jobSharePair(): BelongsTo
    {
        return $this->belongsTo(JobSharePair::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    /**
     * @return HasOne<Conversation, $this>
     */
    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }

    public function isPending(): bool
    {
        return $this->status === InvitationStatus::Pending;
    }

    public function isDirectMessage(): bool
    {
        return $this->kind === InvitationKind::DirectMessage;
    }

    /**
     * Accept the invitation and open the conversation; contact data becomes visible to the company.
     * A newly opened conversation starts with the invitation message, so the chat never opens empty.
     * A job-sharing pair moves to talks with the company once both of its members accepted their invitations
     * (the company marks it as hired later). A pair member also joins the pair's
     * team chat (opened by the first acceptance); her own 1:1 chat with the company stays for private matters.
     */
    public function accept(): Conversation
    {
        return DB::transaction(function (): Conversation {
            $this->update(['status' => InvitationStatus::Accepted, 'responded_at' => now()]);

            $this->markPairAcceptedWhenEveryoneAccepted();

            $conversation = $this->conversation()->firstOrCreate([], ['last_message_at' => now()]);

            if ($conversation->wasRecentlyCreated) {
                $this->seedConversation($conversation);
            }

            $this->joinPairTeamChat();

            return $conversation;
        });
    }

    /**
     * Open the pair's team chat on the first acceptance (seeded with the invitation message) and let the member in.
     * The invitation message she has already read is marked as read for her.
     */
    private function joinPairTeamChat(): void
    {
        if ($this->job_share_pair_id === null) {
            return;
        }

        $teamChat = Conversation::query()->firstOrCreate(['job_share_pair_id' => $this->job_share_pair_id], ['last_message_at' => now()]);

        if ($teamChat->wasRecentlyCreated) {
            $this->seedConversation($teamChat);
        }

        $invitationMessageId = $teamChat->messages()
            ->oldest('id')
            ->where('user_id', $this->sent_by_user_id)
            ->where('body', $this->message)
            ->value('id');

        $teamChat->reads()->firstOrCreate(
            ['user_id' => $this->candidateProfile->user_id],
            ['last_read_message_id' => $invitationMessageId ?? 0],
        );
    }

    /**
     * Copy the invitation message into the chat as the company's first message, already read by the candidate.
     * Saved without model events: the candidate has seen it, so no "new message" notification is sent.
     */
    private function seedConversation(Conversation $conversation): void
    {
        if ($this->sent_by_user_id === null || blank($this->message)) {
            return;
        }

        $message = new Message;
        $message->forceFill([
            'conversation_id' => $conversation->id,
            'user_id' => $this->sent_by_user_id,
            'body' => $this->message,
            'read_at' => now(),
            'created_at' => $this->created_at,
            'updated_at' => $this->created_at,
        ]);

        Message::withoutEvents(fn (): bool => $message->save());
    }

    /**
     * Decline; for a job-sharing pair the whole pair is declined and the partner's pending invitation withdrawn.
     */
    public function decline(): void
    {
        DB::transaction(function (): void {
            $this->update(['status' => InvitationStatus::Declined, 'responded_at' => now()]);

            $pair = $this->lockedPair();

            if ($pair === null) {
                return;
            }

            if ($pair->status === JobSharePairStatus::Invited) {
                $pair->update(['status' => JobSharePairStatus::Declined]);
            }

            $pair->invitations()
                ->whereKeyNot($this->id)
                ->where('status', InvitationStatus::Pending)
                ->update(['status' => InvitationStatus::Withdrawn]);
        });
    }

    private function markPairAcceptedWhenEveryoneAccepted(): void
    {
        $pair = $this->lockedPair();

        if ($pair === null || $pair->status !== JobSharePairStatus::Invited) {
            return;
        }

        $acceptedCount = $pair->invitations()->where('status', InvitationStatus::Accepted)->count();

        if ($acceptedCount === $pair->invitations()->count() && $acceptedCount >= $pair->members()->count()) {
            $pair->update(['status' => JobSharePairStatus::Accepted]);
        }
    }

    /**
     * The invitation's pair, locked so that two members answering at the same moment settle it only once.
     */
    private function lockedPair(): ?JobSharePair
    {
        if ($this->job_share_pair_id === null) {
            return null;
        }

        return JobSharePair::query()->lockForUpdate()->find($this->job_share_pair_id);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvitationStatus::class,
            'kind' => InvitationKind::class,
            'responded_at' => 'datetime',
        ];
    }
}
