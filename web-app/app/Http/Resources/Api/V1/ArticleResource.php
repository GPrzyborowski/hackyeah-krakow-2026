<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Blog article card (lists).
 *
 * @property Article $resource
 */
class ArticleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $article = $this->resource;

        return [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'excerpt' => $article->excerpt,
            'category' => $article->category->value,
            'category_label' => $article->category->label(),
            'reading_minutes' => $article->reading_minutes,
            'is_featured' => $article->is_featured,
            'published_at' => $article->published_at?->toIso8601String(),
        ];
    }
}
