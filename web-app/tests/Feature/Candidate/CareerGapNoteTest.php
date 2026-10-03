<?php

namespace Tests\Feature\Candidate;

use App\Models\CandidateProfile;
use App\Models\Invitation;
use App\Models\JobOffer;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Employer\InteractsWithEmployerFixtures;
use Tests\TestCase;

/**
 * The private career gap note: employers never see it before acceptance, and after acceptance only when the candidate opted in.
 */
class CareerGapNoteTest extends TestCase
{
    use InteractsWithEmployerFixtures, RefreshDatabase;

    private const string NOTE = 'urlop macierzyński';

    private Skill $recruitment;

    private User $employer;

    private JobOffer $offer;

    private CandidateProfile $candidate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recruitment = $this->skill('Rekrutacja IT');
        $this->employer = $this->employer();
        $this->offer = $this->publishedOffer($this->employer->company, [$this->recruitment]);
        $this->candidate = $this->candidate([$this->recruitment], ['career_gap_note' => self::NOTE, 'show_availability_instead_of_gap' => false]);
    }

    public function test_new_profiles_hide_the_gap_by_default(): void
    {
        $profile = User::factory()->create()->candidateProfile()->create(['onboarding_step' => 1]);

        $this->assertTrue($profile->refresh()->show_availability_instead_of_gap);
    }

    public function test_candidate_saves_the_note_on_the_web_and_an_empty_one_clears_it(): void
    {
        $this->actingAs($this->candidate->user)
            ->patch(route('candidate.onboarding.privacy'), ['career_gap_note' => '  opieka nad dzieckiem  ', 'show_availability_instead_of_gap' => true])
            ->assertSessionHasNoErrors();

        $this->candidate->refresh();
        $this->assertSame('opieka nad dzieckiem', $this->candidate->career_gap_note);
        $this->assertTrue($this->candidate->show_availability_instead_of_gap);

        $this->actingAs($this->candidate->user)->patch(route('candidate.onboarding.privacy'), ['career_gap_note' => '   ']);
        $this->assertNull($this->candidate->refresh()->career_gap_note);
    }

    public function test_note_longer_than_300_characters_is_rejected(): void
    {
        $this->actingAs($this->candidate->user)
            ->patch(route('candidate.onboarding.privacy'), ['career_gap_note' => str_repeat('a', 301)])
            ->assertSessionHasErrors('career_gap_note');
    }

    public function test_candidate_updates_the_note_through_the_api(): void
    {
        Sanctum::actingAs($this->candidate->user);

        $this->patchJson('/api/v1/candidate/profile/privacy', ['career_gap_note' => 'przerwa na opiekę', 'show_availability_instead_of_gap' => true])
            ->assertOk()
            ->assertJsonPath('data.privacy.career_gap_note', 'przerwa na opiekę')
            ->assertJsonPath('data.privacy.show_availability_instead_of_gap', true);

        $this->patchJson('/api/v1/candidate/profile/privacy', ['career_gap_note' => str_repeat('a', 301)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('career_gap_note');
    }

    public function test_employer_never_sees_the_note_before_acceptance_even_when_the_candidate_opted_in(): void
    {
        $invited = $this->candidate([$this->recruitment], ['career_gap_note' => self::NOTE, 'show_availability_instead_of_gap' => false], 'Anna Zaproszona');
        Invitation::factory()->for($this->offer)->for($invited)->create();

        $this->assertNoNote($this->actingAs($this->employer)->get(route('employer.candidates.index', ['offer' => $this->offer->id]))->assertOk()->assertSee('Marta K.'));
        $this->assertNoNote($this->actingAs($this->employer)->get(route('employer.invitations.index'))->assertOk()->assertSee('Anna Z.'));

        Sanctum::actingAs($this->employer);
        $this->assertNoNote($this->getJson("/api/v1/employer/offers/{$this->offer->id}/candidates/next")->assertOk());
        $this->assertNoNote($this->getJson('/api/v1/employer/invitations')->assertOk()->assertJsonPath('data.0.status', 'pending'));
    }

    public function test_employer_preview_for_the_candidate_never_contains_the_note(): void
    {
        Sanctum::actingAs($this->candidate->user);

        $this->assertNoNote($this->getJson('/api/v1/candidate/profile/employer-preview')->assertOk());
    }

    public function test_accepted_invitation_shows_the_note_only_when_the_candidate_opted_in(): void
    {
        Invitation::factory()->for($this->offer)->for($this->candidate)->create()->accept();

        $this->actingAs($this->employer)
            ->get(route('employer.invitations.index'))
            ->assertInertia(fn (Assert $page) => $page->where('invitations.0.candidate.career_gap_note', self::NOTE));

        Sanctum::actingAs($this->employer);
        $this->getJson('/api/v1/employer/invitations')
            ->assertOk()
            ->assertJsonPath('data.0.candidate.career_gap_note', self::NOTE);
    }

    public function test_accepted_invitation_hides_the_note_while_the_toggle_is_on(): void
    {
        $this->candidate->update(['show_availability_instead_of_gap' => true]);
        Invitation::factory()->for($this->offer)->for($this->candidate)->create()->accept();

        $this->actingAs($this->employer)
            ->get(route('employer.invitations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invitations.0.candidate.full_name', 'Marta Kowalska')
                ->where('invitations.0.candidate.career_gap_note', null));

        Sanctum::actingAs($this->employer);
        $this->assertNoNote($this->getJson('/api/v1/employer/invitations')->assertOk()->assertJsonPath('data.0.candidate.career_gap_note', null));
    }

    private function assertNoNote(TestResponse $response): void
    {
        $payload = (string) $response->getContent();

        $this->assertStringNotContainsString('macierzy', $payload);
        $this->assertStringNotContainsString('macierzyń', $payload);
    }
}
