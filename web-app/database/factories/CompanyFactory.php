<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => Str::ucfirst(fake()->unique()->word()).' Studio',
            'nip' => fake()->unique()->numerify('##########'),
            'city' => fake()->randomElement(['Kraków', 'Poznań', 'Warszawa', 'Wrocław', 'Gdańsk']),
            'description' => fake()->sentence(12),
        ];
    }

    /**
     * A company whose NIP was checked by a mumjobs administrator.
     */
    public function verified(): static
    {
        return $this->state(fn (): array => ['verified_at' => now()]);
    }
}
