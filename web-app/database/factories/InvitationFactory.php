<?php

namespace Database\Factories;

use App\Enums\InvitationKind;
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
            'kind' => InvitationKind::Invitation,
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

    /**
     * Indicate a question sent without an invitation (to a candidate who allows direct messages).
     */
    public function directMessage(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => InvitationKind::DirectMessage,
            'message' => 'Dzień dobry, czy rozważa Pani pracę w zespole księgowym na 3/4 etatu?',
        ]);
    }
}
