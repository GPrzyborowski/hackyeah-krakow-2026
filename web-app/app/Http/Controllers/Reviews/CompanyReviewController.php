<?php

namespace App\Http\Controllers\Reviews;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reviews\SaveCompanyReviewRequest;
use App\Models\Company;
use App\Models\CompanyReview;
use App\Policies\CompanyReviewPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CompanyReviewController extends Controller
{
    /**
     * Companies the candidate may still review, and her own reviews with moderation status.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $reviewableCompanies = CompanyReviewPolicy::companiesKnownTo($user)
            ->whereDoesntHave('reviews', fn ($query) => $query->where('user_id', $user->id))
            ->orderBy('name')
            ->get();

        $reviews = CompanyReview::query()
            ->whereBelongsTo($user, 'author')
            ->with('company')
            ->latest()
            ->latest('id')
            ->get();

        return Inertia::render('reviews/Index', [
            'reviewableCompanies' => $reviewableCompanies->map(fn (Company $company): array => [
                'id' => $company->id,
                'name' => $company->name,
                'city' => $company->city,
            ])->values(),
            'reviews' => $reviews->map(fn (CompanyReview $review): array => [
                ...$this->reviewData($review),
                'can_edit' => $request->user()->can('update', $review),
            ])->values(),
        ]);
    }

    public function create(Request $request, Company $company): Response|RedirectResponse
    {
        $existingReview = $company->reviews()->where('user_id', $request->user()->id)->first();

        if ($existingReview?->status === ReviewStatus::Pending) {
            return to_route('reviews.edit', $existingReview);
        }

        Gate::authorize('create', [CompanyReview::class, $company]);

        return Inertia::render('reviews/Form', [
            'company' => ['id' => $company->id, 'name' => $company->name, 'city' => $company->city],
            'review' => null,
        ]);
    }

    /**
     * New reviews wait for an administrator before they appear on the company page.
     */
    public function store(SaveCompanyReviewRequest $request, Company $company): RedirectResponse
    {
        Gate::authorize('create', [CompanyReview::class, $company]);

        $company->reviews()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'status' => ReviewStatus::Pending,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dziękujemy! Opinia pojawi się po moderacji.']);

        return to_route('reviews.index');
    }

    public function edit(CompanyReview $review): Response
    {
        Gate::authorize('update', $review);

        $review->load('company');

        return Inertia::render('reviews/Form', [
            'company' => ['id' => $review->company->id, 'name' => $review->company->name, 'city' => $review->company->city],
            'review' => $this->reviewData($review),
        ]);
    }

    public function update(SaveCompanyReviewRequest $request, CompanyReview $review): RedirectResponse
    {
        Gate::authorize('update', $review);

        $review->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Opinia zaktualizowana. Wciąż czeka na moderację.']);

        return to_route('reviews.index');
    }

    /**
     * @return array{id: int, company: array{id: int, name: string}, rating_return: int, rating_flexibility: int, rating_no_pregnancy_questions: int, quote: string|null, author_label: string|null, status: string}
     */
    private function reviewData(CompanyReview $review): array
    {
        return [
            'id' => $review->id,
            'company' => ['id' => $review->company->id, 'name' => $review->company->name],
            'rating_return' => $review->rating_return,
            'rating_flexibility' => $review->rating_flexibility,
            'rating_no_pregnancy_questions' => $review->rating_no_pregnancy_questions,
            'quote' => $review->quote,
            'author_label' => $review->author_label,
            'status' => $review->status->value,
        ];
    }
}
