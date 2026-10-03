<?php

namespace App\Http\Resources\Api\V1;

use App\Models\JobOffer;
use App\Models\JobSharePair;
use App\Services\JobSharing\PairPresenter;
use App\Services\JobSharing\Workday;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Job-sharing offer in the hub with the candidate's match score and her active pair for it.
 *
 * @property array{offer: JobOffer, score: int, active_pair: JobSharePair|null} $resource
 */
class PairOfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $offer = $this->resource['offer'];
        $activePair = $this->resource['active_pair'];

        return [
            'id' => $offer->id,
            'title' => $offer->title,
            'company' => $offer->company->name,
            'city' => $offer->city,
            'work_mode_label' => $offer->work_mode->label(),
            'score' => $this->resource['score'],
            'job_share' => Workday::presentOffer($offer),
            'active_pair_id' => $activePair?->id,
            'active_pair_state' => $activePair ? app(PairPresenter::class)->viewerState($activePair) : null,
        ];
    }
}
