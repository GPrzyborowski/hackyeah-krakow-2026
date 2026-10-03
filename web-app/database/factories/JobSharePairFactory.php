<?php

namespace Database\Factories;

use App\Enums\JobSharePairStatus;
use App\Models\JobOffer;
use App\Models\JobSharePair;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobSharePair>
 */
class JobSharePairFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_offer_id' => JobOffer::factory()->published()->jobShare(),
            'status' => JobSharePairStatus::Forming,
        ];
    }
}
