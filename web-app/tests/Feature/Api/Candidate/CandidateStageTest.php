<?php

namespace Tests\Feature\Api\Candidate;

use App\Enums\ArticleCategory;
use App\Enums\CandidateStage;
use App\Models\Article;
use App\Models\CandidateProfile;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CandidateStageTest extends TestCase
{
    use RefreshDatabase;

    public function test_preferences_require_a_valid_stage(): void
    {
        $profile = CandidateProfile::factory()->create(['stage' => null]);
        Sanctum::actingAs($profile->user);

        $this->putJson('/api/v1/candidate/profile/preferences', ['available_from' => '2027-09-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['stage' => 'Wybierz, gdzie teraz jesteś.']);

        $this->putJson('/api/v1/candidate/profile/preferences', ['stage' => 'on_holiday', 'available_from' => '2027-09-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('stage');

        $this->putJson('/api/v1/candidate/profile/preferences', ['stage' => 'after_leave', 'available_from' => '2027-09-01', 'due_date' => '2026-05-01'])
            ->assertOk()
            ->assertJsonPath('data.stage', 'after_leave')
            ->assertJsonPath('data.stage_label', 'Po urlopie macierzyńskim')
            ->assertJsonPath('data.due_date', null);
    }

    public function test_publishing_requires_a_stage(): void
    {
        $profile = CandidateProfile::factory()->create(['stage' => null, 'available_from' => '2027-09-01']);
        $profile->skills()->attach(Skill::factory()->create(), ['source' => 'manual', 'confirmed_at' => now()]);
        Sanctum::actingAs($profile->user);

        $this->postJson('/api/v1/candidate/profile/publish')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('stage')
            ->assertJsonMissingValidationErrors(['available_from', 'skills']);
    }

    public function test_options_list_the_stages(): void
    {
        Sanctum::actingAs(CandidateProfile::factory()->create()->user);

        $this->getJson('/api/v1/candidate/profile/options')
            ->assertOk()
            ->assertJsonPath('data.stages', [
                ['value' => 'pregnant', 'label' => 'W ciąży'],
                ['value' => 'after_leave', 'label' => 'Po urlopie macierzyńskim'],
            ]);
    }

    public function test_home_returns_the_stage_calendar_message_and_articles(): void
    {
        $profile = CandidateProfile::factory()->published()->afterLeave()->create(['available_from' => now()->addMonth()]);
        Article::factory()->create(['category' => ArticleCategory::Pregnancy]);
        $return = Article::factory()->create(['category' => ArticleCategory::Return]);
        Sanctum::actingAs($profile->user);

        $this->getJson('/api/v1/candidate/home')
            ->assertOk()
            ->assertJsonPath('data.greeting.stage_message', CandidateStage::AfterLeave->homeMessage())
            ->assertJsonPath('data.calendar.stage', 'after_leave')
            ->assertJsonPath('data.calendar.stage_label', 'Po urlopie macierzyńskim')
            ->assertJsonPath('data.calendar.phases', ['leave', 'return', 'ready'])
            ->assertJsonPath('data.calendar.current_phase', 'return')
            ->assertJsonPath('data.calendar.pregnancy_week', null)
            ->assertJsonCount(1, 'data.recommended_articles')
            ->assertJsonPath('data.recommended_articles.0.id', $return->id)
            ->assertJsonPath('data.recommended_articles.0.category', 'return');
    }

    public function test_assistant_suggestions_follow_the_candidate_stage(): void
    {
        Sanctum::actingAs(CandidateProfile::factory()->pregnant()->create()->user);
        $pregnantSuggestions = $this->getJson('/api/v1/assistant/messages')->assertOk()->json('meta.suggestions');

        Sanctum::actingAs(CandidateProfile::factory()->afterLeave()->create()->user);
        $afterLeaveSuggestions = $this->getJson('/api/v1/assistant/messages')->assertOk()->json('meta.suggestions');

        Sanctum::actingAs(User::factory()->employer()->create());
        $employerSuggestions = $this->getJson('/api/v1/assistant/messages')->assertOk()->json('meta.suggestions');

        $this->assertContains('Czy muszę mówić o ciąży na rozmowie?', $pregnantSuggestions);
        $this->assertContains('Zwolnienie lekarskie w ciąży', $pregnantSuggestions);
        $this->assertNotContains('Przerwy na karmienie', $pregnantSuggestions);
        $this->assertContains('Urlop rodzicielski a powrót', $afterLeaveSuggestions);
        $this->assertContains('Przerwy na karmienie', $afterLeaveSuggestions);
        $this->assertNotContains('Ochrona przed zwolnieniem w ciąży', $afterLeaveSuggestions);
        $this->assertContains('Zasiłek macierzyński', $employerSuggestions);
    }
}
