<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * A single article: Markdown source and HTML rendered with the web's safe options (raw HTML stripped, unsafe links disabled).
 *
 * @property Article $resource
 */
class ArticleDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $article = $this->resource;

        return [
            ...(new ArticleResource($article))->toArray($request),
            'body_markdown' => $article->body,
            'body_html' => Str::markdown($article->body, Article::MARKDOWN_OPTIONS),
        ];
    }
}
