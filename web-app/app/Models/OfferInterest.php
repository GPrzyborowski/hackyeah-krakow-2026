<?php

namespace App\Models;

use Database\Factories\OfferInterestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $job_offer_id
 * @property int $candidate_profile_id
 */
#[Fillable(['job_offer_id', 'candidate_profile_id'])]
class OfferInterest extends Model
{
    /** @use HasFactory<OfferInterestFactory> */
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
}
