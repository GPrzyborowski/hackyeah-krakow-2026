<?php

namespace Database\Factories;

use App\Enums\AssistantRole;
use App\Models\AssistantMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssistantMessage>
 */
class AssistantMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'role' => AssistantRole::User,
            'content' => fake()->sentence().'?',
            'citations' => null,
        ];
    }
}
