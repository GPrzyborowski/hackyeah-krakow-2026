<?php

namespace Database\Factories;

use App\Enums\ArticleCategory;
use App\Models\Article;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(5);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'category' => fake()->randomElement(ArticleCategory::cases()),
            'excerpt' => fake()->sentence(15),
            'body' => fake()->paragraphs(4, true),
            'reading_minutes' => fake()->numberBetween(3, 9),
            'is_featured' => false,
            'published_at' => now()->subDay(),
        ];
    }
}
