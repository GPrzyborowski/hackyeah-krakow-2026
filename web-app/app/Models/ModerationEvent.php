<?php

namespace App\Models;

use App\Enums\ModerationContext;
use Carbon\CarbonImmutable;
use Database\Factories\ModerationEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A text blocked by the message moderator; kept for 90 days (momjobs:prune-moderation-events).
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $company_id
 * @property ModerationContext $context
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $excerpt
 * @property string|null $reason
 * @property string|null $suggestion
 * @property string $moderator
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['user_id', 'company_id', 'context', 'subject_type', 'subject_id', 'excerpt', 'reason', 'suggestion', 'moderator', 'created_at'])]
class ModerationEvent extends Model
{
    /** @use HasFactory<ModerationEventFactory> */
    use HasFactory;

    public const int EXCERPT_LENGTH = 300;

    public const int RETENTION_DAYS = 90;

    public const string MODERATOR_KEYWORD = 'keyword';

    public const string MODERATOR_CLAUDE = 'claude';

    public const null UPDATED_AT = null;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => ModerationContext::class,
            'created_at' => 'datetime',
        ];
    }
}
