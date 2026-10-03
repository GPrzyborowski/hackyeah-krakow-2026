<?php

namespace Tests\Feature\Employer;

use App\Enums\CandidateDecisionType;
use App\Models\CandidateDecision;
use App\Models\JobOffer;
use App\Models\OfferInterest;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CandidateSwipeTest extends TestCase
{
    use InteractsWithEmployerFixtures, RefreshDatabase;

    private Skill $recruitment;

    private Skill $onboarding;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recruitment = $this->skill('Rekrutacja IT');
        $this->onboarding = $this->skill('Onboarding');
    }

    public function test_card_shows_the_best_matching_anonymous_candidate()
    {
        $employer = $this->employer();
        $offer = $this->publishedOffer($employer->company, [$this->recruitment, $this->onboarding]);
        $this->candidate([$this->recruitment], name: 'Anna Wiśniewska');
        $best = $this->candidate([$this->recruitment, $this->onboarding], ['headline' => 'Rekruterka IT', 'ai_summary' => 'Prowadziła rekrutacje techniczne.']);

        $this->actingAs($employer)
            ->get("/employer/candidates?offer={$offer->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('employer/candidates/Index')
                ->where('currentOffer.id', $offer->id)
                ->where('candidate.id', $best->id)
                ->where('candidate.anonymous_name', 'Marta K.')
                ->where('candidate.ai_summary', 'Prowadziła rekrutacje techniczne.')
                ->where('candidate.match.score', 100)
                ->where('remainingCount', 2)
                ->where('offers.0.matched_count', 2)
                ->where('offers.0.to_review_count', 2));
    }

    public function test_swipe_payload_never_exposes_private_candidate_data()
    {
        $employer = $this->employer();
        $offer = $this->publishedOffer($employer->company, [$this->recruitment]);
        $candidate = $this->candidate([$this->recruitment], [
            'due_date' => '2027-01-15',
            'leave_starts_on' => '2026-12-01',
            'cv_path' => 'cvs/secret-cv.pdf',
        ]);
        $offer->decisions()->create(['candidate_profile_id' => $this->candidate([$this->recruitment], name: 'Joanna Saved-Person')->id, 'decision' => 'saved']);

        $response = $this->actingAs($employer)->get("/employer/candidates?offer={$offer->id}");

        $response->assertInertia(fn (Assert $page) => $page->where('candidate.id', $candidate->id));
        $payload = json_encode($response->viewData('page')['props']);
        $this->assertStringNotContainsString('Kowalska', $payload);
        $this->assertStringNotContainsString('Saved-Person', $payload);
        $this->assertStringNotContainsString($candidate->user->email, $payload);
        $this->assertStringNotContainsString('2027-01-15', $payload);
        $this->assertStringNotContainsString('2026-12-01', $payload);
        $this->assertStringNotContainsString('secret-cv', $payload);
        $this->assertStringNotContainsString('due_date', $payload);
        $this->assertStringNotContainsString('cv_path', $payload);
    }

    public function test_candidates_hidden_from_the_company_never_appear()
    {
        $employer = $this->employer();
        $offer = $this->publishedOffer($employer->company, [$this->recruitment]);
        $hidden = $this->candidate([$this->recruitment], ['hidden_from_company_id' => $employer->company_id]);

        $this->actingAs($employer)
            ->get("/employer/candidates?offer={$offer->id}")
            ->assertInertia(fn (Assert $page) => $page->where('candidate', null)->where('remainingCount', 0));

        $this->actingAs($employer)
            ->post("/employer/offers/{$offer->id}/candidates/{$hidden->id}/decision", ['decision' => 'saved'])
            ->assertNotFound();
    }

    public function test_unconfirmed_skills_are_not_shown()
    {
        $employer = $this->employer();
        $offer = $this->publishedOffer($employer->company, [$this->recruitment]);
        $candidate = $this->candidate([$this->recruitment]);
        $candidate->skills()->attach($this->skill('Tajna umiejętność'), ['source' => 'ai', 'confirmed_at' => null]);

        $this->actingAs($employer)
            ->get("/employer/candidates?offer={$offer->id}")
            ->assertInertia(fn (Assert $page) => $page->where('candidate.skills', [['name' => 'Rekrutacja IT', 'matched' => true]]));
    }

    public function test_interested_candidates_come_first_and_are_labelled()
    {
        $employer = $this->employer();
        $offer = $this->publishedOffer($employer->company, [$this->recruitment, $this->onboarding]);
        $this->candidate([$this->recruitment, $this->onboarding]);
        $interested = $this->candidate([$this->recruitment], name: 'Ewa Zielińska');
        OfferInterest::factory()->create(['job_offer_id' => $offer->id, 'candidate_profile_id' => $interested->id]);

        $this->actingAs($employer)
            ->get("/employer/candidates?offer={$offer->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('candidate.id', $interested->id)
                ->where('candidate.is_interested', true));
    }

    public function test_skipping_moves_to_the_next_candidate()
    {
        $employer = $this->employer();
        $offer = $this->publishedOffer($employer->company, [$this->recruitment]);
        $first = $this->candidate([$this->recruitment]);

        $this->actingAs($employer)
            ->post("/employer/offers/{$offer->id}/candidates/{$first->id}/decision", ['decision' => 'skipped'])
            ->assertRedirect(route('employer.candidates.index', ['offer' => $offer->id]));

        $this->assertSame(CandidateDecisionType::Skipped, CandidateDecision::sole()->decision);

        $this->actingAs($employer)
            ->get("/employer/candidates?offer={$offer->id}")
            ->assertInertia(fn (Assert $page) => $page->where('candidate', null)->where('offers.0.to_review_count', 0));
    }

    public function test_saved_candidate_is_listed_and_can_be_brought_back()
    {
        $employer = $this->employer();
        $offer = $this->publishedOffer($employer->company, [$this->recruitment]);
        $candidate = $this->candidate([$this->recruitment]);

        $this->actingAs($employer)->post("/employer/offers/{$offer->id}/candidates/{$candidate->id}/decision", ['decision' => 'saved']);

        $this->actingAs($employer)
            ->get("/employer/candidates?offer={$offer->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('candidate', null)
                ->where('saved.0.id', $candidate->id)
                ->where('saved.0.anonymous_name', 'Marta K.'));

        $this->actingAs($employer)
            ->get("/employer/candidates?offer={$offer->id}&candidate={$candidate->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->where('candidate.id', $candidate->id)
                ->where('isSavedCandidate', true));

        $this->actingAs($employer)->post("/employer/offers/{$offer->id}/candidates/{$candidate->id}/decision", ['decision' => 'skipped']);
        $this->assertSame(CandidateDecisionType::Skipped, CandidateDecision::sole()->decision);
    }

    public function test_decision_endpoint_rejects_invite_and_unknown_decisions()
    {
        $employer = $this->employer();
        $offer = $this->publishedOffer($employer->company, [$this->recruitment]);
        $candidate = $this->candidate([$this->recruitment]);

        $this->actingAs($employer)
            ->post("/employer/offers/{$offer->id}/candidates/{$candidate->id}/decision", ['decision' => 'invited'])
            ->assertSessionHasErrors('decision');

        $this->assertSame(0, CandidateDecision::count());
    }

    public function test_employer_cannot_review_candidates_for_another_company_offer()
    {
        $employer = $this->employer();
        $foreignOffer = $this->publishedOffer($this->employer()->company, [$this->recruitment]);
        $candidate = $this->candidate([$this->recruitment]);

        $this->actingAs($employer)->get("/employer/candidates?offer={$foreignOffer->id}")->assertForbidden();
        $this->actingAs($employer)
            ->post("/employer/offers/{$foreignOffer->id}/candidates/{$candidate->id}/decision", ['decision' => 'skipped'])
            ->assertForbidden();

        $this->assertSame(0, CandidateDecision::count());
    }

    public function test_draft_offers_cannot_be_reviewed()
    {
        $employer = $this->employer();
        $draft = JobOffer::factory()->for($employer->company)->create();
        $candidate = $this->candidate([$this->recruitment]);

        $this->actingAs($employer)
            ->post("/employer/offers/{$draft->id}/candidates/{$candidate->id}/decision", ['decision' => 'skipped'])
            ->assertForbidden();
    }

    public function test_empty_state_without_published_offers()
    {
        $this->actingAs($this->employer())
            ->get('/employer/candidates')
            ->assertInertia(fn (Assert $page) => $page->where('offers', [])->where('candidate', null));
    }
}
