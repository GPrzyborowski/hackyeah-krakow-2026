<?php

namespace App\Services\Candidate;

use App\Enums\ArticleCategory;
use App\Enums\CandidateStage;
use App\Models\Article;
use Illuminate\Support\Collection;

/**
 * Blog articles recommended on the candidate home screen, picked from the categories that fit her private stage.
 */
class ArticleRecommendations
{
    /**
     * One article per recommended category first (featured, then newest), filled up from the same categories.
     * Without a stage the newest published articles are recommended.
     *
     * @return Collection<int, Article>
     */
    public function forStage(?CandidateStage $stage, int $limit = 3): Collection
    {
        $query = Article::query()->published()->orderByDesc('is_featured')->latest('published_at');

        if ($stage === null) {
            return $query->limit($limit)->get();
        }

        $categories = $stage->recommendedArticleCategories();
        $articles = $query->whereIn('category', array_map(fn (ArticleCategory $category): string => $category->value, $categories))->get();

        $firstPerCategory = collect($categories)
            ->map(fn (ArticleCategory $category): ?Article => $articles->first(fn (Article $article): bool => $article->category === $category))
            ->filter();

        return $firstPerCategory
            ->concat($articles->diff($firstPerCategory))
            ->take($limit)
            ->values();
    }
}
