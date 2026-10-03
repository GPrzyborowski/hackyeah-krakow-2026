<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Candidate home screen: greeting (with a message for her private stage), private return calendar, invitation counters,
 * the best matching offers and blog articles recommended for her stage.
 *
 * @property array{first_name: string, stage_message: string|null, published: bool, onboarding_step: int, calendar: array<string, mixed>, invitations: array{pending_count: int, company_names: list<string>}, pair_invitations_count: int, saved_offers_count: int, top_offers: list<OfferResource>, recommended_articles: AnonymousResourceCollection} $resource
 */
class HomeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'greeting' => [
                'first_name' => $this->resource['first_name'],
                'stage_message' => $this->resource['stage_message'],
            ],
            'profile' => [
                'published' => $this->resource['published'],
                'onboarding_step' => $this->resource['onboarding_step'],
            ],
            'calendar' => $this->resource['calendar'],
            'invitations' => $this->resource['invitations'],
            'pair_invitations_count' => $this->resource['pair_invitations_count'],
            'saved_offers_count' => $this->resource['saved_offers_count'],
            'top_offers' => $this->resource['top_offers'],
            'recommended_articles' => $this->resource['recommended_articles'],
        ];
    }
}
