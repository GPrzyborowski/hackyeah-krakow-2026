<?php

namespace Tests\Feature\Employer;

use App\Enums\EmploymentFraction;
use App\Enums\SkillImportance;
use App\Enums\WorkMode;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\JobOffer;
use App\Models\Skill;
use App\Models\User;

trait InteractsWithEmployerFixtures
{
    /**
     * Called automatically by Laravel for test classes using this trait; pages render without a Vite build.
     */
    protected function setUpInteractsWithEmployerFixtures(): void
    {
        $this->withoutVite();
    }

    protected function skill(string $name): Skill
    {
        return Skill::findOrCreateByName($name);
    }

    protected function employer(?Company $company = null): User
    {
        return User::factory()->employer($company ?? Company::factory()->create())->create(['name' => 'Rekruterka Testowa']);
    }

    /**
     * @param  list<Skill>  $required
     * @param  list<Skill>  $niceToHave
     */
    protected function publishedOffer(Company $company, array $required, array $niceToHave = [], string $startDate = '2027-09-01'): JobOffer
    {
        $offer = JobOffer::factory()->published()->for($company)->create([
            'start_date' => $startDate,
            'work_mode' => WorkMode::Hybrid,
            'employment_fraction' => EmploymentFraction::ThreeFifths,
        ]);

        foreach ($required as $skill) {
            $offer->skills()->attach($skill, ['importance' => SkillImportance::Required->value]);
        }

        foreach ($niceToHave as $skill) {
            $offer->skills()->attach($skill, ['importance' => SkillImportance::NiceToHave->value]);
        }

        return $offer;
    }

    /**
     * @param  list<Skill>  $skills
     * @param  array<string, mixed>  $attributes
     */
    protected function candidate(array $skills, array $attributes = [], string $name = 'Marta Kowalska'): CandidateProfile
    {
        $candidate = CandidateProfile::factory()
            ->published()
            ->for(User::factory()->state(['name' => $name]))
            ->create([
                'available_from' => '2027-09-01',
                'work_modes' => [WorkMode::Hybrid->value],
                'employment_fractions' => [EmploymentFraction::ThreeFifths->value],
                ...$attributes,
            ]);

        foreach ($skills as $skill) {
            $candidate->skills()->attach($skill, ['source' => 'manual', 'confirmed_at' => now()]);
        }

        return $candidate;
    }
}
