<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyInvitation>
 */
class CompanyInvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'email' => fake()->unique()->safeEmail(),
            'token' => CompanyInvitation::generateToken(),
            'invited_by_user_id' => null,
            'accepted_at' => null,
            'expires_at' => now()->addDays(CompanyInvitation::VALID_DAYS),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'accepted_at' => now()->subHour(),
        ]);
    }
}
