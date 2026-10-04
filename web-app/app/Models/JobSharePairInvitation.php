<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\JobSharePairInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A shareable link with which the initiator of a forming job-sharing pair invites a friend to join it.
 *
 * @property int $id
 * @property int $job_share_pair_id
 * @property string $token
 * @property int $invited_by_candidate_profile_id
 * @property int|null $accepted_by_candidate_profile_id
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read JobSharePair $pair
 * @property-read CandidateProfile $invitedBy
 * @property-read CandidateProfile|null $acceptedBy
 */
#[Fillable(['job_share_pair_id', 'token', 'invited_by_candidate_profile_id', 'accepted_by_candidate_profile_id', 'accepted_at', 'expires_at'])]
#[Hidden(['token'])]
class JobSharePairInvitation extends Model
{
    /** @use HasFactory<JobSharePairInvitationFactory> */
    use HasFactory;

    public const int VALID_DAYS = 7;

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    /**
     * @return BelongsTo<JobSharePair, $this>
     */
    public function pair(): BelongsTo
    {
        return $this->belongsTo(JobSharePair::class, 'job_share_pair_id');
    }

    /**
     * @return BelongsTo<CandidateProfile, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class, 'invited_by_candidate_profile_id');
    }

    /**
     * @return BelongsTo<CandidateProfile, $this>
     */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class, 'accepted_by_candidate_profile_id');
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function url(): string
    {
        return route('job-sharing.join.show', $this->token);
    }

    /**
     * Not used yet and still within its validity window.
     *
     * @param  Builder<JobSharePairInvitation>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
