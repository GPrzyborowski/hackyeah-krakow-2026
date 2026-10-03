<?php

namespace Tests\Feature\Api\Employer;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Employer\InteractsWithEmployerFixtures;
use Tests\TestCase;

class SkillTest extends TestCase
{
    use InteractsWithEmployerFixtures, RefreshDatabase;

    public function test_autocomplete_returns_matching_skills(): void
    {
        $this->skill('Rekrutacja IT');
        $this->skill('Employer branding');
        Sanctum::actingAs($this->employer());

        $this->getJson('/api/v1/employer/skills?q=rekru')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Rekrutacja IT')
            ->assertJsonStructure(['data' => [['id', 'name']]]);
    }
}
