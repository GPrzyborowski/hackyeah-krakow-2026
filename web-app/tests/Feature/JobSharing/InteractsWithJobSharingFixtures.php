<?php

namespace Tests\Feature\JobSharing;

use App\Enums\DayPart;
use App\Enums\JobSharePairStatus;
use App\Enums\SkillImportance;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\JobOffer;
use App\Models\JobSharePair;
use App\Models\Skill;
use Tests\Feature\Employer\InteractsWithEmployerFixtures;

trait InteractsWithJobSharingFixtures
{
    use InteractsWithEmployerFixtures;

    /**
     * @param  list<Skill>  $required
     */
    protected function jobShareOffer(Company $company, array $required, string $startDate = '2027-09-01'): JobOffer
    {
        $offer = JobOffer::factory()->published()->jobShare()->for($company)->create(['start_date' => $startDate]);

        foreach ($required as $skill) {
            $offer->skills()->attach($skill, ['importance' => SkillImportance::Required->value]);
        }

        return $offer;
    }

    /**
     * @param  list<Skill>  $skills
     * @param  array<string, mixed>  $attributes
     */
    protected function sharer(array $skills, string $name, ?DayPart $dayPart = null, array $attributes = []): CandidateProfile
    {
        return $this->candidate($skills, [
            'open_to_job_sharing' => true,
            'preferred_day_part' => $dayPart,
            ...$attributes,
        ], $name);
    }

    protected function pair(JobOffer $offer, CandidateProfile $initiator, CandidateProfile $partner, JobSharePairStatus $status = JobSharePairStatus::Formed): JobSharePair
    {
        $pair = JobSharePair::factory()->for($offer)->create(['status' => $status]);

        $pair->members()->attach([
            $initiator->id => ['is_initiator' => true, 'accepted_at' => now()],
            $partner->id => ['is_initiator' => false, 'accepted_at' => $status === JobSharePairStatus::Forming ? null : now()],
        ]);

        return $pair;
    }

    /**
     * Formed pair with the even 08:00–12:00 / 12:00–16:00 split.
     */
    protected function scheduledPair(JobOffer $offer, CandidateProfile $first, CandidateProfile $second, JobSharePairStatus $status = JobSharePairStatus::Formed): JobSharePair
    {
        $pair = $this->pair($offer, $first, $second, $status);
        $pair->update(['proposed_schedule' => [
            ['candidate_profile_id' => $first->id, 'starts_at' => '08:00', 'ends_at' => '12:00'],
            ['candidate_profile_id' => $second->id, 'starts_at' => '12:00', 'ends_at' => '16:00'],
        ]]);

        return $pair;
    }
}
