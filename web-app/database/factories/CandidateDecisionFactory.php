<?php

namespace Database\Factories;

use App\Enums\CandidateDecisionType;
use App\Models\CandidateDecision;
use App\Models\CandidateProfile;
use App\Models\JobOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CandidateDecision>
 */
class CandidateDecisionFactory extends Factory
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
            'decision' => CandidateDecisionType::Saved,
        ];
    }
}
