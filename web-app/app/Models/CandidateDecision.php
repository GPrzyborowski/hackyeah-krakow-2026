<?php

namespace App\Models;

use App\Enums\CandidateDecisionType;
use Database\Factories\CandidateDecisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $job_offer_id
 * @property int $candidate_profile_id
 * @property CandidateDecisionType $decision
 */
#[Fillable(['job_offer_id', 'candidate_profile_id', 'decision'])]
class CandidateDecision extends Model
{
    /** @use HasFactory<CandidateDecisionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<JobOffer, $this>
     */
    public function jobOffer(): BelongsTo
    {
        return $this->belongsTo(JobOffer::class);
    }

    /**
     * @return BelongsTo<CandidateProfile, $this>
     */
    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decision' => CandidateDecisionType::class,
        ];
    }
}
