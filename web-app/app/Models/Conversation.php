<?php

namespace App\Models;

use App\Enums\UserRole;
use Carbon\CarbonImmutable;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $invitation_id
 * @property CarbonImmutable|null $last_message_at
 */
#[Fillable(['invitation_id', 'last_message_at'])]
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
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * The candidate herself or any member of the inviting company may take part.
     */
    public function hasParticipant(User $user): bool
    {
        $invitation = $this->invitation;

        if ($user->role === UserRole::Candidate) {
            return $invitation->candidateProfile->user_id === $user->id;
        }

        return $user->company_id !== null && $invitation->jobOffer->company_id === $user->company_id;
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
