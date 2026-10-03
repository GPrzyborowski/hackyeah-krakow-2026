<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\JobShareMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Message in the private chat of a job-sharing pair (candidates only, not visible to the employer).
 *
 * @property int $id
 * @property int $job_share_pair_id
 * @property int $user_id
 * @property string $body
 * @property CarbonImmutable $created_at
 */
#[Fillable(['job_share_pair_id', 'user_id', 'body'])]
class JobShareMessage extends Model
{
    /** @use HasFactory<JobShareMessageFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<JobSharePair, $this>
     */
    public function pair(): BelongsTo
    {
        return $this->belongsTo(JobSharePair::class, 'job_share_pair_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
