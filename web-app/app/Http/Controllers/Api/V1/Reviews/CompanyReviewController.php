<?php

namespace App\Http\Controllers\Api\V1\Reviews;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reviews\SaveCompanyReviewRequest;
use App\Http\Resources\Api\V1\CompanyReviewResource;
use App\Models\Company;
use App\Models\CompanyReview;
use App\Policies\CompanyReviewPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Anonymous company reviews by candidates who accepted the company's invitation; new and edited reviews wait for moderation.
 */
class CompanyReviewController extends Controller
{
    /**
     * Companies the candidate may still review and her own reviews with their moderation status.
     */
    public function index(Request $request): JsonResponse
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

        return response()->json(['data' => [
            'reviewable_companies' => $reviewableCompanies->map(fn (Company $company): array => [
                'id' => $company->id,
                'name' => $company->name,
                'city' => $company->city,
            ])->values(),
            'reviews' => CompanyReviewResource::collection($reviews)->toArray($request),
        ]]);
    }

    public function store(SaveCompanyReviewRequest $request, Company $company): JsonResponse
    {
        Gate::authorize('create', [CompanyReview::class, $company]);

        $review = $company->reviews()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'status' => ReviewStatus::Pending,
        ]);

        return (new CompanyReviewResource($review->load('company')))->response()->setStatusCode(201);
    }

    /**
     * Edit a review that still waits for moderation.
     */
    public function update(SaveCompanyReviewRequest $request, CompanyReview $review): CompanyReviewResource
    {
        Gate::authorize('update', $review);

        $review->update($request->validated());

        return new CompanyReviewResource($review->load('company'));
    }
}
