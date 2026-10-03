<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CandidateDecision;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The stored decision about a candidate for an offer.
 *
 * @property CandidateDecision $resource
 */
class EmployerCandidateDecisionResource extends JsonResource
{
    /**
     * @return array{offer_id: int, candidate_id: int, decision: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'offer_id' => $this->resource->job_offer_id,
            'candidate_id' => $this->resource->candidate_profile_id,
            'decision' => $this->resource->decision->value,
        ];
    }
}
