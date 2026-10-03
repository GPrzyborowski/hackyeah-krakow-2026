<?php

namespace Database\Factories;

use App\Enums\InvitationStatus;
use App\Models\CandidateProfile;
use App\Models\Invitation;
use App\Models\JobOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
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
            'message' => 'Dzień dobry, zapraszamy do rozmowy o stanowisku. Widełki i godziny pracy w ogłoszeniu.',
            'status' => InvitationStatus::Pending,
        ];
    }

    /**
     * Indicate that the candidate accepted the invitation.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvitationStatus::Accepted,
            'responded_at' => now(),
        ]);
    }
}
