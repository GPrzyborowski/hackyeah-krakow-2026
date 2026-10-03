<?php

namespace Database\Factories;

use App\Models\NewsletterSubscriber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsletterSubscriber>
 */
class NewsletterSubscriberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'token' => NewsletterSubscriber::generateToken(),
        ];
    }

    /**
     * Indicate that the subscriber confirmed the address.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'confirmed_at' => now(),
        ]);
    }

    /**
     * Indicate that the subscriber opted out.
     */
    public function unsubscribed(): static
    {
        return $this->state(fn (array $attributes) => [
            'confirmed_at' => now()->subWeek(),
            'unsubscribed_at' => now(),
        ]);
    }
}
