<?php

namespace Tests\Feature\Api\Shared;

use App\Enums\ArticleCategory;
use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_published_articles_featured_first_and_filters_by_category(): void
    {
        $category = ArticleCategory::cases()[0];
        $otherCategory = ArticleCategory::cases()[1];
        $older = Article::factory()->create(['category' => $category, 'published_at' => now()->subDays(3)]);
        $featured = Article::factory()->create(['category' => $category, 'is_featured' => true, 'published_at' => now()->subDays(5)]);
        Article::factory()->create(['category' => $otherCategory]);
        Article::factory()->create(['category' => $category, 'published_at' => now()->addDay()]);

        $this->getJson('/api/v1/articles?category='.$category->value)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $featured->id)
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonPath('meta.active_category', $category->value);

        $this->getJson('/api/v1/articles?featured=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $featured->id);
    }

    public function test_article_returns_markdown_and_safe_html(): void
    {
        $article = Article::factory()->create([
            'slug' => 'urlop-rodzicielski',
            'body' => "## Urlop\n\n<script>alert(1)</script>\n\n[link](javascript:alert(1))",
        ]);

        $response = $this->getJson('/api/v1/articles/urlop-rodzicielski')
            ->assertOk()
            ->assertJsonPath('data.id', $article->id)
            ->assertJsonPath('data.body_markdown', $article->body);

        $html = (string) $response->json('data.body_html');
        $this->assertStringContainsString('<h2>Urlop</h2>', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_unpublished_article_is_404(): void
    {
        Article::factory()->create(['slug' => 'szkic', 'published_at' => null]);

        $this->getJson('/api/v1/articles/szkic')->assertNotFound();
    }
}
