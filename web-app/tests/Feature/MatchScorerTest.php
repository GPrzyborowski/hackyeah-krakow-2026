<?php

namespace Tests\Feature;

use App\Enums\EmploymentFraction;
use App\Enums\SkillImportance;
use App\Enums\WorkMode;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\JobOffer;
use App\Models\Skill;
use App\Services\Matching\MatchScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchScorerTest extends TestCase
{
    use RefreshDatabase;

    private MatchScorer $scorer;

    private Skill $recruitment;

    private Skill $onboarding;

    private Skill $excel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scorer = new MatchScorer;
        $this->recruitment = Skill::factory()->create(['name' => 'Rekrutacja IT']);
        $this->onboarding = Skill::factory()->create(['name' => 'Onboarding']);
        $this->excel = Skill::factory()->create(['name' => 'Excel']);
    }

    public function test_score_weights_required_skills_nice_to_have_and_preferences()
    {
        $offer = $this->offer(startDate: '2027-09-01');
        $candidate = $this->candidate(availableFrom: '2027-09-01', skills: [$this->recruitment]);

        $match = $this->scorer->score($candidate, $offer);

        $this->assertSame(40, $match->score);
        $this->assertSame(['Rekrutacja IT'], $match->matchedRequired);
        $this->assertSame(['Onboarding'], $match->missingRequired);
        $this->assertSame(['Excel'], $match->missingNiceToHave);
        $this->assertTrue($match->startDateCompatible);
    }

    public function test_unconfirmed_skills_are_ignored()
    {
        $offer = $this->offer(startDate: '2027-09-01');
        $candidate = $this->candidate(availableFrom: '2027-09-01', skills: []);
        $candidate->skills()->attach($this->recruitment, ['source' => 'ai', 'confirmed_at' => null]);

        $this->assertSame([], $this->scorer->score($candidate, $offer)->matchedRequired);
    }

    public function test_candidates_who_cannot_start_in_time_are_not_eligible()
    {
        $offer = $this->offer(startDate: '2027-09-01');
        $inTime = $this->candidate(availableFrom: '2027-09-30', skills: [$this->recruitment]);
        $this->candidate(availableFrom: '2027-10-02', skills: [$this->recruitment]);

        $ranked = $this->scorer->rankCandidatesFor($offer);

        $this->assertSame([$inTime->id], $ranked->pluck('candidate.id')->all());
    }

    public function test_candidates_hidden_from_the_company_are_not_eligible()
    {
        $offer = $this->offer(startDate: '2027-09-01');
        $this->candidate(availableFrom: '2027-09-01', skills: [$this->recruitment], hiddenFrom: $offer->company);

        $this->assertCount(0, $this->scorer->rankCandidatesFor($offer));
    }

    public function test_preview_counts_require_all_required_skills()
    {
        $offer = $this->offer(startDate: '2027-09-01');
        $this->candidate(availableFrom: '2027-09-01', skills: [$this->recruitment, $this->onboarding, $this->excel]);
        $this->candidate(availableFrom: '2027-09-01', skills: [$this->recruitment, $this->onboarding]);
        $this->candidate(availableFrom: '2027-09-01', skills: [$this->recruitment]);

        $counts = $this->scorer->previewCounts(
            $offer->company,
            $offer->start_date,
            [$this->recruitment->id, $this->onboarding->id],
            [$this->excel->id],
        );

        $this->assertSame(['with_required' => 2, 'with_nice_to_have' => 1], $counts);
    }

    private function offer(string $startDate): JobOffer
    {
        $offer = JobOffer::factory()->published()->create([
            'start_date' => $startDate,
            'work_mode' => WorkMode::Remote,
            'employment_fraction' => EmploymentFraction::ThreeFifths,
        ]);

        $offer->skills()->attach($this->recruitment, ['importance' => SkillImportance::Required->value]);
        $offer->skills()->attach($this->onboarding, ['importance' => SkillImportance::Required->value]);
        $offer->skills()->attach($this->excel, ['importance' => SkillImportance::NiceToHave->value]);

        return $offer;
    }

    /**
     * @param  list<Skill>  $skills
     */
    private function candidate(string $availableFrom, array $skills, ?Company $hiddenFrom = null): CandidateProfile
    {
        $candidate = CandidateProfile::factory()->published()->create([
            'available_from' => $availableFrom,
            'work_modes' => [WorkMode::Hybrid->value],
            'employment_fractions' => [EmploymentFraction::ThreeFifths->value],
            'hidden_from_company_id' => $hiddenFrom?->id,
        ]);

        foreach ($skills as $skill) {
            $candidate->skills()->attach($skill, ['source' => 'manual', 'confirmed_at' => now()]);
        }

        return $candidate;
    }
}
