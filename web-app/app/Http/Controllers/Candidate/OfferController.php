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
use App\Services\Candidate\OfferSearch;
use App\Services\JobSharing\OfferJobSharePanel;
use App\Services\Matching\MatchScorer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OfferController extends Controller
{
    use PresentsOffers, ResolvesCandidateProfile;

    /**
     * Published offers with search, filters and match ranking.
     */
    public function index(OfferFilterRequest $request, OfferSearch $offerSearch): Response
    {
        $profile = $this->candidateProfile($request);
        $filters = $offerSearch->filtersFrom($request, $profile);
        $ranked = $offerSearch->rank($profile, $filters);
        $interestedOfferIds = $this->interestedOfferIds($profile);
        $savedOfferIds = $this->savedOfferIds($profile);

        return Inertia::render('candidate/offers/Index', [
            'offers' => $ranked->map(fn (array $row): array => $this->presentOffer($row['offer'], $row['match'], $interestedOfferIds, $savedOfferIds)),
            'filters' => $filters,
            'workModes' => collect(WorkMode::cases())->map(fn (WorkMode $mode): array => ['value' => $mode->value, 'label' => $mode->label()]),
            'employmentFractions' => collect(EmploymentFraction::cases())->map(fn (EmploymentFraction $fraction): array => ['value' => $fraction->value, 'label' => $fraction->label()]),
            'hasConfirmedSkills' => $profile->confirmedSkills()->exists(),
        ]);
    }

    /**
     * A single published offer with the match breakdown and company reviews.
     */
    public function show(Request $request, JobOffer $offer, MatchScorer $matchScorer, OfferJobSharePanel $jobSharePanel): Response
    {
        abort_unless($offer->isPublished(), 404);

        $profile = $this->candidateProfile($request);
        $offer->load(['skills', 'company.approvedReviews']);
        $interestedOfferIds = $this->interestedOfferIds($profile, $offer);
        $savedOfferIds = $this->savedOfferIds($profile, $offer);

        return Inertia::render('candidate/offers/Show', [
            'offer' => [
                ...$this->presentOffer($offer, $matchScorer->score($profile, $offer), $interestedOfferIds, $savedOfferIds),
                'description' => $offer->description,
                'company_description' => $offer->company->description,
            ],
            'availableFrom' => $profile->available_from?->toDateString(),
            'jobSharing' => $jobSharePanel->present($profile, $offer),
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
