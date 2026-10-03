<?php

namespace Tests\Feature\Api\Employer;

use App\Models\Company;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Employer\InteractsWithEmployerFixtures;
use Tests\TestCase;

class SkillTest extends TestCase
{
    use InteractsWithEmployerFixtures, RefreshDatabase;

    public function test_autocomplete_returns_matching_skills(): void
    {
        Skill::factory()->create(['name' => 'Rekrutacja IT']);
        Skill::factory()->create(['name' => 'Employer branding']);
        Sanctum::actingAs($this->employer());

        $this->getJson('/api/v1/employer/skills?q=rekru')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Rekrutacja IT')
            ->assertJsonStructure(['data' => [['id', 'name']]]);
    }

    public function test_free_text_skills_typed_by_candidates_are_not_suggested(): void
    {
        $company = Company::factory()->create();
        $this->publishedOffer($company, [$this->skill('Rekrutacja w ofercie')]);
        $this->skill('Rekrutacja prywatna – tag kandydatki');
        Sanctum::actingAs($this->employer());

        $this->getJson('/api/v1/employer/skills?q=rekru')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Rekrutacja w ofercie');
    }
}
