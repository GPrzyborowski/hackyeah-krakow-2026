<?php

namespace App\Http\Controllers\Content;

use App\Enums\ArticleCategory;
use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    /**
     * Published articles, optionally filtered by category, with the featured one as the hero.
     */
    public function index(Request $request): Response
    {
        $category = ArticleCategory::tryFrom((string) $request->query('category'));

        $articles = Article::query()
            ->published()
            ->when($category, fn ($query) => $query->where('category', $category))
            ->orderByDesc('is_featured')
            ->latest('published_at')
            ->get();

        $featured = $articles->firstWhere('is_featured', true);

        return Inertia::render('public/blog/Index', [
            'categories' => collect(ArticleCategory::cases())->map(fn (ArticleCategory $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ]),
            'activeCategory' => $category?->value,
            'featured' => $featured ? $this->presentCard($featured) : null,
            'articles' => $articles
                ->reject(fn (Article $article): bool => $featured !== null && $article->is($featured))
                ->map(fn (Article $article): array => $this->presentCard($article))
                ->values(),
        ]);
    }

    /**
     * A single published article rendered from Markdown (raw HTML stripped, unsafe links disabled).
     */
    public function show(Article $article): Response
    {
        abort_unless($article->published_at !== null && $article->published_at->isPast(), 404);

        $related = Article::query()
            ->published()
            ->whereKeyNot($article->id)
            ->where('category', $article->category)
            ->latest('published_at')
            ->limit(3)
            ->get();

        return Inertia::render('public/blog/Show', [
            'article' => [
                ...$this->presentCard($article),
                'html' => Str::markdown($article->body, Article::MARKDOWN_OPTIONS),
            ],
            'related' => $related->map(fn (Article $related): array => $this->presentCard($related))->values(),
        ]);
    }

    /**
     * @return array{id: int, title: string, slug: string, excerpt: string, category: string, category_label: string, reading_minutes: int, published_at: string|null}
     */
    private function presentCard(Article $article): array
    {
        return [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'category' => $article->category->value,
            'category_label' => $article->category->label(),
            'reading_minutes' => $article->reading_minutes,
            'published_at' => $article->published_at?->toIso8601String(),
        ];
    }
}
