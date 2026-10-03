<?php

namespace App\Services\Offers;

use App\Enums\EmploymentFraction;
use App\Enums\WorkMode;
use App\Models\JobOffer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Public, match-free search of published offers (web offers page and the mobile API).
 */
class PublicOfferSearch
{
    /**
     * Normalise filters from the query string, silently dropping unknown values.
     *
     * @return array{q: string, location: string, work_mode: list<string>, fraction: list<string>, flexible: bool, nursery_nearby: bool, job_share: bool, verified_only: bool}
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
            'nursery_nearby' => $request->boolean('nursery_nearby'),
            'job_share' => $request->boolean('job_share'),
            'verified_only' => $request->boolean('verified_only'),
        ];
    }

    /**
     * Published offers matching the filters, newest first, with the company and its approved reviews loaded.
     *
     * @param  array{q: string, location: string, work_mode: list<string>, fraction: list<string>, flexible: bool, nursery_nearby: bool, job_share: bool, verified_only: bool}  $filters
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
            ->when($filters['nursery_nearby'], fn (Builder $query) => $query->withNurseryNearby())
            ->when($filters['job_share'], fn (Builder $query) => $query->where('is_job_share', true))
            ->when($filters['verified_only'], fn (Builder $query) => $query->whereHas('company', fn (Builder $query) => $query->whereNotNull('verified_at')))
            ->latest('published_at')
            ->latest('id');
    }
}
