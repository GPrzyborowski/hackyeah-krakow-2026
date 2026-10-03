<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use App\Policies\CompanyTeamPolicy;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $nip
 * @property string|null $city
 * @property string|null $description
 * @property Carbon|null $verified_at
 * @property int|null $verified_by_user_id
 */
#[Fillable(['name', 'nip', 'city', 'description'])]
#[UsePolicy(CompanyTeamPolicy::class)]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }

    /**
     * Whether mumjobs checked the company's NIP (admin verification).
     */
    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Mark the company as verified by the given administrator.
     */
    public function markVerifiedBy(User $admin): void
    {
        $this->forceFill(['verified_at' => now(), 'verified_by_user_id' => $admin->id])->save();
    }

    /**
     * Withdraw the verification.
     */
    public function revokeVerification(): void
    {
        $this->forceFill(['verified_at' => null, 'verified_by_user_id' => null])->save();
    }

    /**
     * @param  Builder<Company>  $query
     */
    public function scopeVerified(Builder $query): void
    {
        $query->whereNotNull('verified_at');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<CompanyInvitation, $this>
     */
    public function teamInvitations(): HasMany
    {
        return $this->hasMany(CompanyInvitation::class);
    }

    /**
     * @return HasMany<JobOffer, $this>
     */
    public function jobOffers(): HasMany
    {
        return $this->hasMany(JobOffer::class);
    }

    /**
     * @return HasMany<CompanyReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(CompanyReview::class);
    }

    /**
     * @return HasMany<CompanyReview, $this>
     */
    public function approvedReviews(): HasMany
    {
        return $this->reviews()->where('status', ReviewStatus::Approved);
    }

    /**
     * Average of all approved review category ratings, rounded to one decimal.
     */
    public function averageRating(): ?float
    {
        $reviews = $this->relationLoaded('approvedReviews') ? $this->approvedReviews : $this->approvedReviews()->get();

        if ($reviews->isEmpty()) {
            return null;
        }

        return round($reviews->avg(fn (CompanyReview $review): float => $review->overallRating()), 1);
    }
}
