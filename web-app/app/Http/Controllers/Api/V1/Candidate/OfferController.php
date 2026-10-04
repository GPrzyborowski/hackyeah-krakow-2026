<?php

namespace App\Http\Controllers\Api\V1\Candidate;

use App\Enums\ContractType;
use App\Enums\EmploymentFraction;
use App\Enums\OfferCategory;
use App\Enums\WorkMode;
use App\Http\Controllers\Candidate\Concerns\PresentsOffers;
use App\Http\Controllers\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\OfferFilterRequest;
use App\Http\Resources\Api\V1\OfferDetailResource;
use App\Http\Resources\Api\V1\OfferResource;
use App\Models\JobOffer;
use App\Services\Candidate\OfferSearch;
use App\Services\JobSharing\OfferJobSharePanel;
use App\Services\Matching\MatchScorer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

class OfferController extends Controller
{
    use PresentsOffers, ResolvesCandidateProfile;

    private const int PER_PAGE = 20;

    /**
     * Published offers with the same search, filters and match ranking as the web list, paginated after ranking.
     */
    public function index(OfferFilterRequest $request, OfferSearch $offerSearch): AnonymousResourceCollection
    {
        $profile = $this->candidateProfile($request);
        $filters = $offerSearch->filtersFrom($request, $profile);
        $ranked = $offerSearch->rank($profile, $filters);
        $interestedOfferIds = $this->interestedOfferIds($profile);
        $savedOfferIds = $this->savedOfferIds($profile);
        $page = LengthAwarePaginator::resolveCurrentPage();

        $offers = (new LengthAwarePaginator(
            $ranked->forPage($page, self::PER_PAGE)->values(),
            $ranked->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        ))->through(fn (array $row): OfferResource => new OfferResource(
            $row['offer'],
            $row['match'],
            in_array($row['offer']->id, $interestedOfferIds, true),
            in_array($row['offer']->id, $savedOfferIds, true),
        ));

        return OfferResource::collection($offers)->additional(['meta' => [
            'filters' => $filters,
            'has_confirmed_skills' => $profile->confirmedSkills()->exists(),
            'work_modes' => collect(WorkMode::cases())->map(fn (WorkMode $mode): array => ['value' => $mode->value, 'label' => $mode->label()]),
            'employment_fractions' => collect(EmploymentFraction::cases())->map(fn (EmploymentFraction $fraction): array => ['value' => $fraction->value, 'label' => $fraction->label()]),
            'contract_types' => collect(ContractType::cases())->map(fn (ContractType $type): array => ['value' => $type->value, 'label' => $type->label()]),
            'categories' => collect(OfferCategory::cases())->map(fn (OfferCategory $category): array => ['value' => $category->value, 'label' => $category->label()]),
        ]]);
    }

    /**
     * A single published offer with the full match breakdown, job-sharing panel and company reviews.
     */
    public function show(Request $request, JobOffer $offer, MatchScorer $matchScorer, OfferJobSharePanel $jobSharePanel): OfferDetailResource
    {
        abort_unless($offer->isPublished(), 404);

        $profile = $this->candidateProfile($request);
        $offer->load(['skills', 'company.approvedReviews']);

        return new OfferDetailResource(
            $offer,
            $matchScorer->score($profile, $offer),
            $this->interestedOfferIds($profile, $offer) !== [],
            $this->savedOfferIds($profile, $offer) !== [],
            $jobSharePanel->present($profile, $offer),
        );
    }
}
