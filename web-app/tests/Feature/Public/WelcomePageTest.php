<?php

namespace Tests\Feature\Public;

use App\Enums\ReviewStatus;
use App\Models\Article;
use App\Models\Company;
use App\Models\CompanyReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_landing_page_renders_with_empty_database(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->has('companies', 0)
                ->has('articles', 0),
            );
    }

    public function test_landing_page_shows_top_three_companies_by_approved_reviews(): void
    {
        $mostReviewed = Company::factory()->create(['name' => 'Zielone Biuro']);
        CompanyReview::factory()->count(3)->for($mostReviewed)->create([
            'rating_return' => 5,
            'rating_flexibility' => 4,
            'rating_no_pregnancy_questions' => 3,
        ]);
        CompanyReview::factory()->for($mostReviewed)->create(['status' => ReviewStatus::Rejected, 'rating_return' => 1]);

        CompanyReview::factory()->count(2)->for(Company::factory())->create();
        CompanyReview::factory()->for(Company::factory())->create();
        $leastReviewed = Company::factory()->create();
        CompanyReview::factory()->for($leastReviewed)->create();

        $pendingOnly = Company::factory()->create();
        CompanyReview::factory()->count(5)->for($pendingOnly)->create(['status' => ReviewStatus::Pending]);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('companies', 3)
                ->where('companies.0.name', 'Zielone Biuro')
                ->where('companies.0.rating.count', 3)
                ->where('companies.0.rating.overall', 4)
                ->where('companies.0.rating.categories.return', 5)
                ->where('companies.0.rating.categories.flexibility', 4)
                ->where('companies.0.rating.categories.no_pregnancy_questions', 3)
                ->has('companies.0.featured_quote.quote')
                ->has('companies.0.featured_quote.author_label'),
            );
    }

    public function test_landing_page_shows_three_latest_published_articles(): void
    {
        Article::factory()->create(['slug' => 'older', 'published_at' => now()->subDays(10)]);
        Article::factory()->create(['slug' => 'newest', 'published_at' => now()->subDay()]);
        Article::factory()->create(['slug' => 'middle', 'published_at' => now()->subDays(3)]);
        Article::factory()->create(['slug' => 'oldest', 'published_at' => now()->subDays(30)]);
        Article::factory()->create(['slug' => 'draft', 'published_at' => null]);
        Article::factory()->create(['slug' => 'scheduled', 'published_at' => now()->addDay()]);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('articles', 3)
                ->where('articles.0.slug', 'newest')
                ->where('articles.1.slug', 'middle')
                ->where('articles.2.slug', 'older')
                ->has('articles.0.category_label')
                ->has('articles.0.reading_minutes'),
            );
    }
}
