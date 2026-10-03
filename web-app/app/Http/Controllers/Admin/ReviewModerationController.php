<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Models\CompanyReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReviewModerationController extends Controller
{
    /**
     * Reviews of one moderation status (pending by default), oldest pending first so nothing waits too long.
     */
    public function index(Request $request): Response
    {
        $status = ReviewStatus::tryFrom((string) $request->query('status')) ?? ReviewStatus::Pending;

        $reviews = CompanyReview::query()
            ->where('status', $status)
            ->with(['company', 'author'])
            ->when(
                $status === ReviewStatus::Pending,
                fn ($query) => $query->oldest()->oldest('id'),
                fn ($query) => $query->latest('updated_at')->latest('id'),
            )
            ->limit(100)
            ->get();

        $counts = CompanyReview::query()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('admin/reviews/Index', [
            'status' => $status->value,
            'counts' => collect(ReviewStatus::cases())->mapWithKeys(fn (ReviewStatus $case): array => [
                $case->value => (int) ($counts[$case->value] ?? 0),
            ]),
            'reviews' => $reviews->map(fn (CompanyReview $review): array => [
                'id' => $review->id,
                'company' => ['id' => $review->company->id, 'name' => $review->company->name],
                'author_name' => $review->author?->name,
                'rating_return' => $review->rating_return,
                'rating_flexibility' => $review->rating_flexibility,
                'rating_no_pregnancy_questions' => $review->rating_no_pregnancy_questions,
                'quote' => $review->quote,
                'author_label' => $review->author_label,
                'status' => $review->status->value,
                'created_at' => $review->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function approve(CompanyReview $review): RedirectResponse
    {
        return $this->moderate($review, ReviewStatus::Approved, 'Opinia zatwierdzona i widoczna na profilu firmy.');
    }

    public function reject(CompanyReview $review): RedirectResponse
    {
        return $this->moderate($review, ReviewStatus::Rejected, 'Opinia odrzucona.');
    }

    private function moderate(CompanyReview $review, ReviewStatus $status, string $message): RedirectResponse
    {
        Gate::authorize('moderate', $review);

        $review->update(['status' => $status]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
