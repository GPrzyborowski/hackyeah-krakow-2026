<?php

namespace Tests\Feature\Admin;

use App\Enums\ArticleCategory;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ArticleManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $article = Article::factory()->create();

        $this->get(route('admin.articles.index'))->assertRedirect(route('login'));
        $this->post(route('admin.articles.store'), $this->validPayload())->assertRedirect(route('login'));
        $this->post(route('admin.articles.preview'), ['body' => 'x'])->assertRedirect(route('login'));
        $this->delete(route('admin.articles.destroy', $article))->assertRedirect(route('login'));

        $this->assertModelExists($article);
    }

    public function test_candidates_and_employers_cannot_manage_articles(): void
    {
        $article = Article::factory()->create();

        foreach ([User::factory()->create(), User::factory()->employer()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.articles.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.articles.edit', $article))->assertForbidden();
            $this->actingAs($user)->post(route('admin.articles.store'), $this->validPayload())->assertForbidden();
            $this->actingAs($user)->put(route('admin.articles.update', $article), $this->validPayload())->assertForbidden();
            $this->actingAs($user)->delete(route('admin.articles.destroy', $article))->assertForbidden();
            $this->actingAs($user)->postJson(route('admin.articles.preview'), ['body' => 'x'])->assertForbidden();
        }

        $this->assertModelExists($article);
        $this->assertDatabaseCount('articles', 1);
    }

    public function test_index_lists_articles_with_publication_status(): void
    {
        $draft = Article::factory()->create(['published_at' => null]);
        $scheduled = Article::factory()->create(['published_at' => now()->addWeek()]);
        $published = Article::factory()->create(['published_at' => now()->subDay(), 'category' => ArticleCategory::Rights]);

        $this->actingAs($this->admin)
            ->get(route('admin.articles.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/articles/Index')
                ->has('articles', 3)
                ->where('articles.0.id', $draft->id)
                ->where('articles.0.status', 'draft')
                ->where('articles.1.id', $scheduled->id)
                ->where('articles.1.status', 'scheduled')
                ->where('articles.2.id', $published->id)
                ->where('articles.2.status', 'published')
                ->where('articles.2.category_label', 'Prawa'));
    }

    public function test_admin_creates_an_article_with_slug_and_reading_time_derived(): void
    {
        $body = trim(str_repeat('słowo ', 450));

        $this->actingAs($this->admin)
            ->post(route('admin.articles.store'), $this->validPayload([
                'title' => 'Powrót do pracy po urlopie: część etatu',
                'slug' => '',
                'body' => "## Nagłówek\n\n".$body,
                'reading_minutes' => '',
                'published_at' => '2026-10-01T08:30:00.000Z',
            ]))
            ->assertRedirect(route('admin.articles.index'));

        $article = Article::query()->sole();

        $this->assertSame('powrot-do-pracy-po-urlopie-czesc-etatu', $article->slug);
        $this->assertSame(3, $article->reading_minutes);
        $this->assertSame('2026-10-01 08:30:00', $article->published_at?->toDateTimeString());
    }

    public function test_reading_time_can_be_overridden_and_short_bodies_take_at_least_one_minute(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.articles.store'), $this->validPayload(['slug' => 'nadpisany', 'reading_minutes' => 12]))
            ->assertRedirect();
        $this->actingAs($this->admin)
            ->post(route('admin.articles.store'), $this->validPayload(['slug' => 'krotki', 'body' => '**Krótko.**', 'reading_minutes' => null]))
            ->assertRedirect();

        $this->assertSame(12, Article::query()->where('slug', 'nadpisany')->value('reading_minutes'));
        $this->assertSame(1, Article::query()->where('slug', 'krotki')->value('reading_minutes'));
    }

    public function test_empty_publication_date_saves_a_draft(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.articles.store'), $this->validPayload(['published_at' => '']))
            ->assertRedirect();

        $this->assertNull(Article::query()->sole()->published_at);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidArticles')]
    public function test_article_validation(array $overrides, string $field): void
    {
        Article::factory()->create(['slug' => 'zajety-adres']);

        $this->actingAs($this->admin)
            ->post(route('admin.articles.store'), $this->validPayload($overrides))
            ->assertSessionHasErrors($field);

        $this->assertDatabaseCount('articles', 1);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidArticles(): array
    {
        return [
            'duplicate slug' => [['slug' => 'zajety-adres'], 'slug'],
            'slug derived from a title that is taken' => [['title' => 'Zajęty adres', 'slug' => ''], 'slug'],
            'unknown category' => [['category' => 'gossip'], 'category'],
            'missing title' => [['title' => ''], 'title'],
            'missing body' => [['body' => ''], 'body'],
            'zero reading minutes' => [['reading_minutes' => 0], 'reading_minutes'],
            'invalid publication date' => [['published_at' => 'jutro'], 'published_at'],
        ];
    }

    public function test_admin_updates_an_article_keeping_its_own_slug(): void
    {
        $article = Article::factory()->create(['slug' => 'stary-adres', 'category' => ArticleCategory::Leave]);

        $this->actingAs($this->admin)
            ->get(route('admin.articles.edit', $article))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/articles/Form')
                ->where('article.slug', 'stary-adres')
                ->has('categories', count(ArticleCategory::cases())));

        $this->actingAs($this->admin)
            ->put(route('admin.articles.update', $article), $this->validPayload([
                'title' => 'Nowy tytuł',
                'slug' => 'stary-adres',
                'category' => ArticleCategory::Return->value,
            ]))
            ->assertRedirect(route('admin.articles.index'))
            ->assertSessionHasNoErrors();

        $article->refresh();
        $this->assertSame('Nowy tytuł', $article->title);
        $this->assertSame(ArticleCategory::Return, $article->category);
    }

    public function test_only_one_article_is_featured_at_a_time(): void
    {
        $previouslyFeatured = Article::factory()->create(['is_featured' => true]);
        $other = Article::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.articles.store'), $this->validPayload(['is_featured' => true]))
            ->assertRedirect();

        $created = Article::query()->latest('id')->firstOrFail();
        $this->assertTrue($created->is_featured);
        $this->assertFalse($previouslyFeatured->refresh()->is_featured);

        $this->actingAs($this->admin)
            ->put(route('admin.articles.update', $other), $this->validPayload(['slug' => $other->slug, 'is_featured' => true]))
            ->assertRedirect();

        $this->assertSame([$other->id], Article::query()->where('is_featured', true)->pluck('id')->all());
    }

    public function test_admin_deletes_an_article(): void
    {
        $article = Article::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.articles.destroy', $article))
            ->assertRedirect(route('admin.articles.index'));

        $this->assertModelMissing($article);
    }

    public function test_preview_renders_markdown_and_strips_raw_html(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.articles.preview'), [
                'body' => "## Twoje prawa\n\n**Ważne**<script>alert('x')</script>\n\n[klik](javascript:alert(1))",
            ])
            ->assertOk();

        $html = $response->json('html');

        $this->assertStringContainsString('<h2>Twoje prawa</h2>', $html);
        $this->assertStringContainsString('<strong>Ważne</strong>', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'title' => 'Jak wrócić do pracy',
            'slug' => 'jak-wrocic-do-pracy',
            'category' => ArticleCategory::Return->value,
            'excerpt' => 'Krótki przewodnik.',
            'body' => 'Treść artykułu o powrocie do pracy.',
            'reading_minutes' => null,
            'is_featured' => false,
            'published_at' => null,
            ...$overrides,
        ];
    }
}
