<?php

namespace App\Models;

use App\Enums\JobSharePairStatus;
use Carbon\CarbonImmutable;
use Database\Factories\JobSharePairFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Two candidates applying together for one job-sharing offer, each covering part of the workday.
 *
 * @property int $id
 * @property int $job_offer_id
 * @property JobSharePairStatus $status
 * @property list<array{candidate_profile_id: int, starts_at: string, ends_at: string}>|null $proposed_schedule
 * @property CarbonImmutable|null $submitted_at
 */
#[Fillable(['job_offer_id', 'status', 'proposed_schedule', 'submitted_at'])]
class JobSharePair extends Model
{
    /** @use HasFactory<JobSharePairFactory> */
    use HasFactory;

    public const int MAX_MEMBERS = 2;

    /**
     * @return BelongsTo<JobOffer, $this>
     */
    public function jobOffer(): BelongsTo
    {
        return $this->belongsTo(JobOffer::class);
    }

    /**
     * @return BelongsToMany<CandidateProfile, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(CandidateProfile::class, 'job_share_members')
            ->withPivot(['is_initiator', 'accepted_at', 'schedule_confirmed_at'])
            ->withTimestamps();
    }

    /**
     * Members who accepted being in the pair.
     *
     * @return BelongsToMany<CandidateProfile, $this>
     */
    public function acceptedMembers(): BelongsToMany
    {
        return $this->members()->wherePivotNotNull('accepted_at');
    }

    /**
     * @return HasMany<JobShareMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(JobShareMessage::class);
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * Pairs whose every member is visible to the company: none of them unpublished her profile or hid it from that company.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleToCompany(Builder $query, Company $company): void
    {
        $query->whereDoesntHave('members', function (Builder $members) use ($company): void {
            $members->whereNot(fn (Builder $member) => $member->visibleTo($company));
        });
    }

    public function hasMember(CandidateProfile $candidate): bool
    {
        return $this->members()->whereKey($candidate->id)->exists();
    }

    public function hasAcceptedMember(CandidateProfile $candidate): bool
    {
        return $this->acceptedMembers()->whereKey($candidate->id)->exists();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => JobSharePairStatus::class,
            'proposed_schedule' => 'array',
            'submitted_at' => 'datetime',
        ];
    }
}
