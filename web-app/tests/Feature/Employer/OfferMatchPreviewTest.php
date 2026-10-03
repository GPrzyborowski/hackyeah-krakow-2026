<?php

namespace Tests\Feature\Employer;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferMatchPreviewTest extends TestCase
{
    use InteractsWithEmployerFixtures, RefreshDatabase;

    public function test_preview_counts_candidates_by_skill_names_and_start_date()
    {
        $recruitment = $this->skill('Rekrutacja IT');
        $excel = $this->skill('Excel');
        $this->candidate([$recruitment, $excel]);
        $this->candidate([$recruitment]);
        $this->candidate([$recruitment], ['available_from' => '2028-01-01']);

        $this->actingAs($this->employer())
            ->postJson('/employer/offers/preview-matches', [
                'start_date' => '2027-09-01',
                'required_skills' => ['Rekrutacja IT'],
                'nice_to_have_skills' => ['Excel'],
            ])
            ->assertOk()
            ->assertExactJson(['with_required' => 2, 'with_nice_to_have' => 1]);
    }

    public function test_unknown_required_skill_matches_nobody()
    {
        $this->candidate([$this->skill('Excel')]);

        $this->actingAs($this->employer())
            ->postJson('/employer/offers/preview-matches', [
                'start_date' => '2027-09-01',
                'required_skills' => ['Excel', 'Nieistniejąca umiejętność'],
                'nice_to_have_skills' => [],
            ])
            ->assertExactJson(['with_required' => 0, 'with_nice_to_have' => 0]);
    }

    public function test_preview_excludes_candidates_hidden_from_the_company()
    {
        $employer = $this->employer();
        $excel = $this->skill('Excel');
        $this->candidate([$excel], ['hidden_from_company_id' => $employer->company_id]);

        $this->actingAs($employer)
            ->postJson('/employer/offers/preview-matches', [
                'start_date' => '2027-09-01',
                'required_skills' => ['Excel'],
                'nice_to_have_skills' => [],
            ])
            ->assertJsonPath('with_required', 0);
    }

    public function test_preview_requires_start_date()
    {
        $this->actingAs($this->employer())
            ->postJson('/employer/offers/preview-matches', ['required_skills' => [], 'nice_to_have_skills' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_date');
    }
}
