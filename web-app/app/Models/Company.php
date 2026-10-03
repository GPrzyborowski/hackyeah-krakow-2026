<?php

namespace App\Models;

use App\Enums\ReviewStatus;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $nip
 * @property string|null $city
 * @property string|null $description
 */
#[Fillable(['name', 'nip', 'city', 'description'])]
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    /**
     * @return HasMany<User, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(User::class);
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
