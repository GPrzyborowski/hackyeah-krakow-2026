<?php

namespace App\Http\Controllers\Api\V1\Content;

use App\Enums\ArticleCategory;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ArticleDetailResource;
use App\Http\Resources\Api\V1\ArticleResource;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Public blog articles.
 */
class ArticleController extends Controller
{
    private const int PER_PAGE = 20;

    /**
     * Published articles, featured first then newest; `?category=` filters, `?featured=1` returns only featured ones.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $category = ArticleCategory::tryFrom((string) $request->query('category'));

        $articles = Article::query()
            ->published()
            ->when($category, fn ($query) => $query->where('category', $category))
            ->when($request->boolean('featured'), fn ($query) => $query->where('is_featured', true))
            ->orderByDesc('is_featured')
            ->latest('published_at')
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return ArticleResource::collection($articles)->additional(['meta' => [
            'categories' => collect(ArticleCategory::cases())->map(fn (ArticleCategory $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ])->all(),
            'active_category' => $category?->value,
        ]]);
    }

    /**
     * A published article with up to three related ones from the same category.
     */
    public function show(Article $article): ArticleDetailResource
    {
        abort_unless($article->published_at !== null && $article->published_at->isPast(), 404);

        $related = Article::query()
            ->published()
            ->whereKeyNot($article->id)
            ->where('category', $article->category)
            ->latest('published_at')
            ->limit(3)
            ->get();

        return (new ArticleDetailResource($article))->additional([
            'related' => ArticleResource::collection($related),
        ]);
    }
}
