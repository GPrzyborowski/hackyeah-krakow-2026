<?php

namespace App\Http\Controllers\Public;

use App\Enums\EmploymentFraction;
use App\Enums\WorkMode;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\PresentsCompanyRatings;
use App\Models\JobOffer;
use App\Services\JobSharing\Workday;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OfferController extends Controller
{
    use PresentsCompanyRatings;

    /**
     * Public, match-free list of published offers with simple query-string filters.
     */
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $offers = JobOffer::query()
            ->published()
            ->with(['company' => fn ($query) => $query->select(['id', 'name', 'city'])->with(['approvedReviews' => fn ($query) => $query->orderBy('id')])])
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
            ->when($filters['job_share'], fn (Builder $query) => $query->where('is_job_share', true))
            ->latest('published_at')
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (JobOffer $offer): array => $this->present($offer));

        return Inertia::render('public/offers/Index', [
            'offers' => $offers,
            'filters' => $filters,
            'workModes' => collect(WorkMode::cases())->map(fn (WorkMode $mode): array => ['value' => $mode->value, 'label' => $mode->label()]),
            'fractions' => collect(EmploymentFraction::cases())->map(fn (EmploymentFraction $fraction): array => ['value' => $fraction->value, 'label' => $fraction->label()]),
        ]);
    }

    /**
     * Normalise filters from the query string, silently dropping unknown values.
     *
     * @return array{q: string, location: string, work_mode: list<string>, fraction: list<string>, flexible: bool, job_share: bool}
     */
    private function filters(Request $request): array
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
            'job_share' => $request->boolean('job_share'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(JobOffer $offer): array
    {
        $company = $offer->company;

        return [
            'id' => $offer->id,
            'title' => $offer->title,
            'city' => $offer->city,
            'work_mode' => $offer->work_mode->value,
            'work_mode_label' => $offer->work_mode->label(),
            'employment_fraction_label' => $offer->employment_fraction->label(),
            'salary_min' => $offer->salary_min,
            'salary_max' => $offer->salary_max,
            'start_date' => $offer->start_date->toDateString(),
            'flexible_hours' => $offer->flexible_hours,
            'fixed_meeting_hours' => $offer->fixed_meeting_hours,
            'childcare_subsidy' => $offer->childcare_subsidy,
            'is_parent_friendly' => $offer->isParentFriendly(),
            'job_share' => Workday::presentOffer($offer),
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'rating' => $company->averageRating(),
                'featured_quote' => $this->featuredQuote($company),
            ],
        ];
    }
}
