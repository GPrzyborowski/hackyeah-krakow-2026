<?php

namespace App\Http\Controllers\Employer;

use App\Actions\Employer\CloseJobOffer;
use App\Actions\Employer\SaveJobOffer;
use App\Enums\EmploymentFraction;
use App\Enums\OfferCategory;
use App\Enums\WorkMode;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Employer\Concerns\InteractsWithEmployerCompany;
use App\Http\Requests\Employer\SaveJobOfferRequest;
use App\Http\Resources\EmployerJobOfferResource;
use App\Models\Company;
use App\Models\JobOffer;
use App\Services\Employer\CompanyOffers;
use App\Services\Matching\MatchScorer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class JobOfferController extends Controller
{
    use InteractsWithEmployerCompany;

    /**
     * The company's offers with candidate funnel counters.
     */
    public function index(Request $request, MatchScorer $scorer, CompanyOffers $companyOffers): Response
    {
        Gate::authorize('viewAny', JobOffer::class);

        $company = $this->currentCompany($request);
        $hasApprovedReview = $company->approvedReviews()->exists();

        $offers = $companyOffers->listing($company)
            ->get()
            ->map(fn (JobOffer $offer): array => [
                ...(new EmployerJobOfferResource($offer))->resolve($request),
                'statistics' => $this->offerStatistics($offer, $scorer),
                'submitted_pairs_count' => (int) $offer->getAttribute('submitted_pairs_count'),
                'is_parent_friendly' => $companyOffers->isParentFriendly($offer, $hasApprovedReview),
            ]);

        return Inertia::render('employer/offers/Index', [
            'offers' => $offers,
            'companyVerified' => $company->isVerified(),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', JobOffer::class);

        return $this->renderForm($this->currentCompany($request), null);
    }

    public function store(SaveJobOfferRequest $request, SaveJobOffer $saveJobOffer): RedirectResponse
    {
        Gate::authorize('create', JobOffer::class);

        $offer = $this->currentCompany($request)->jobOffers()->make();

        $saveJobOffer->handle($offer, $request);

        return $this->redirectAfterSave($offer, $request);
    }

    public function edit(Request $request, JobOffer $offer): Response
    {
        Gate::authorize('update', $offer);

        return $this->renderForm($this->currentCompany($request), $offer->load('skills'));
    }

    public function update(SaveJobOfferRequest $request, JobOffer $offer, SaveJobOffer $saveJobOffer): RedirectResponse
    {
        Gate::authorize('update', $offer);

        $saveJobOffer->handle($offer, $request);

        return $this->redirectAfterSave($offer, $request);
    }

    /**
     * Close the offer and withdraw invitations nobody answered yet.
     */
    public function close(JobOffer $offer, CloseJobOffer $closeJobOffer): RedirectResponse
    {
        Gate::authorize('close', $offer);

        $closeJobOffer->handle($offer);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Oferta została zamknięta.']);

        return to_route('employer.offers.index');
    }

    private function renderForm(Company $company, ?JobOffer $offer): Response
    {
        return Inertia::render('employer/offers/Form', [
            'offer' => $offer ? (new EmployerJobOfferResource($offer))->resolve() : null,
            'companyHasApprovedReview' => $company->approvedReviews()->exists(),
            'categories' => collect(OfferCategory::cases())->map(fn (OfferCategory $category): array => ['value' => $category->value, 'label' => $category->label()]),
            'workModes' => collect(WorkMode::cases())->map(fn (WorkMode $mode): array => ['value' => $mode->value, 'label' => $mode->label()]),
            'employmentFractions' => collect(EmploymentFraction::cases())->map(fn (EmploymentFraction $fraction): array => ['value' => $fraction->value, 'label' => $fraction->label()]),
        ]);
    }

    private function redirectAfterSave(JobOffer $offer, SaveJobOfferRequest $request): RedirectResponse
    {
        if ($request->isPublishing()) {
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Oferta opublikowana. Oto pasujące kandydatki.']);

            return to_route('employer.candidates.index', ['offer' => $offer->id]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Szkic zapisany.']);

        return to_route('employer.offers.edit', $offer);
    }
}
