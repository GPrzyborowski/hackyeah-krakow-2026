<?php

namespace Database\Factories;

use App\Models\CandidateProfile;
use App\Models\JobOffer;
use App\Models\OfferInterest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OfferInterest>
 */
class OfferInterestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_offer_id' => JobOffer::factory(),
            'candidate_profile_id' => CandidateProfile::factory(),
        ];
    }
}
