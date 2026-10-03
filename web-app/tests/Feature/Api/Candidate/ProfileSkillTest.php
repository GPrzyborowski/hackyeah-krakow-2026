<?php

namespace Tests\Feature\Api\Candidate;

use App\Enums\SkillSource;
use App\Models\CandidateProfile;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileSkillTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_adds_a_manual_tag_that_is_confirmed_at_once(): void
    {
        $profile = CandidateProfile::factory()->create();
        Sanctum::actingAs($profile->user);

        $this->postJson('/api/v1/candidate/profile/skills', ['name' => 'Rekrutacja IT'])
            ->assertCreated()
            ->assertJsonPath('data.skills.0.name', 'Rekrutacja IT')
            ->assertJsonPath('data.skills.0.source', SkillSource::Manual->value)
            ->assertJsonPath('data.skills.0.confirmed', true);

        $this->assertSame(1, $profile->confirmedSkills()->count());
    }

    public function test_tag_name_is_validated(): void
    {
        Sanctum::actingAs(CandidateProfile::factory()->create()->user);

        $this->postJson('/api/v1/candidate/profile/skills', ['name' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_tag_with_contact_details_is_rejected(): void
    {
        $profile = CandidateProfile::factory()->create();
        Sanctum::actingAs($profile->user);

        $this->postJson('/api/v1/candidate/profile/skills', ['name' => 'kontakt: marta@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertSame(0, $profile->skills()->count());
    }

    public function test_candidate_removes_a_tag(): void
    {
        $profile = CandidateProfile::factory()->create();
        $skill = Skill::factory()->create();
        $profile->skills()->attach($skill, ['source' => 'manual', 'confirmed_at' => now()]);
        Sanctum::actingAs($profile->user);

        $this->deleteJson("/api/v1/candidate/profile/skills/{$skill->id}")
            ->assertOk()
            ->assertJsonPath('data.skills', []);
    }

    public function test_employers_get_403(): void
    {
        Sanctum::actingAs(User::factory()->employer()->create());

        $this->postJson('/api/v1/candidate/profile/skills', ['name' => 'Rekrutacja IT'])->assertForbidden();
    }
}
