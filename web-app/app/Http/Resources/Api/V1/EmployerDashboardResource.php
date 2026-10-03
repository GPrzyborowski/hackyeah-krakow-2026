<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Employer "Start" screen: company header, KPIs, the funnel per published offer, to-do items and recent activity.
 * The web dashboard renders the very same shape.
 *
 * @property array{greeting: array{first_name: string}, company: array<string, mixed>, stats: array<string, mixed>, funnel: list<array<string, mixed>>, todo: list<array<string, mixed>>, reviews: array{approved_count: int, average_rating: float|null}, activity: list<array<string, mixed>>} $resource
 */
class EmployerDashboardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'greeting' => $this->resource['greeting'],
            'company' => $this->resource['company'],
            'stats' => $this->resource['stats'],
            'funnel' => $this->resource['funnel'],
            'todo' => $this->resource['todo'],
            'reviews' => $this->resource['reviews'],
            'activity' => $this->resource['activity'],
        ];
    }
}
