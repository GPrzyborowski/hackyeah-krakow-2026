<?php

namespace App\Http\Resources;

use App\Models\JobOffer;
use App\Services\Matching\CvInsights;
use App\Services\Matching\MatchResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The candidate's private "Analiza CV" built by CvInsights (shared by the web page and the mobile API).
 *
 * @property array{
 *     strengths: list<array{id: int, name: string, demand_count: int}>,
 *     positions: array{suggested: list<array{title: string, score: int}>, offers: list<array{title: string, score: int}>},
 *     missing_skills: list<array{id: int, name: string, offers_count: int, average_gain: int}>,
 *     offers: list<array{offer: JobOffer, match: MatchResult}>,
 *     stats: array{offers_total: int, matching_offers: int, average_score: int, can_start_on_time: int}
 * } $resource
 */
class CvAnalysisResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'stats' => [
                ...$this->resource['stats'],
                'good_match_threshold' => CvInsights::GOOD_MATCH_THRESHOLD,
            ],
            'strengths' => $this->resource['strengths'],
            'positions' => $this->resource['positions'],
            'missing_skills' => $this->resource['missing_skills'],
            'offers' => array_map(fn (array $row): array => $this->presentOffer($row['offer'], $row['match']), $this->resource['offers']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentOffer(JobOffer $offer, MatchResult $match): array
    {
        return [
            'id' => $offer->id,
            'title' => $offer->title,
            'company' => $offer->company->name,
            'city' => $offer->city,
            'work_mode_label' => $offer->work_mode->label(),
            'employment_fraction_label' => $offer->employment_fraction->label(),
            'start_date' => $offer->start_date->toDateString(),
            'score' => $match->score,
            'matched_skills' => [...$match->matchedRequired, ...$match->matchedNiceToHave],
            'missing_required' => $match->missingRequired,
            'missing_nice_to_have' => $match->missingNiceToHave,
            'start_date_compatible' => $match->startDateCompatible,
        ];
    }
}
