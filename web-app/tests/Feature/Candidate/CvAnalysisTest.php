<?php

namespace Tests\Feature\Candidate;

use App\Enums\SkillImportance;
use App\Models\CandidateProfile;
use App\Models\JobOffer;
use App\Models\Skill;
use App\Models\User;
use App\Services\Matching\CvInsights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CvAnalysisTest extends TestCase
{
    use RefreshDatabase;

    private CandidateProfile $profile;

    private Skill $recruitment;

    private Skill $excel;

    private Skill $onboarding;

    private Skill $payroll;

    private Skill $german;

    private JobOffer $strongOffer;

    private JobOffer $mediumOffer;

    private JobOffer $weakOffer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profile = CandidateProfile::factory()->published()->create([
            'available_from' => '2027-09-01',
            'work_modes' => [],
            'employment_fractions' => [],
            'suggested_positions' => [['title' => 'HR Business Partner', 'score' => 70], ['title' => 'Rekruterka IT', 'score' => 90]],
        ]);

        $this->recruitment = Skill::factory()->create(['name' => 'Rekrutacja IT']);
        $this->excel = Skill::factory()->create(['name' => 'Excel']);
        $this->onboarding = Skill::factory()->create(['name' => 'Onboarding']);
        $this->payroll = Skill::factory()->create(['name' => 'Kadry i płace']);
        $this->german = Skill::factory()->create(['name' => 'Niemiecki']);

        $this->profile->skills()->attach($this->recruitment, ['source' => 'manual', 'confirmed_at' => now()]);
        $this->profile->skills()->attach($this->excel, ['source' => 'manual', 'confirmed_at' => now()]);

        // 1/2 required + no nice-to-have + preferences = 35 + 20 + 10 = 65; with Onboarding: 100 (+35).
        $this->strongOffer = $this->offer('Specjalistka ds. rekrutacji', '2027-09-01', required: [$this->recruitment, $this->onboarding]);
        // 1/3 required = 23.33 + 20 + 10 = 53; with Onboarding or Payroll: 76.67 + ... = 77 (+24).
        $this->mediumOffer = $this->offer('HR generalistka', '2027-06-01', required: [$this->recruitment, $this->onboarding, $this->payroll]);
        // 0 required + Excel nice-to-have + preferences = 30 (below 50%, ignored for missing skills).
        $this->weakOffer = $this->offer('Asystentka zarządu', '2027-09-01', required: [$this->onboarding, $this->german], niceToHave: [$this->excel]);

        $draft = JobOffer::factory()->create(['title' => 'Szkic']);
        $draft->skills()->attach($this->excel, ['importance' => SkillImportance::Required->value]);
    }

    public function test_analysis_ranks_strengths_missing_skills_and_stats(): void
    {
        $analysis = app(CvInsights::class)->analyze($this->profile);

        $this->assertSame([
            ['id' => $this->recruitment->id, 'name' => 'Rekrutacja IT', 'demand_count' => 2],
            ['id' => $this->excel->id, 'name' => 'Excel', 'demand_count' => 1],
        ], $analysis['strengths']);

        $this->assertSame([
            ['id' => $this->onboarding->id, 'name' => 'Onboarding', 'offers_count' => 2, 'average_gain' => 30],
            ['id' => $this->payroll->id, 'name' => 'Kadry i płace', 'offers_count' => 1, 'average_gain' => 24],
        ], $analysis['missing_skills']);

        $this->assertSame([
            'offers_total' => 3,
            'matching_offers' => 1,
            'average_score' => 49,
            'can_start_on_time' => 2,
        ], $analysis['stats']);

        $this->assertSame(
            [$this->strongOffer->id, $this->mediumOffer->id, $this->weakOffer->id],
            array_map(fn (array $row): int => $row['offer']->id, $analysis['offers']),
        );
        $this->assertSame([['title' => 'Rekruterka IT', 'score' => 90], ['title' => 'HR Business Partner', 'score' => 70]], $analysis['positions']['suggested']);
        $this->assertSame(['title' => 'Specjalistka ds. rekrutacji', 'score' => 65], $analysis['positions']['offers'][0]);
    }

    public function test_virtual_skill_does_not_change_the_stored_profile(): void
    {
        app(CvInsights::class)->analyze($this->profile);

        $this->assertSame(2, $this->profile->confirmedSkills()->count());
        $this->assertCount(2, $this->profile->confirmedSkills);
    }

    public function test_page_renders_the_analysis_for_a_published_candidate(): void
    {
        $this->actingAs($this->profile->user)
            ->get(route('candidate.cv-analysis'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('candidate/CvAnalysis')
                ->where('analysis.stats.matching_offers', 1)
                ->where('analysis.strengths.0.name', 'Rekrutacja IT')
                ->where('analysis.missing_skills.0.name', 'Onboarding')
                ->has('analysis.offers', 3)
                ->where('analysis.offers.0.id', $this->strongOffer->id)
                ->where('analysis.offers.0.score', 65)
                ->where('analysis.offers.0.matched_skills', ['Rekrutacja IT'])
                ->where('analysis.offers.0.missing_required', ['Onboarding']));
    }

    public function test_unpublished_candidate_is_sent_to_onboarding(): void
    {
        $profile = CandidateProfile::factory()->create();

        $this->actingAs($profile->user)
            ->get(route('candidate.cv-analysis'))
            ->assertRedirect(route('candidate.onboarding.show'));
    }

    public function test_employers_are_forbidden_and_guests_redirected(): void
    {
        $this->get(route('candidate.cv-analysis'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->employer()->create())
            ->get(route('candidate.cv-analysis'))
            ->assertForbidden();
    }

    public function test_adding_a_missing_skill_confirms_it_and_updates_the_analysis(): void
    {
        $this->actingAs($this->profile->user)
            ->from(route('candidate.cv-analysis'))
            ->post(route('candidate.skills.store'), ['name' => 'Onboarding'])
            ->assertRedirect(route('candidate.cv-analysis'));

        $this->assertTrue($this->profile->confirmedSkills()->whereKey($this->onboarding->id)->exists());

        $this->actingAs($this->profile->user)
            ->get(route('candidate.cv-analysis'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('analysis.offers.0.score', 100)
                ->where('analysis.missing_skills.0.name', 'Niemiecki'));
    }

    public function test_api_returns_the_same_analysis_for_the_candidate_only(): void
    {
        Sanctum::actingAs($this->profile->user);

        $this->getJson(route('api.v1.candidate.cv-analysis'))
            ->assertOk()
            ->assertJsonPath('data.stats.matching_offers', 1)
            ->assertJsonPath('data.missing_skills.0.average_gain', 30)
            ->assertJsonPath('data.offers.0.id', $this->strongOffer->id);

        Sanctum::actingAs(User::factory()->employer()->create());

        $this->getJson(route('api.v1.candidate.cv-analysis'))->assertForbidden();
    }

    /**
     * @param  list<Skill>  $required
     * @param  list<Skill>  $niceToHave
     */
    private function offer(string $title, string $startDate, array $required, array $niceToHave = []): JobOffer
    {
        $offer = JobOffer::factory()->published()->create(['title' => $title, 'start_date' => $startDate]);

        foreach ($required as $skill) {
            $offer->skills()->attach($skill, ['importance' => SkillImportance::Required->value]);
        }

        foreach ($niceToHave as $skill) {
            $offer->skills()->attach($skill, ['importance' => SkillImportance::NiceToHave->value]);
        }

        return $offer;
    }
}
