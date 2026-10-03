<?php

namespace Tests\Feature\Candidate;

use App\Enums\ArticleCategory;
use App\Enums\CandidateStage;
use App\Models\Article;
use App\Models\CandidateProfile;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CandidateStageTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;

    private CandidateProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->candidate = User::factory()->create(['name' => 'Marta Kowalska']);
        $this->profile = $this->candidate->candidateProfile()->create(['onboarding_step' => 2]);
    }

    public function test_preferences_require_a_valid_stage(): void
    {
        $this->actingAs($this->candidate)
            ->put(route('candidate.onboarding.preferences'), ['available_from' => '2027-09-01'])
            ->assertSessionHasErrors(['stage' => 'Wybierz, gdzie teraz jesteś.']);

        $this->actingAs($this->candidate)
            ->put(route('candidate.onboarding.preferences'), ['stage' => 'on_holiday', 'available_from' => '2027-09-01'])
            ->assertSessionHasErrors('stage');

        $this->assertNull($this->profile->refresh()->stage);
    }

    public function test_pregnant_candidate_keeps_her_due_date(): void
    {
        $this->actingAs($this->candidate)
            ->put(route('candidate.onboarding.preferences'), [
                'stage' => 'pregnant',
                'available_from' => '2027-09-01',
                'due_date' => '2027-01-02',
                'leave_starts_on' => '2026-12-01',
            ])
            ->assertSessionHasNoErrors();

        $this->profile->refresh();
        $this->assertSame(CandidateStage::Pregnant, $this->profile->stage);
        $this->assertSame('2027-01-02', $this->profile->due_date?->toDateString());
    }

    public function test_candidate_after_leave_has_no_due_date_stored(): void
    {
        $this->profile->update(['due_date' => '2026-05-10']);

        $this->actingAs($this->candidate)
            ->put(route('candidate.profile.preferences'), [
                'stage' => 'after_leave',
                'available_from' => '2027-02-01',
                'leave_starts_on' => '2026-05-01',
                'due_date' => '2026-05-10',
            ])
            ->assertSessionHasNoErrors();

        $this->profile->refresh();
        $this->assertSame(CandidateStage::AfterLeave, $this->profile->stage);
        $this->assertNull($this->profile->due_date);
        $this->assertSame('2026-05-01', $this->profile->leave_starts_on?->toDateString());
    }

    public function test_publishing_requires_a_stage(): void
    {
        $this->profile->update(['available_from' => '2027-09-01', 'onboarding_step' => 3]);
        $this->profile->skills()->attach(Skill::factory()->create(), ['source' => 'manual', 'confirmed_at' => now()]);

        $this->actingAs($this->candidate)
            ->post(route('candidate.onboarding.publish'))
            ->assertSessionHasErrors('stage');
        $this->assertNull($this->profile->refresh()->published_at);

        $this->profile->update(['stage' => CandidateStage::AfterLeave]);

        $this->actingAs($this->candidate)
            ->post(route('candidate.onboarding.publish'))
            ->assertRedirect(route('candidate.home'));
        $this->assertNotNull($this->profile->refresh()->published_at);
    }

    public function test_home_for_a_pregnant_candidate_shows_her_message_calendar_and_articles(): void
    {
        $profile = CandidateProfile::factory()->published()->pregnant()->create();
        $pregnancy = Article::factory()->create(['category' => ArticleCategory::Pregnancy]);
        $rights = Article::factory()->create(['category' => ArticleCategory::Rights]);
        $interviews = Article::factory()->create(['category' => ArticleCategory::CvAndInterviews]);
        Article::factory()->create(['category' => ArticleCategory::Return]);

        $this->actingAs($profile->user)
            ->get(route('candidate.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stageMessage', CandidateStage::Pregnant->homeMessage())
                ->where('calendar.stage', 'pregnant')
                ->where('calendar.stage_label', 'W ciąży')
                ->where('calendar.phases', ['pregnancy', 'leave', 'ready'])
                ->where('recommendedArticles', fn ($articles): bool => collect($articles)->pluck('id')->all() === [$pregnancy->id, $rights->id, $interviews->id]));
    }

    public function test_home_for_a_candidate_after_leave_shows_return_articles(): void
    {
        $profile = CandidateProfile::factory()->published()->afterLeave()->create();
        Article::factory()->create(['category' => ArticleCategory::Pregnancy]);
        $return = Article::factory()->create(['category' => ArticleCategory::Return]);
        $leave = Article::factory()->create(['category' => ArticleCategory::Leave]);
        $rights = Article::factory()->create(['category' => ArticleCategory::Rights]);

        $this->actingAs($profile->user)
            ->get(route('candidate.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stageMessage', CandidateStage::AfterLeave->homeMessage())
                ->where('calendar.stage', 'after_leave')
                ->where('calendar.phases', ['leave', 'return', 'ready'])
                ->where('recommendedArticles', fn ($articles): bool => collect($articles)->pluck('id')->all() === [$return->id, $leave->id, $rights->id]));
    }

    public function test_home_without_a_stage_has_no_stage_message_and_falls_back_to_ready(): void
    {
        $profile = CandidateProfile::factory()->published()->create(['stage' => null, 'due_date' => null, 'leave_starts_on' => null]);

        $this->actingAs($profile->user)
            ->get(route('candidate.home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stageMessage', null)
                ->where('calendar.stage', null)
                ->where('calendar.current_phase', 'ready'));
    }

    public function test_assistant_suggestions_follow_the_candidate_stage(): void
    {
        $pregnant = CandidateProfile::factory()->pregnant()->create();
        $afterLeave = CandidateProfile::factory()->afterLeave()->create();

        $this->actingAs($pregnant->user)
            ->get(route('assistant.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('suggestions', fn ($suggestions): bool => collect($suggestions)->contains('Ochrona przed zwolnieniem w ciąży')
                    && ! collect($suggestions)->contains('Przerwy na karmienie')));

        $this->actingAs($afterLeave->user)
            ->get(route('assistant.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('suggestions', fn ($suggestions): bool => collect($suggestions)->contains('Przerwy na karmienie')
                    && ! collect($suggestions)->contains('Zwolnienie lekarskie w ciąży')));

        $this->actingAs(User::factory()->employer()->create())
            ->get(route('assistant.index'))
            ->assertInertia(fn (Assert $page) => $page->where('suggestions', fn ($suggestions): bool => collect($suggestions)->contains('Zasiłek macierzyński')));
    }
}
