<?php

namespace Tests\Feature\Api\Employer;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Employer\InteractsWithEmployerFixtures;
use Tests\TestCase;

class OfferMatchPreviewTest extends TestCase
{
    use InteractsWithEmployerFixtures, RefreshDatabase;

    public function test_preview_counts_visible_candidates_by_skill_names(): void
    {
        $employer = $this->employer();
        $recruitment = $this->skill('Rekrutacja IT');
        $onboarding = $this->skill('Onboarding');
        $this->candidate([$recruitment, $onboarding]);
        $this->candidate([$recruitment]);
        $this->candidate([$recruitment], ['hidden_from_company_id' => $employer->company_id]);
        Sanctum::actingAs($employer);

        $this->postJson('/api/v1/employer/offers/preview-matches', [
            'start_date' => '2027-09-01',
            'required_skills' => ['Rekrutacja IT'],
            'nice_to_have_skills' => ['Onboarding'],
        ])
            ->assertOk()
            ->assertExactJson(['data' => ['with_required' => 2, 'with_nice_to_have' => 1]]);
    }

    public function test_preview_requires_start_date(): void
    {
        Sanctum::actingAs($this->employer());

        $this->postJson('/api/v1/employer/offers/preview-matches', ['required_skills' => [], 'nice_to_have_skills' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_date');
    }
}
