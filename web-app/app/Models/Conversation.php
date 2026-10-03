<?php

namespace App\Models;

use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use Carbon\CarbonImmutable;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Either a 1:1 chat opened by an accepted invitation (`invitation_id`) or the shared team chat of a job-sharing pair
 * (`job_share_pair_id`): the employer's company plus every pair member who accepted her invitation.
 *
 * @property int $id
 * @property int|null $invitation_id
 * @property int|null $job_share_pair_id
 * @property CarbonImmutable|null $last_message_at
 * @property-read int|null $unread_count Counterpart messages not yet read, loaded via withCount().
 */
#[Fillable(['invitation_id', 'job_share_pair_id', 'last_message_at'])]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Invitation, $this>
     */
    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }

    /**
     * Set for the team chat of a job-sharing pair.
     *
     * @return BelongsTo<JobSharePair, $this>
     */
    public function jobSharePair(): BelongsTo
    {
        return $this->belongsTo(JobSharePair::class);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @return HasMany<ConversationRead, $this>
     */
    public function reads(): HasMany
    {
        return $this->hasMany(ConversationRead::class);
    }

    public function isTeamChat(): bool
    {
        return $this->job_share_pair_id !== null;
    }

    /**
     * The offer the conversation is about.
     */
    public function jobOffer(): JobOffer
    {
        return $this->isTeamChat() ? $this->jobSharePair->jobOffer : $this->invitation->jobOffer;
    }

    /**
     * Candidates taking part: the invited candidate, or the pair members who accepted their invitation (in joining order).
     *
     * @return Collection<int, CandidateProfile>
     */
    public function candidateParticipants(): Collection
    {
        if (! $this->isTeamChat()) {
            return collect([$this->invitation->candidateProfile]);
        }

        return $this->jobSharePair->acceptedInvitations
            ->toBase()
            ->sortBy('responded_at')
            ->map(fn (Invitation $invitation): CandidateProfile => $invitation->candidateProfile)
            ->values();
    }

    /**
     * Members of the inviting company take part; candidates only once they accepted the invitation.
     */
    public function hasParticipant(User $user): bool
    {
        if ($user->role === UserRole::Candidate) {
            if (! $this->isTeamChat()) {
                return $this->invitation->candidateProfile->user_id === $user->id;
            }

            return $this->jobSharePair->invitations()
                ->where('status', InvitationStatus::Accepted)
                ->whereRelation('candidateProfile', 'user_id', $user->id)
                ->exists();
        }

        return $user->company_id !== null && $this->jobOffer()->company_id === $user->company_id;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }
}
