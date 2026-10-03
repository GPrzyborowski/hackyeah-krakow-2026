<?php

namespace Database\Factories;

use App\Enums\EmploymentFraction;
use App\Enums\WorkMode;
use App\Models\CandidateProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CandidateProfile>
 */
class CandidateProfileFactory extends Factory
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
            'headline' => fake()->jobTitle(),
            'years_of_experience' => fake()->numberBetween(1, 15),
            'city' => fake()->randomElement(['Kraków', 'Poznań', 'Warszawa']),
            'available_from' => now()->addMonths(fake()->numberBetween(1, 11))->startOfMonth(),
            'work_modes' => [WorkMode::Remote->value, WorkMode::Hybrid->value],
            'employment_fractions' => [EmploymentFraction::ThreeFifths->value, EmploymentFraction::ThreeQuarters->value],
            'wants_flexible_hours' => true,
            'onboarding_step' => 1,
        ];
    }

    /**
     * Indicate that the profile finished onboarding and is visible to employers.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'onboarding_step' => 4,
            'ai_summary' => fake()->sentence(16),
            'published_at' => now(),
        ]);
    }
}
