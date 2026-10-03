<?php

namespace App\Models;

use App\Enums\InvitationStatus;
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
 * @property CarbonImmutable|null $responded_at
 * @property CarbonImmutable $created_at
 */
#[Fillable(['job_offer_id', 'candidate_profile_id', 'job_share_pair_id', 'sent_by_user_id', 'message', 'status', 'responded_at'])]
class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use HasFactory;

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

    /**
     * Accept the invitation and open the conversation; contact data becomes visible to the company.
     * A newly opened conversation starts with the invitation message, so the chat never opens empty.
     */
    public function accept(): Conversation
    {
        return DB::transaction(function (): Conversation {
            $this->update(['status' => InvitationStatus::Accepted, 'responded_at' => now()]);

            $conversation = $this->conversation()->firstOrCreate([], ['last_message_at' => now()]);

            if ($conversation->wasRecentlyCreated) {
                $this->seedConversation($conversation);
            }

            return $conversation;
        });
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

    public function decline(): void
    {
        $this->update(['status' => InvitationStatus::Declined, 'responded_at' => now()]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvitationStatus::class,
            'responded_at' => 'datetime',
        ];
    }
}
