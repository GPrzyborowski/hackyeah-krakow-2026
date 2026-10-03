<?php

namespace Database\Factories;

use App\Models\LegalSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegalSource>
 */
class LegalSourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'act' => 'Kodeks pracy',
            'article' => (string) fake()->numberBetween(1, 300),
            'title' => fake()->sentence(4),
            'content' => fake()->paragraph(),
            'keywords' => fake()->words(3),
        ];
    }
}
