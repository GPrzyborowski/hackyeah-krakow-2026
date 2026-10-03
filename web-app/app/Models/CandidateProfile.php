<?php

namespace App\Models;

use App\Enums\CvStatus;
use App\Enums\DayPart;
use Carbon\CarbonImmutable;
use Database\Factories\CandidateProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $headline
 * @property int|null $years_of_experience
 * @property string|null $city
 * @property string|null $ai_summary
 * @property CarbonImmutable|null $available_from
 * @property CarbonImmutable|null $leave_starts_on
 * @property CarbonImmutable|null $due_date
 * @property list<string>|null $work_modes
 * @property list<string>|null $employment_fractions
 * @property bool $wants_flexible_hours
 * @property bool $open_to_job_sharing
 * @property DayPart|null $preferred_day_part
 * @property bool $show_availability_instead_of_gap
 * @property int|null $hidden_from_company_id
 * @property bool $allow_direct_messages
 * @property bool $job_alerts_enabled
 * @property int $onboarding_step
 * @property string|null $cv_path
 * @property string|null $cv_original_name
 * @property CvStatus|null $cv_status
 * @property string|null $cv_text
 * @property string|null $photo_path
 * @property string|null $phone
 * @property list<array{title: string, score: int}>|null $suggested_positions
 * @property CarbonImmutable|null $published_at
 */
#[Fillable([
    'headline', 'years_of_experience', 'city', 'ai_summary', 'available_from', 'leave_starts_on', 'due_date',
    'work_modes', 'employment_fractions', 'wants_flexible_hours', 'open_to_job_sharing', 'preferred_day_part',
    'show_availability_instead_of_gap', 'hidden_from_company_id', 'allow_direct_messages', 'job_alerts_enabled', 'onboarding_step',
    'cv_path', 'cv_original_name', 'cv_status', 'cv_text', 'suggested_positions', 'published_at',
    'phone',
])]
class CandidateProfile extends Model
{
    /** @use HasFactory<CandidateProfileFactory> */
    use HasFactory;

    /**
     * Mirrors the column defaults so new, unsaved profiles behave like stored ones.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'job_alerts_enabled' => true,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * All skills attached to the profile, including unconfirmed AI suggestions.
     *
     * @return BelongsToMany<Skill, $this>
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class)->withPivot(['source', 'confirmed_at'])->withTimestamps();
    }

    /**
     * Skills the candidate confirmed; the only ones employers may see or match against.
     *
     * @return BelongsToMany<Skill, $this>
     */
    public function confirmedSkills(): BelongsToMany
    {
        return $this->skills()->wherePivotNotNull('confirmed_at');
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
     * Offers already sent to the candidate in a job alert e-mail (each offer is alerted once).
     *
     * @return BelongsToMany<JobOffer, $this>
     */
    public function alertedOffers(): BelongsToMany
    {
        return $this->belongsToMany(JobOffer::class, 'job_alert_deliveries')->withPivot('sent_at');
    }

    /**
     * Offers the candidate bookmarked with "Zapisz".
     *
     * @return BelongsToMany<JobOffer, $this>
     */
    public function savedOffers(): BelongsToMany
    {
        return $this->belongsToMany(JobOffer::class, 'saved_offers')->withTimestamps();
    }

    /**
     * @return BelongsToMany<JobSharePair, $this>
     */
    public function jobSharePairs(): BelongsToMany
    {
        return $this->belongsToMany(JobSharePair::class, 'job_share_members')
            ->withPivot(['is_initiator', 'accepted_at', 'schedule_confirmed_at'])
            ->withTimestamps();
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function hiddenFromCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'hidden_from_company_id');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /**
     * Profiles visible to employers.
     *
     * @param  Builder<CandidateProfile>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at');
    }

    /**
     * Published profiles that the given company may see (respects "hide from current employer").
     *
     * @param  Builder<CandidateProfile>  $query
     */
    public function scopeVisibleTo(Builder $query, Company $company): void
    {
        $query->published()->where(function (Builder $query) use ($company): void {
            $query->whereNull('hidden_from_company_id')->orWhere('hidden_from_company_id', '!=', $company->id);
        });
    }

    public function hasPhoto(): bool
    {
        return $this->photo_path !== null;
    }

    /**
     * URL of the authorised photo endpoint (web session or API token); the version query busts caches after a change.
     * Only put it in payloads for the candidate herself or a company whose invitation she accepted.
     */
    public function photoUrl(bool $forApi = false): ?string
    {
        if (! $this->hasPhoto()) {
            return null;
        }

        return route($forApi ? 'api.v1.candidate-photos.show' : 'candidate-photos.show', [
            'profile' => $this->id,
            'v' => substr(md5((string) $this->photo_path), 0, 8),
        ]);
    }

    /**
     * Display name shown to employers before an invitation is accepted, e.g. "Marta K.".
     */
    public function anonymousName(): string
    {
        $parts = preg_split('/\s+/', trim($this->user->name)) ?: [];
        $firstName = $parts[0] ?? '';
        $lastName = $parts[1] ?? null;

        return $lastName ? $firstName.' '.mb_substr($lastName, 0, 1).'.' : $firstName;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'available_from' => 'date',
            'leave_starts_on' => 'date',
            'due_date' => 'date',
            'work_modes' => 'array',
            'employment_fractions' => 'array',
            'wants_flexible_hours' => 'boolean',
            'open_to_job_sharing' => 'boolean',
            'preferred_day_part' => DayPart::class,
            'show_availability_instead_of_gap' => 'boolean',
            'allow_direct_messages' => 'boolean',
            'job_alerts_enabled' => 'boolean',
            'cv_status' => CvStatus::class,
            'suggested_positions' => 'array',
            'published_at' => 'datetime',
        ];
    }
}
