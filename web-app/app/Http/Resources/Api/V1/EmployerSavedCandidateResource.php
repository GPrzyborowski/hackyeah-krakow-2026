<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CandidateProfile;
use App\Services\Matching\MatchResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A compact anonymous entry of the "saved for later" strip.
 *
 * @property CandidateProfile $resource
 */
class EmployerSavedCandidateResource extends JsonResource
{
    public function __construct(CandidateProfile $resource, public MatchResult $match)
    {
        parent::__construct($resource);
    }

    /**
     * @return array{id: int, anonymous_name: string, initial: string, available_from: string|null, score: int}
     */
    public function toArray(Request $request): array
    {
        $anonymousName = $this->resource->anonymousName();

        return [
            'id' => $this->resource->id,
            'anonymous_name' => $anonymousName,
            'initial' => mb_strtoupper(mb_substr($anonymousName, 0, 1)),
            'available_from' => $this->resource->available_from?->toDateString(),
            'score' => $this->match->score,
        ];
    }
}
