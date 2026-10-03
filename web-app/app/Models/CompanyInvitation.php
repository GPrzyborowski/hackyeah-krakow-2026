<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\CompanyInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An e-mailed invitation for a recruiter to join a company team; the token in the link proves e-mail ownership.
 *
 * @property int $id
 * @property int $company_id
 * @property string $email
 * @property string $token
 * @property int|null $invited_by_user_id
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Company $company
 * @property-read User|null $invitedBy
 */
#[Fillable(['company_id', 'email', 'token', 'invited_by_user_id', 'accepted_at', 'expires_at'])]
#[Hidden(['token'])]
class CompanyInvitation extends Model
{
    /** @use HasFactory<CompanyInvitationFactory> */
    use HasFactory;

    public const int VALID_DAYS = 7;

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Not accepted yet (expired ones included, so the team can see and revoke them).
     *
     * @param  Builder<CompanyInvitation>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at');
    }

    /**
     * Not accepted and still within its validity window.
     *
     * @param  Builder<CompanyInvitation>  $query
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
