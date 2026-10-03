<?php

namespace Database\Factories;

use App\Models\JobShareMessage;
use App\Models\JobSharePair;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobShareMessage>
 */
class JobShareMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_share_pair_id' => JobSharePair::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
