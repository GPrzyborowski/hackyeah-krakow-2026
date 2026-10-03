<?php

namespace Database\Factories;

use App\Enums\CandidateStage;
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
            'stage' => CandidateStage::AfterLeave,
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

    /**
     * Indicate that the candidate is pregnant (with a private due date and leave start).
     */
    public function pregnant(): static
    {
        return $this->state(fn (array $attributes) => [
            'stage' => CandidateStage::Pregnant,
            'due_date' => now()->addWeeks(13)->startOfDay(),
            'leave_starts_on' => now()->addWeeks(11)->startOfDay(),
        ]);
    }

    /**
     * Indicate that the candidate is on or after her maternity leave.
     */
    public function afterLeave(): static
    {
        return $this->state(fn (array $attributes) => [
            'stage' => CandidateStage::AfterLeave,
            'due_date' => null,
        ]);
    }
}
