<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CandidateProfile;
use App\Models\JobSharePair;
use App\Services\JobSharing\PairPresenter;
use App\Services\JobSharing\Workday;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pair (or pair invitation) in the job-sharing hub, with the partner shown anonymously.
 * Expects `jobOffer.company` and `members.user` to be loaded.
 *
 * @property JobSharePair $resource
 */
class PairListItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pair = $this->resource;
        $viewerProfileId = $request->user()?->candidateProfile?->id;
        $partner = app(PairPresenter::class)->members($pair)
            ->first(fn (CandidateProfile $member): bool => $member->id !== $viewerProfileId);

        return [
            'id' => $pair->id,
            'status' => $pair->status->value,
            'status_label' => $pair->status->label(),
            'offer' => [
                'id' => $pair->jobOffer->id,
                'title' => $pair->jobOffer->title,
                'company' => $pair->jobOffer->company->name,
                'job_share' => Workday::presentOffer($pair->jobOffer),
            ],
            'partner' => $partner ? [
                'display_name' => $partner->anonymousName(),
                'headline' => $partner->headline,
                'preferred_day_part_label' => $partner->preferred_day_part?->label(),
            ] : null,
        ];
    }
}
