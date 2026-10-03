<?php

namespace Tests\Feature\Candidate;

use App\Enums\SkillSource;
use App\Models\CandidateProfile;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfilePageTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;

    private CandidateProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->candidate = User::factory()->create(['name' => 'Marta Kowalska']);
        $this->profile = CandidateProfile::factory()->published()->for($this->candidate)->create([
            'available_from' => '2027-03-01',
            'due_date' => '2026-12-20',
            'career_gap_note' => 'urlop macierzyński',
        ]);
    }

    public function test_published_candidate_sees_her_profile_overview(): void
    {
        $skill = Skill::factory()->create(['name' => 'Excel']);
        $this->profile->skills()->attach($skill->id, ['source' => SkillSource::Ai->value, 'confirmed_at' => null]);

        $this->actingAs($this->candidate)
            ->get(route('candidate.profile'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('candidate/Profile')
                ->where('fullName', 'Marta Kowalska')
                ->where('profile.anonymous_name', 'Marta K.')
                ->where('profile.is_published', true)
                ->where('profile.show_availability_instead_of_gap', true)
                ->where('profile.career_gap_note', 'urlop macierzyński')
                ->where('skills.0.name', 'Excel')
                ->where('skills.0.confirmed', false)
                ->where('calendar.available_from', '2027-03-01')
                ->where('calendar.due_date', '2026-12-20')
                ->has('workModes')
                ->has('employmentFractions')
                ->has('companies'));
    }

    public function test_candidate_who_did_not_finish_the_wizard_is_sent_to_onboarding(): void
    {
        $this->profile->update(['published_at' => null, 'onboarding_step' => 2]);

        $this->actingAs($this->candidate)
            ->get(route('candidate.profile'))
            ->assertRedirect(route('candidate.onboarding.show'));
    }

    public function test_hidden_finished_profile_is_still_available(): void
    {
        $this->profile->update(['published_at' => null]);

        $this->actingAs($this->candidate)
            ->get(route('candidate.profile'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('profile.is_published', false));
    }

    public function test_employers_and_guests_cannot_open_the_candidate_profile_page(): void
    {
        $this->get(route('candidate.profile'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->employer()->create())
            ->get(route('candidate.profile'))
            ->assertForbidden();
    }

    public function test_preferences_saved_from_the_profile_page_return_to_it(): void
    {
        $this->actingAs($this->candidate)
            ->put(route('candidate.profile.preferences'), [
                'available_from' => '2027-05-01',
                'headline' => 'Rekruterka IT',
                'work_modes' => ['remote'],
                'employment_fractions' => ['3/5'],
            ])
            ->assertRedirect(route('candidate.profile'));

        $this->profile->refresh();
        $this->assertSame('2027-05-01', $this->profile->available_from->toDateString());
        $this->assertSame('Rekruterka IT', $this->profile->headline);
    }

    public function test_skills_confirmed_from_the_profile_page_become_visible_and_return_to_it(): void
    {
        $skill = Skill::factory()->create(['name' => 'Excel']);
        $this->profile->skills()->attach($skill->id, ['source' => SkillSource::Ai->value, 'confirmed_at' => null]);

        $this->actingAs($this->candidate)
            ->post(route('candidate.profile.skills.confirm'))
            ->assertRedirect(route('candidate.profile'));

        $this->assertTrue($this->profile->confirmedSkills()->whereKey($skill->id)->exists());
    }
}
