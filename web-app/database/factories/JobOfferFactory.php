<?php

namespace Database\Factories;

use App\Enums\EmploymentFraction;
use App\Enums\OfferStatus;
use App\Enums\WorkMode;
use App\Models\Company;
use App\Models\JobOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobOffer>
 */
class JobOfferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $salaryMin = fake()->numberBetween(60, 100) * 100;

        return [
            'company_id' => Company::factory(),
            'title' => fake()->jobTitle(),
            'city' => fake()->randomElement(['Kraków', 'Poznań', 'Warszawa']),
            'work_mode' => fake()->randomElement(WorkMode::cases()),
            'employment_fraction' => fake()->randomElement(EmploymentFraction::cases()),
            'salary_min' => $salaryMin,
            'salary_max' => $salaryMin + 2500,
            'start_date' => now()->addMonths(fake()->numberBetween(1, 11))->startOfMonth(),
            'description' => fake()->paragraph(),
            'flexible_hours' => fake()->boolean(),
            'fixed_meeting_hours' => fake()->boolean(),
            'childcare_subsidy' => fake()->boolean(30),
            'status' => OfferStatus::Draft,
        ];
    }

    /**
     * Indicate that the offer is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OfferStatus::Published,
            'published_at' => now(),
        ]);
    }
}
