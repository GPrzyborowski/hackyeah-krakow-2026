<?php

namespace Tests\Feature\Content;

use App\Enums\ArticleCategory;
use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BlogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guests_see_published_articles_with_featured_hero(): void
    {
        $featured = Article::factory()->create(['is_featured' => true, 'category' => ArticleCategory::Return]);
        Article::factory()->create(['category' => ArticleCategory::Leave]);
        Article::factory()->create(['published_at' => null]);
        Article::factory()->create(['published_at' => now()->addWeek()]);

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/blog/Index')
                ->where('featured.id', $featured->id)
                ->has('articles', 1)
                ->has('categories', count(ArticleCategory::cases())));
    }

    public function test_category_filter_limits_articles(): void
    {
        $leave = Article::factory()->create(['category' => ArticleCategory::Leave]);
        Article::factory()->create(['category' => ArticleCategory::Rights]);

        $this->get(route('blog.index', ['category' => 'leave']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('activeCategory', 'leave')
                ->where('featured', null)
                ->has('articles', 1)
                ->where('articles.0.id', $leave->id));
    }

    public function test_article_body_is_rendered_from_markdown_without_raw_html(): void
    {
        $article = Article::factory()->create([
            'slug' => 'urlop-rodzicielski',
            'body' => "## Wniosek\n\nZłóż **wniosek** na 21 dni przed.\n\n<script>alert(1)</script>\n\n[link](javascript:alert(1))",
        ]);

        $this->get(route('blog.show', $article))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/blog/Show')
                ->where('article.title', $article->title)
                ->where('article.html', fn (string $html): bool => str_contains($html, '<h2>Wniosek</h2>')
                    && str_contains($html, '<strong>wniosek</strong>')
                    && ! str_contains($html, '<script>')
                    && ! str_contains($html, 'javascript:')));
    }

    public function test_unpublished_article_is_not_found(): void
    {
        $draft = Article::factory()->create(['published_at' => null]);
        $scheduled = Article::factory()->create(['published_at' => now()->addDay()]);

        $this->get(route('blog.show', $draft))->assertNotFound();
        $this->get(route('blog.show', $scheduled))->assertNotFound();
    }
}
