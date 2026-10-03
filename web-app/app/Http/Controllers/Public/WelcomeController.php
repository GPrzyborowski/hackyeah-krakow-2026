<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Public\Concerns\PresentsCompanyRatings;
use App\Models\Article;
use App\Models\Company;
use Inertia\Inertia;
use Inertia\Response;

class WelcomeController extends Controller
{
    use PresentsCompanyRatings;

    /**
     * Landing page with real top-rated companies and the latest blog articles.
     */
    public function __invoke(): Response
    {
        $companies = Company::query()
            ->select(['id', 'name', 'city'])
            ->withCount('approvedReviews')
            ->having('approved_reviews_count', '>', 0)
            ->orderByDesc('approved_reviews_count')
            ->orderBy('id')
            ->limit(3)
            ->with(['approvedReviews' => fn ($query) => $query->select([
                'id', 'company_id', 'rating_return', 'rating_flexibility', 'rating_no_pregnancy_questions',
                'quote', 'author_label', 'status',
            ])->orderBy('id')])
            ->get()
            ->map(fn (Company $company): array => [
                'id' => $company->id,
                'name' => $company->name,
                'city' => $company->city,
                'rating' => $this->ratingSummary($company),
                'featured_quote' => $this->featuredQuote($company),
            ]);

        $articles = Article::query()
            ->published()
            ->latest('published_at')
            ->limit(3)
            ->get(['id', 'title', 'slug', 'category', 'reading_minutes', 'published_at'])
            ->map(fn (Article $article): array => [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'category' => $article->category->value,
                'category_label' => $article->category->label(),
                'reading_minutes' => $article->reading_minutes,
            ]);

        return Inertia::render('Welcome', [
            'companies' => $companies,
            'articles' => $articles,
        ]);
    }
}
