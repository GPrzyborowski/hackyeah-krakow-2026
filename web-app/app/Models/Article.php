<?php

namespace App\Models;

use App\Enums\ArticleCategory;
use Carbon\CarbonImmutable;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property ArticleCategory $category
 * @property string $excerpt
 * @property string $body
 * @property int $reading_minutes
 * @property bool $is_featured
 * @property CarbonImmutable|null $published_at
 */
#[Fillable(['title', 'slug', 'category', 'excerpt', 'body', 'reading_minutes', 'is_featured', 'published_at'])]
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory;

    /**
     * CommonMark options for rendering article bodies: raw HTML stripped, unsafe links disabled.
     */
    public const array MARKDOWN_OPTIONS = [
        'html_input' => 'strip',
        'allow_unsafe_links' => false,
    ];

    /**
     * @param  Builder<Article>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ArticleCategory::class,
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}
