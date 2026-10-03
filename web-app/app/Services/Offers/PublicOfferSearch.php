<?php

namespace App\Services\Offers;

use App\Enums\EmploymentFraction;
use App\Enums\ReviewStatus;
use App\Enums\WorkMode;
use App\Models\CompanyReview;
use App\Models\JobOffer;
use App\Services\Matching\MatchScorer;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Public, match-free search of published offers (web offers page and the mobile API).
 */
class PublicOfferSearch
{
    /** @var list<string> Sort keys: newest published, best company rating (unrated last), soonest start. */
    public const array SORTS = ['newest', 'rating', 'start_date'];

    public const string DEFAULT_SORT = 'newest';

    /**
     * Normalise filters from the query string, silently dropping unknown values.
     *
     * @return array{q: string, location: string, work_mode: list<string>, fraction: list<string>, flexible: bool, childcare_subsidy: bool, nursery_nearby: bool, with_reviews: bool, job_share: bool, verified_only: bool, start_from: string|null, sort: string}
     */
    public function filters(Request $request): array
    {
        $values = fn (string $key, callable $isValid): array => array_values(collect((array) $request->query($key, []))
            ->filter(fn (mixed $value): bool => is_string($value) && $isValid($value))
            ->unique()
            ->all());

        return [
            'q' => trim((string) $request->string('q')),
            'location' => trim((string) $request->string('location')),
            'work_mode' => $values('work_mode', fn (string $value): bool => WorkMode::tryFrom($value) !== null),
            'fraction' => $values('fraction', fn (string $value): bool => EmploymentFraction::tryFrom($value) !== null),
            'flexible' => $request->boolean('flexible'),
            'childcare_subsidy' => $request->boolean('childcare_subsidy'),
            'nursery_nearby' => $request->boolean('nursery_nearby'),
            'with_reviews' => $request->boolean('with_reviews'),
            'job_share' => $request->boolean('job_share'),
            'verified_only' => $request->boolean('verified_only'),
            'start_from' => $this->validDate($request->query('start_from')),
            'sort' => in_array($request->query('sort'), self::SORTS, true) ? (string) $request->query('sort') : self::DEFAULT_SORT,
        ];
    }

    /**
     * A strict Y-m-d date string, or null for anything else.
     */
    private function validDate(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = Carbon::createFromFormat('!Y-m-d', $value);

        return $date !== null && $date->format('Y-m-d') === $value ? $value : null;
    }

    /**
     * Published offers matching the filters, sorted (newest first by default), with the company and its approved reviews loaded.
     *
     * @param  array{q: string, location: string, work_mode: list<string>, fraction: list<string>, flexible: bool, childcare_subsidy: bool, nursery_nearby: bool, with_reviews: bool, job_share: bool, verified_only: bool, start_from: string|null, sort: string}  $filters
     * @return Builder<JobOffer>
     */
    public function query(array $filters): Builder
    {
        return JobOffer::query()
            ->published()
            ->with(['company' => fn ($query) => $query->select(['id', 'name', 'city', 'verified_at'])->with(['approvedReviews' => fn ($query) => $query->orderBy('id')])])
            ->when($filters['q'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    $query->where('title', 'like', "%{$filters['q']}%")
                        ->orWhereHas('company', fn (Builder $query) => $query->where('name', 'like', "%{$filters['q']}%"))
                        ->orWhereHas('skills', fn (Builder $query) => $query->where('name', 'like', "%{$filters['q']}%"));
                });
            })
            ->when($filters['location'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    $query->where('city', 'like', "%{$filters['location']}%");

                    if (str_contains(mb_strtolower($filters['location']), 'zdal')) {
                        $query->orWhere('work_mode', WorkMode::Remote);
                    }
                });
            })
            ->when($filters['work_mode'] !== [], fn (Builder $query) => $query->whereIn('work_mode', $filters['work_mode']))
            ->when($filters['fraction'] !== [], fn (Builder $query) => $query->whereIn('employment_fraction', $filters['fraction']))
            ->when($filters['flexible'], fn (Builder $query) => $query->where('flexible_hours', true))
            ->when($filters['childcare_subsidy'], fn (Builder $query) => $query->where('childcare_subsidy', true))
            ->when($filters['nursery_nearby'], fn (Builder $query) => $query->withNurseryNearby())
            ->when($filters['with_reviews'], fn (Builder $query) => $query->whereHas('company.approvedReviews'))
            ->when($filters['job_share'], fn (Builder $query) => $query->where('is_job_share', true))
            ->when($filters['verified_only'], fn (Builder $query) => $query->whereHas('company', fn (Builder $query) => $query->whereNotNull('verified_at')))
            ->when($filters['start_from'], fn (Builder $query, string $date) => $query->whereDate(
                'start_date',
                '>=',
                Carbon::parse($date)->subDays(MatchScorer::START_DATE_TOLERANCE_DAYS)->toDateString(),
            ))
            ->when($filters['sort'] === 'rating', fn (Builder $query) => $query->orderByDesc(
                CompanyReview::query()
                    ->selectRaw('avg((rating_return + rating_flexibility + rating_no_pregnancy_questions) / 3)')
                    ->whereColumn('company_reviews.company_id', 'job_offers.company_id')
                    ->where('status', ReviewStatus::Approved),
            ))
            ->when($filters['sort'] === 'start_date', fn (Builder $query) => $query->orderBy('start_date'))
            ->latest('published_at')
            ->latest('id');
    }
}
