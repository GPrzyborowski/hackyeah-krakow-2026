<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\JobSharePairStatus;
use App\Models\CandidateProfile;
use App\Models\JobSharePair;
use App\Services\Matching\MatchResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * A job-sharing pair as the offer's company reviews it: both members anonymous, combined skill coverage, day split.
 *
 * @property array{pair: JobSharePair, members: Collection<int, array{candidate: CandidateProfile, match: MatchResult}>, coverage: array{covered: list<string>, missing: list<string>, percent: int}, schedule: list<array{candidate_profile_id: int, starts_at: string, ends_at: string}>} $resource
 */
class EmployerPairResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pair = $this->resource['pair'];

        return [
            'id' => $pair->id,
            'offer_id' => $pair->job_offer_id,
            'status' => $pair->status->value,
            'status_label' => match ($pair->status) {
                JobSharePairStatus::Submitted => 'Czeka na decyzję',
                JobSharePairStatus::Invited => 'Zaproszona',
                JobSharePairStatus::Rejected => 'Odrzucona',
                JobSharePairStatus::Hired => 'Zatrudniona',
                JobSharePairStatus::Declined => 'Odrzucona przez jedną z kandydatek',
                default => null,
            },
            'submitted_at' => $pair->submitted_at?->toIso8601String(),
            'members' => $this->resource['members']
                ->map(fn (array $member): array => (new EmployerCandidateCardResource($member['candidate'], $member['match']))->resolve($request))
                ->values()
                ->all(),
            'coverage' => $this->resource['coverage'],
            'schedule' => $this->resource['schedule'],
        ];
    }
}
