<?php

namespace App\Http\Controllers\Api\V1\Employer;

use App\Actions\Employer\CloseJobOffer;
use App\Actions\Employer\SaveJobOffer;
use App\Enums\JobSharePairStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Employer\Concerns\InteractsWithEmployerCompany;
use App\Http\Requests\Api\V1\Employer\IndexOffersRequest;
use App\Http\Requests\Employer\SaveJobOfferRequest;
use App\Http\Resources\Api\V1\EmployerOfferResource;
use App\Models\Company;
use App\Models\JobOffer;
use App\Services\Employer\CompanyOffers;
use App\Services\Matching\MatchScorer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * The company's job offers: list with funnel statistics, create, edit, publish and close.
 */
class JobOfferController extends Controller
{
    use InteractsWithEmployerCompany;

    public function __construct(private readonly MatchScorer $scorer, private readonly CompanyOffers $companyOffers) {}

    public function index(IndexOffersRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', JobOffer::class);

        $company = $this->currentCompany($request);
        $hasApprovedReview = $company->approvedReviews()->exists();
        $status = $request->status();

        $offers = $this->companyOffers->listing($company)
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->paginate(20)
            ->withQueryString()
            ->through(fn (JobOffer $offer): EmployerOfferResource => $this->resource($offer, $hasApprovedReview));

        return EmployerOfferResource::collection($offers);
    }

    public function show(Request $request, JobOffer $offer): EmployerOfferResource
    {
        Gate::authorize('view', $offer);

        return $this->present($offer, $this->currentCompany($request));
    }

    /**
     * Save a draft (`action=draft`) or publish (`action=publish`) a new offer.
     */
    public function store(SaveJobOfferRequest $request, SaveJobOffer $saveJobOffer): JsonResponse
    {
        Gate::authorize('create', JobOffer::class);

        $company = $this->currentCompany($request);
        $offer = $saveJobOffer->handle($company->jobOffers()->make(), $request);

        return $this->present($offer, $company)->response()->setStatusCode(201);
    }

    public function update(SaveJobOfferRequest $request, JobOffer $offer, SaveJobOffer $saveJobOffer): EmployerOfferResource
    {
        Gate::authorize('update', $offer);

        return $this->present($saveJobOffer->handle($offer, $request), $this->currentCompany($request));
    }

    /**
     * Close the offer; unanswered invitations are withdrawn.
     */
    public function close(Request $request, JobOffer $offer, CloseJobOffer $closeJobOffer): EmployerOfferResource
    {
        Gate::authorize('close', $offer);

        return $this->present($closeJobOffer->handle($offer), $this->currentCompany($request));
    }

    private function present(JobOffer $offer, Company $company): EmployerOfferResource
    {
        $offer->load(['skills', 'invitations', 'decisions:id,job_offer_id,candidate_profile_id', 'company'])
            ->loadCount(['jobSharePairs as submitted_pairs_count' => fn ($query) => $query->where('status', JobSharePairStatus::Submitted)->visibleToCompany($company)]);

        return $this->resource($offer, $company->approvedReviews()->exists());
    }

    private function resource(JobOffer $offer, bool $companyHasApprovedReview): EmployerOfferResource
    {
        return new EmployerOfferResource(
            $offer,
            $this->offerStatistics($offer, $this->scorer),
            (int) $offer->getAttribute('submitted_pairs_count'),
            $this->companyOffers->isParentFriendly($offer, $companyHasApprovedReview),
        );
    }
}
