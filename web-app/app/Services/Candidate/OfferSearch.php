<?php

namespace App\Services\Candidate;

use App\Enums\WorkMode;
use App\Http\Requests\Candidate\OfferFilterRequest;
use App\Models\CandidateProfile;
use App\Models\JobOffer;
use App\Services\Matching\MatchResult;
use App\Services\Matching\MatchScorer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Candidate offer search: filters published offers and ranks them by match (or publication date).
 */
class OfferSearch
{
    public function __construct(private readonly MatchScorer $matchScorer) {}

    /**
     * Normalised filters; "start_from" defaults to the candidate's availability unless explicitly sent (even empty).
     *
     * @return array{q: string, location: string, work_modes: list<string>, employment_fractions: list<string>, flexible_hours: bool, childcare_subsidy: bool, nursery_nearby: bool, with_reviews: bool, job_share: bool, saved: bool, start_from: string|null, sort: string}
     */
    public function filtersFrom(OfferFilterRequest $request, CandidateProfile $profile): array
    {
        $filters = $request->validated();

        return [
            'q' => $filters['q'] ?? '',
            'location' => $filters['location'] ?? '',
            'work_modes' => array_values($filters['work_modes'] ?? []),
            'employment_fractions' => array_values($filters['employment_fractions'] ?? []),
            'flexible_hours' => $request->boolean('flexible_hours'),
            'childcare_subsidy' => $request->boolean('childcare_subsidy'),
            'nursery_nearby' => $request->boolean('nursery_nearby'),
            'with_reviews' => $request->boolean('with_reviews'),
            'job_share' => $request->boolean('job_share'),
            'saved' => $request->boolean('saved'),
            'start_from' => $request->has('start_from')
                ? ($filters['start_from'] ?? null)
                : $profile->available_from?->toDateString(),
            'sort' => $filters['sort'] ?? 'match',
        ];
    }

    /**
     * @param  array{q: string, location: string, work_modes: list<string>, employment_fractions: list<string>, flexible_hours: bool, childcare_subsidy: bool, nursery_nearby: bool, with_reviews: bool, job_share: bool, saved: bool, start_from: string|null, sort: string}  $filters
     * @return Collection<int, array{offer: JobOffer, match: MatchResult}>
     */
    public function rank(CandidateProfile $profile, array $filters): Collection
    {
        $query = JobOffer::query()
            ->when($filters['q'], function (Builder $query, string $term): void {
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('title', 'like', "%{$term}%")
                        ->orWhereHas('skills', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"));
                });
            })
            ->when($filters['location'], function (Builder $query, string $location): void {
                if (Str::contains(Str::lower($location), 'zdaln')) {
                    $query->where('work_mode', WorkMode::Remote);
                } else {
                    $query->where('city', 'like', "%{$location}%");
                }
            })
            ->when($filters['work_modes'], fn (Builder $query, array $modes) => $query->whereIn('work_mode', $modes))
            ->when($filters['employment_fractions'], fn (Builder $query, array $fractions) => $query->whereIn('employment_fraction', $fractions))
            ->when($filters['flexible_hours'], fn (Builder $query) => $query->where('flexible_hours', true))
            ->when($filters['childcare_subsidy'], fn (Builder $query) => $query->where('childcare_subsidy', true))
            ->when($filters['nursery_nearby'], fn (Builder $query) => $query->withNurseryNearby())
            ->when($filters['with_reviews'], fn (Builder $query) => $query->whereHas('company.approvedReviews'))
            ->when($filters['job_share'], fn (Builder $query) => $query->where('is_job_share', true))
            ->when($filters['saved'], fn (Builder $query) => $query->whereIn('id', $profile->savedOffers()->select('job_offers.id')))
            ->when($filters['start_from'], fn (Builder $query, string $date) => $query->whereDate(
                'start_date',
                '>=',
                Carbon::parse($date)->subDays(MatchScorer::START_DATE_TOLERANCE_DAYS)->toDateString(),
            ));

        $ranked = $this->matchScorer->rankOffersFor($profile, $query);

        if ($filters['sort'] === 'newest') {
            $ranked = $ranked->sortByDesc(fn (array $row): int => $row['offer']->published_at?->getTimestamp() ?? 0)->values();
        }

        return $ranked;
    }
}
