<?php

namespace Tests\Feature\Api\Employer;

use App\Models\CandidateDecision;
use App\Models\Invitation;
use App\Models\JobOffer;
use App\Models\OfferInterest;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Employer\InteractsWithEmployerFixtures;
use Tests\TestCase;

class CandidateTest extends TestCase
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

    public function test_next_returns_the_best_matching_anonymous_candidate_with_counters(): void
    {
        $employer = $this->employer();
        $offer = $this->publishedOffer($employer->company, [$this->recruitment, $this->onboarding]);
        $best = $this->candidate([$this->recruitment, $this->onboarding]);
        $this->candidate([$this->recruitment], name: 'Anna Nowak');
        Sanctum::actingAs($employer);

        $this->getJson("/api/v1/employer/offers/{$offer->id}/candidates/next")
            ->assertOk()
            ->assertJsonPath('data.offer.id', $offer->id)
            ->assertJsonPath('data.candidate.id', $best->id)
            ->assertJsonPath('data.candidate.anonymous_name', 'Marta K.')
            ->assertJsonPath('data.candidate.match.score', 100)
            ->assertJsonPath('data.candidate.is_interested', false)
            ->assertJsonPath('data.remaining_count', 2)
            ->assertJsonPath('data.statistics.to_review_count', 2)
            ->assertJsonPath('data.invitation_stats.invited', 0)
            ->assertJsonPath('data.saved', []);
    }

    public function test_no_employer_payload_exposes_private_candidate_data(): void
    {
        $employer = $this->employer();
        $offer = $this->publishedOffer($employer->company, [$this->recruitment]);
        $private = [
            'due_date' => '2027-01-15',
            'leave_starts_on' => '2026-12-01',
            'cv_path' => 'cvs/secret-cv.pdf',
            'cv_text' => 'Tajny tekst CV',
        ];
        $candidate = $this->candidate([$this->recruitment], $private);
        $saved = $this->candidate([$this->recruitment], $private, 'Joanna Saved-Person');
        $offer->decisions()->create(['candidate_profile_id' => $saved->id, 'decision' => 'saved']);
        Sanctum::actingAs($employer);

        $responses = [
            $this->getJson("/api/v1/employer/offers/{$offer->id}/candidates/next")->assertOk()->assertJsonPath('data.candidate.id', $candidate->id),
            $this->getJson("/api/v1/employer/offers/{$offer->id}/candidates/next?candidate={$saved->id}")->assertOk()->assertJsonPath('data.candidate.id', $saved->id),
            $this->getJson("/api/v1/employer/offers/{$offer->id}/candidates/saved")->assertOk()->assertJsonPath('data.0.id', $saved->id),
        ];

        foreach ($responses as $response) {
            $payload = $response->getContent();
            foreach (['Kowalska', 'Saved-Person', $candidate->user->email, $saved->user->email, '2027-01-15', '2026-12-01', 'secret-cv', 'Tajny tekst', 'due_date', 'leave_starts_on', 'cv_path', 'cv_text', 'email'] as $secret) {
                $this->assertStringNotContainsString($secret, (string) $payload);
            }
        }
    }

    public function test_candidates_hidden_from_the_company_never_appear(): void
    {
        $employer = $this->employer();
        $offer = $this->publishedOffer($employer->company, [$this->recruitment]);
        $hidden = $this->candidate([$this->recruitment], ['hidden_from_company_id' => $employer->company_id]);
        $offer->decisions()->create(['candidate_profile_id' => $hidden->id, 'decision' => 'saved']);
        Sanctum::actingAs($employer);

        $this->getJson("/api/v1/employer/offers/{$offer->id}/candidates/next?candidate={$hidden->id}")
            ->assertOk()
            ->assertJsonPath('data.candidate', null)
            ->assertJsonPath('data.saved', []);
        $this->getJson("/api/v1/employer/offers/{$offer->id}/candidates/saved")->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/v1/employer/offers/{$offer->id}/candidates/{$hidden->id}/decision", ['decision' => 'skipped'])->assertNotFound();
    }

    public function test_interested_candidates_come_first(): void
    {
        $employer = $this->employer();
        $offer = $this->publishedOffer($employer->company, [$this->recruitment, $this->onboarding]);
        $this->candidate([$this->recruitment, $this->onboarding]);
        $interested = $this->candidate([$this->recruitment], name: 'Ewa Zielińska');
        OfferInterest::factory()->create(['job_offer_id' => $offer->id, 'candidate_profile_id' => $interested->id]);
        Sanctum::actingAs($employer);

        $this->getJson("/api/v1/employer/offers/{$offer->id}/candidates/next")
            ->assertJsonPath('data.candidate.id', $interested->id)
            ->assertJsonPath('data.candidate.is_interested', true);
    }

    public function test_saving_moves_the_candidate_to_the_saved_list(): void
    {
        $employer = $this->employer();
        $offer = $this->publishedOffer($employer->company, [$this->recruitment]);
        $candidate = $this->candidate([$this->recruitment]);
        Sanctum::actingAs($employer);

        $this->postJson("/api/v1/employer/offers/{$offer->id}/candidates/{$candidate->id}/decision", ['decision' => 'saved'])
            ->assertOk()
            ->assertExactJson(['data' => ['offer_id' => $offer->id, 'candidate_id' => $candidate->id, 'decision' => 'saved']]);

        $this->getJson("/api/v1/employer/offers/{$offer->id}/candidates/next")
            ->assertJsonPath('data.candidate', null)
            ->assertJsonPath('data.remaining_count', 0)
            ->assertJsonPath('data.saved.0.id', $candidate->id)
            ->assertJsonPath('data.saved.0.anonymous_name', 'Marta K.');

        $this->getJson("/api/v1/employer/offers/{$offer->id}/candidates/next?candidate={$candidate->id}")
            ->assertJsonPath('data.candidate.id', $candidate->id)
            ->assertJsonPath('data.is_saved_candidate', true);

        $this->getJson("/api/v1/employer/offers/{$offer->id}/candidates/saved")
            ->assertJsonPath('data.0.id', $candidate->id)
            ->assertJsonPath('data.0.match.score', 100);
    }

    public function test_invited_decision_is_not_downgraded(): void
    {
        $employer = $this->employer();
        $offer = $this->publishedOffer($employer->company, [$this->recruitment]);
        $candidate = $this->candidate([$this->recruitment]);
        Invitation::factory()->for($offer)->for($candidate)->create();
        $offer->decisions()->create(['candidate_profile_id' => $candidate->id, 'decision' => 'invited']);
        Sanctum::actingAs($employer);

        $this->postJson("/api/v1/employer/offers/{$offer->id}/candidates/{$candidate->id}/decision", ['decision' => 'skipped'])
            ->assertOk()
            ->assertJsonPath('data.decision', 'invited');
    }

    public function test_decision_must_be_skipped_or_saved(): void
    {
        $employer = $this->employer();
        $offer = $this->publishedOffer($employer->company, [$this->recruitment]);
        $candidate = $this->candidate([$this->recruitment]);
        Sanctum::actingAs($employer);

        $this->postJson("/api/v1/employer/offers/{$offer->id}/candidates/{$candidate->id}/decision", ['decision' => 'invited'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('decision');

        $this->assertSame(0, CandidateDecision::count());
    }

    public function test_candidates_who_do_not_match_the_offer_cannot_be_decided_on(): void
    {
        $employer = $this->employer();
        $offer = $this->publishedOffer($employer->company, [$this->recruitment]);
        $availableTooLate = $this->candidate([$this->recruitment], ['available_from' => '2028-06-01']);
        Sanctum::actingAs($employer);

        $this->postJson("/api/v1/employer/offers/{$offer->id}/candidates/{$availableTooLate->id}/decision", ['decision' => 'saved'])
            ->assertNotFound();

        $this->assertSame(0, CandidateDecision::count());
    }

    public function test_employer_cannot_review_another_company_offer_or_a_draft(): void
    {
        $employer = $this->employer();
        $foreignOffer = $this->publishedOffer($this->employer()->company, [$this->recruitment]);
        $draft = JobOffer::factory()->for($employer->company)->create();
        $candidate = $this->candidate([$this->recruitment]);
        Sanctum::actingAs($employer);

        $this->getJson("/api/v1/employer/offers/{$foreignOffer->id}/candidates/next")->assertForbidden();
        $this->getJson("/api/v1/employer/offers/{$foreignOffer->id}/candidates/saved")->assertForbidden();
        $this->postJson("/api/v1/employer/offers/{$foreignOffer->id}/candidates/{$candidate->id}/decision", ['decision' => 'skipped'])->assertForbidden();
        $this->getJson("/api/v1/employer/offers/{$draft->id}/candidates/next")->assertForbidden();

        $this->assertSame(0, CandidateDecision::count());
    }

    public function test_next_does_not_query_per_candidate(): void
    {
        $employer = $this->employer();
        $offer = $this->publishedOffer($employer->company, [$this->recruitment, $this->onboarding]);

        foreach (range(1, 20) as $index) {
            $candidate = $this->candidate([$this->recruitment, $this->onboarding], name: "Kandydatka{$index} Nowak");

            if ($index <= 5) {
                $offer->decisions()->create(['candidate_profile_id' => $candidate->id, 'decision' => 'saved']);
            }
        }

        Sanctum::actingAs($employer);
        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        $this->getJson("/api/v1/employer/offers/{$offer->id}/candidates/next")
            ->assertOk()
            ->assertJsonCount(5, 'data.saved')
            ->assertJsonPath('data.remaining_count', 15);

        $this->assertLessThanOrEqual(20, $queryCount);
    }
}
