<?php

namespace App\Models;

use App\Enums\EmploymentFraction;
use App\Enums\OfferStatus;
use App\Enums\SkillImportance;
use App\Enums\WorkMode;
use Carbon\CarbonImmutable;
use Database\Factories\JobOfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $company_id
 * @property string $title
 * @property string|null $city
 * @property WorkMode $work_mode
 * @property EmploymentFraction $employment_fraction
 * @property int|null $salary_min
 * @property int|null $salary_max
 * @property CarbonImmutable $start_date
 * @property string|null $description
 * @property bool $flexible_hours
 * @property bool $fixed_meeting_hours
 * @property bool $childcare_subsidy
 * @property OfferStatus $status
 * @property CarbonImmutable|null $published_at
 */
#[Fillable([
    'title', 'city', 'work_mode', 'employment_fraction', 'salary_min', 'salary_max', 'start_date', 'description',
    'flexible_hours', 'fixed_meeting_hours', 'childcare_subsidy', 'status', 'published_at',
])]
class JobOffer extends Model
{
    /** @use HasFactory<JobOfferFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsToMany<Skill, $this>
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class)->withPivot('importance')->withTimestamps();
    }

    /**
     * @return BelongsToMany<Skill, $this>
     */
    public function requiredSkills(): BelongsToMany
    {
        return $this->skills()->wherePivot('importance', SkillImportance::Required->value);
    }

    /**
     * @return BelongsToMany<Skill, $this>
     */
    public function niceToHaveSkills(): BelongsToMany
    {
        return $this->skills()->wherePivot('importance', SkillImportance::NiceToHave->value);
    }

    /**
     * @return HasMany<CandidateDecision, $this>
     */
    public function decisions(): HasMany
    {
        return $this->hasMany(CandidateDecision::class);
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * @return HasMany<OfferInterest, $this>
     */
    public function interests(): HasMany
    {
        return $this->hasMany(OfferInterest::class);
    }

    /**
     * @param  Builder<JobOffer>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', OfferStatus::Published);
    }

    public function isPublished(): bool
    {
        return $this->status === OfferStatus::Published;
    }

    /**
     * Whether the offer meets the "przyjazna rodzicom" badge conditions.
     */
    public function isParentFriendly(): bool
    {
        return $this->salary_min !== null
            && $this->salary_max !== null
            && $this->flexible_hours
            && $this->company->approvedReviews()->exists();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'work_mode' => WorkMode::class,
            'employment_fraction' => EmploymentFraction::class,
            'start_date' => 'date',
            'flexible_hours' => 'boolean',
            'fixed_meeting_hours' => 'boolean',
            'childcare_subsidy' => 'boolean',
            'status' => OfferStatus::class,
            'published_at' => 'datetime',
        ];
    }
}
