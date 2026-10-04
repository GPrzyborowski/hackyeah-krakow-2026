<?php

namespace Database\Factories;

use App\Models\CandidateProfile;
use App\Models\JobSharePair;
use App\Models\JobSharePairInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobSharePairInvitation>
 */
class JobSharePairInvitationFactory extends Factory
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
            'token' => JobSharePairInvitation::generateToken(),
            'invited_by_candidate_profile_id' => CandidateProfile::factory(),
            'accepted_by_candidate_profile_id' => null,
            'accepted_at' => null,
            'expires_at' => now()->addDays(JobSharePairInvitation::VALID_DAYS),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subDay(),
        ]);
    }
}
