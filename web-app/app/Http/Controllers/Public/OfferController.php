<?php

namespace App\Http\Controllers\Public;

use App\Enums\EmploymentFraction;
use App\Enums\WorkMode;
use App\Http\Controllers\Controller;
use App\Models\JobOffer;
use App\Services\Offers\PublicOfferPresenter;
use App\Services\Offers\PublicOfferSearch;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OfferController extends Controller
{
    public function __construct(private readonly PublicOfferPresenter $presenter) {}

    /**
     * Public, match-free list of published offers with simple query-string filters.
     */
    public function index(Request $request, PublicOfferSearch $search): Response
    {
        $filters = $search->filters($request);

        $offers = $search->query($filters)
            ->paginate(10)
            ->withQueryString()
            ->through(fn (JobOffer $offer): array => $this->presenter->card($offer));

        return Inertia::render('public/offers/Index', [
            'offers' => $offers,
            'filters' => $filters,
            'workModes' => collect(WorkMode::cases())->map(fn (WorkMode $mode): array => ['value' => $mode->value, 'label' => $mode->label()]),
            'fractions' => collect(EmploymentFraction::cases())->map(fn (EmploymentFraction $fraction): array => ['value' => $fraction->value, 'label' => $fraction->label()]),
        ]);
    }

    /**
     * A published offer for everyone (guests, employers, candidates) without a match score; drafts and closed offers are 404.
     */
    public function show(JobOffer $offer): Response
    {
        abort_unless($offer->isPublished(), 404);

        $offer->load(['skills', 'company.approvedReviews' => fn ($query) => $query->orderBy('id')]);

        return Inertia::render('public/offers/Show', [
            'offer' => [
                ...$this->presenter->detail($offer),
                'company_description' => $offer->company->description,
            ],
            'reviews' => $this->presenter->reviews($offer),
        ]);
    }
}
