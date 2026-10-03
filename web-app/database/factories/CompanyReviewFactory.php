<?php

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models\Company;
use App\Models\CompanyReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyReview>
 */
class CompanyReviewFactory extends Factory
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
            'user_id' => null,
            'rating_return' => fake()->numberBetween(3, 5),
            'rating_flexibility' => fake()->numberBetween(3, 5),
            'rating_no_pregnancy_questions' => fake()->numberBetween(3, 5),
            'quote' => fake()->sentence(),
            'author_label' => 'Mama jednego dziecka',
            'status' => ReviewStatus::Approved,
        ];
    }
}
