<?php

namespace App\Services\Ai;

use App\Models\Article;
use App\Models\LegalSource;
use Illuminate\Support\Collection;

/**
 * Keyword-scored retrieval of legal sources and blog articles for the legal assistant.
 */
class KnowledgeRetriever
{
    /**
     * @return Collection<int, LegalSource>
     */
    public function legalSources(string $question, int $limit = 4): Collection
    {
        $questionStems = PolishText::stems($question);
        $normalizedQuestion = PolishText::normalize($question);

        return $this->rank(
            LegalSource::query()->get(),
            function (LegalSource $source) use ($questionStems, $normalizedQuestion): int {
                $score = 0;

                foreach ($source->keywords ?? [] as $keyword) {
                    $normalizedKeyword = PolishText::normalize($keyword);

                    if (str_contains($normalizedQuestion, $normalizedKeyword)) {
                        $score += 6;
                    } else {
                        $score += 3 * count(array_intersect($questionStems, PolishText::stems($keyword)));
                    }
                }

                return $score
                    + 2 * count(array_intersect($questionStems, PolishText::stems($source->title)))
                    + count(array_intersect($questionStems, PolishText::stems($source->content)));
            },
            $limit,
        );
    }

    /**
     * @return Collection<int, Article>
     */
    public function articles(string $question, int $limit = 2): Collection
    {
        $questionStems = PolishText::stems($question);

        return $this->rank(
            Article::query()->published()->get(),
            fn (Article $article): int => 4 * count(array_intersect($questionStems, PolishText::stems($article->title)))
                + 2 * count(array_intersect($questionStems, PolishText::stems($article->excerpt)))
                + count(array_intersect($questionStems, PolishText::stems($article->body))),
            $limit,
        );
    }

    /**
     * @template TModel
     *
     * @param  Collection<int, TModel>  $items
     * @param  callable(TModel): int  $scorer
     * @return Collection<int, TModel>
     */
    private function rank(Collection $items, callable $scorer, int $limit): Collection
    {
        return $items
            ->map(fn ($item): array => ['item' => $item, 'score' => $scorer($item)])
            ->filter(fn (array $scored): bool => $scored['score'] > 1)
            ->sortByDesc('score')
            ->take($limit)
            ->pluck('item')
            ->values();
    }
}
