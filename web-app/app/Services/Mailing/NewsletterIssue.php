<?php

namespace App\Services\Mailing;

use App\Models\Article;
use App\Models\NewsletterSubscriber;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * The weekly newsletter content: the newest articles published in the last week, each sent to a subscriber only once.
 */
class NewsletterIssue
{
    public const int LOOKBACK_DAYS = 7;

    public const int MAX_ARTICLES = 3;

    /**
     * @return EloquentCollection<int, Article>
     */
    public function recentArticles(): EloquentCollection
    {
        return Article::query()
            ->published()
            ->where('published_at', '>=', now()->subDays(self::LOOKBACK_DAYS))
            ->latest('published_at')
            ->limit(self::MAX_ARTICLES)
            ->get();
    }

    /**
     * @param  EloquentCollection<int, Article>  $recentArticles
     * @return EloquentCollection<int, Article>
     */
    public function articlesFor(NewsletterSubscriber $subscriber, EloquentCollection $recentArticles): EloquentCollection
    {
        $deliveredIds = $subscriber->deliveredArticles()->pluck('articles.id')->all();

        return $recentArticles
            ->reject(fn (Article $article): bool => in_array($article->id, $deliveredIds, true))
            ->values();
    }

    /**
     * @param  EloquentCollection<int, Article>  $articles
     */
    public function recordDelivery(NewsletterSubscriber $subscriber, EloquentCollection $articles): void
    {
        $sentAt = now();

        $subscriber->deliveredArticles()->syncWithoutDetaching(
            $articles->mapWithKeys(fn (Article $article): array => [$article->id => ['sent_at' => $sentAt]])->all(),
        );
        $subscriber->update(['last_sent_at' => $sentAt]);
    }
}
