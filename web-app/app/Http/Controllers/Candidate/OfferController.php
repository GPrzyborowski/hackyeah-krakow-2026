<?php

namespace App\Http\Controllers\Candidate;

use App\Enums\EmploymentFraction;
use App\Enums\WorkMode;
use App\Http\Controllers\Candidate\Concerns\PresentsOffers;
use App\Http\Controllers\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\OfferFilterRequest;
use App\Models\CompanyReview;
use App\Models\JobOffer;
use App\Models\OfferInterest;
use App\Services\Matching\MatchScorer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class OfferController extends Controller
{
    use PresentsOffers, ResolvesCandidateProfile;

    /**
     * Published offers with search, filters and match ranking.
     */
    public function index(OfferFilterRequest $request, MatchScorer $matchScorer): Response
    {
        $profile = $this->candidateProfile($request);
        $filters = $request->validated();
        $startFrom = $request->has('start_from')
            ? ($filters['start_from'] ?? null)
            : $profile->available_from?->toDateString();
        $sort = $filters['sort'] ?? 'match';

        $query = JobOffer::query()
            ->when($filters['q'] ?? null, function (Builder $query, string $term): void {
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('title', 'like', "%{$term}%")
                        ->orWhereHas('skills', fn (Builder $query) => $query->where('name', 'like', "%{$term}%"));
                });
            })
            ->when($filters['location'] ?? null, function (Builder $query, string $location): void {
                if (Str::contains(Str::lower($location), 'zdaln')) {
                    $query->where('work_mode', WorkMode::Remote);
                } else {
                    $query->where('city', 'like', "%{$location}%");
                }
            })
            ->when($filters['work_modes'] ?? [], fn (Builder $query, array $modes) => $query->whereIn('work_mode', $modes))
            ->when($filters['employment_fractions'] ?? [], fn (Builder $query, array $fractions) => $query->whereIn('employment_fraction', $fractions))
            ->when($request->boolean('flexible_hours'), fn (Builder $query) => $query->where('flexible_hours', true))
            ->when($request->boolean('childcare_subsidy'), fn (Builder $query) => $query->where('childcare_subsidy', true))
            ->when($request->boolean('with_reviews'), fn (Builder $query) => $query->whereHas('company.approvedReviews'))
            ->when($startFrom, fn (Builder $query, string $date) => $query->whereDate(
                'start_date',
                '>=',
                Carbon::parse($date)->subDays(MatchScorer::START_DATE_TOLERANCE_DAYS)->toDateString(),
            ));

        $ranked = $matchScorer->rankOffersFor($profile, $query);

        if ($sort === 'newest') {
            $ranked = $ranked->sortByDesc(fn (array $row): int => $row['offer']->published_at?->getTimestamp() ?? 0)->values();
        }

        $interestedOfferIds = array_values($profile->interests()->get(['job_offer_id'])->map(fn (OfferInterest $interest): int => $interest->job_offer_id)->all());

        return Inertia::render('candidate/offers/Index', [
            'offers' => $ranked->map(fn (array $row): array => $this->presentOffer($row['offer'], $row['match'], $interestedOfferIds)),
            'filters' => [
                'q' => $filters['q'] ?? '',
                'location' => $filters['location'] ?? '',
                'work_modes' => $filters['work_modes'] ?? [],
                'employment_fractions' => $filters['employment_fractions'] ?? [],
                'flexible_hours' => $request->boolean('flexible_hours'),
                'childcare_subsidy' => $request->boolean('childcare_subsidy'),
                'with_reviews' => $request->boolean('with_reviews'),
                'start_from' => $startFrom,
                'sort' => $sort,
            ],
            'workModes' => collect(WorkMode::cases())->map(fn (WorkMode $mode): array => ['value' => $mode->value, 'label' => $mode->label()]),
            'employmentFractions' => collect(EmploymentFraction::cases())->map(fn (EmploymentFraction $fraction): array => ['value' => $fraction->value, 'label' => $fraction->label()]),
            'hasConfirmedSkills' => $profile->confirmedSkills()->exists(),
        ]);
    }

    /**
     * A single published offer with the match breakdown and company reviews.
     */
    public function show(Request $request, JobOffer $offer, MatchScorer $matchScorer): Response
    {
        abort_unless($offer->isPublished(), 404);

        $profile = $this->candidateProfile($request);
        $offer->load(['skills', 'company.approvedReviews']);
        $interestedOfferIds = array_values($profile->interests()->where('job_offer_id', $offer->id)->get(['job_offer_id'])
            ->map(fn (OfferInterest $interest): int => $interest->job_offer_id)->all());

        return Inertia::render('candidate/offers/Show', [
            'offer' => [
                ...$this->presentOffer($offer, $matchScorer->score($profile, $offer), $interestedOfferIds),
                'description' => $offer->description,
                'company_description' => $offer->company->description,
            ],
            'availableFrom' => $profile->available_from?->toDateString(),
            'reviews' => $offer->company->approvedReviews
                ->sortByDesc('created_at')
                ->values()
                ->map(fn (CompanyReview $review): array => [
                    'id' => $review->id,
                    'quote' => $review->quote,
                    'author_label' => $review->author_label,
                    'rating' => round($review->overallRating(), 1),
                    'rating_return' => $review->rating_return,
                    'rating_flexibility' => $review->rating_flexibility,
                    'rating_no_pregnancy_questions' => $review->rating_no_pregnancy_questions,
                ]),
        ]);
    }
}
