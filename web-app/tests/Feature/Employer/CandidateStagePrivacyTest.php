<?php

namespace Tests\Feature\Employer;

use App\Enums\CandidateStage;
use App\Enums\DayPart;
use App\Enums\JobSharePairStatus;
use App\Models\CandidateProfile;
use App\Models\Invitation;
use App\Models\JobOffer;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\JobSharing\InteractsWithJobSharingFixtures;
use Tests\TestCase;

/**
 * The stage (pregnant / after leave) is private: no employer-facing or partner-facing payload may carry it,
 * neither before nor after an invitation is accepted.
 */
class CandidateStagePrivacyTest extends TestCase
{
    use InteractsWithJobSharingFixtures, RefreshDatabase;

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
        $this->candidate = $this->candidate([$this->recruitment], ['stage' => CandidateStage::Pregnant]);
    }

    public function test_anonymous_web_and_api_payloads_have_no_stage(): void
    {
        $invited = $this->candidate([$this->recruitment], ['stage' => CandidateStage::AfterLeave], 'Anna Zaproszona');
        Invitation::factory()->for($this->offer)->for($invited)->create();
        $saved = $this->candidate([$this->recruitment], ['stage' => CandidateStage::AfterLeave], 'Joanna Zapisana');
        $this->offer->decisions()->create(['candidate_profile_id' => $saved->id, 'decision' => 'saved']);

        $this->assertNoStage($this->actingAs($this->employer)->get(route('employer.candidates.index', ['offer' => $this->offer->id]))->assertOk()->assertSee('Marta K.'));
        $this->assertNoStage($this->actingAs($this->employer)->get(route('employer.invitations.index'))->assertOk()->assertSee('Anna Z.'));

        Sanctum::actingAs($this->employer);
        $this->assertNoStage($this->getJson("/api/v1/employer/offers/{$this->offer->id}/candidates/next")->assertOk()->assertJsonPath('data.candidate.id', $this->candidate->id));
        $this->assertNoStage($this->getJson("/api/v1/employer/offers/{$this->offer->id}/candidates/saved")->assertOk()->assertJsonPath('data.0.id', $saved->id));
        $this->assertNoStage($this->getJson('/api/v1/employer/invitations')->assertOk()->assertJsonPath('data.0.status', 'pending'));
    }

    public function test_revealed_payloads_after_acceptance_have_no_stage(): void
    {
        $conversation = Invitation::factory()->for($this->offer)->for($this->candidate)->create()->accept();

        $this->assertNoStage($this->actingAs($this->employer)->get(route('employer.invitations.index'))->assertOk()->assertSee('Marta Kowalska'));
        $this->assertNoStage($this->actingAs($this->employer)->get(route('conversations.show', $conversation))->assertOk()->assertSee('Marta Kowalska'));

        Sanctum::actingAs($this->employer);
        $this->assertNoStage($this->getJson('/api/v1/employer/invitations')->assertOk()->assertJsonPath('data.0.candidate.full_name', 'Marta Kowalska'));
        $this->assertNoStage($this->getJson("/api/v1/conversations/{$conversation->id}")->assertOk());
    }

    public function test_job_sharing_payloads_for_employers_and_partners_have_no_stage(): void
    {
        $offer = $this->jobShareOffer($this->employer->company, [$this->recruitment]);
        $marta = $this->sharer([$this->recruitment], 'Marta Kowalska', DayPart::Morning, ['stage' => CandidateStage::Pregnant]);
        $ewa = $this->sharer([$this->recruitment], 'Ewa Nowak', DayPart::Afternoon, ['stage' => CandidateStage::AfterLeave]);
        $this->scheduledPair($offer, $marta, $ewa, JobSharePairStatus::Submitted);

        $this->assertNoStage($this->actingAs($this->employer)->get(route('employer.offers.job-share-pairs.index', $offer))->assertOk()->assertSee('Ewa N.'));
        Sanctum::actingAs($this->employer);
        $this->assertNoStage($this->getJson("/api/v1/employer/offers/{$offer->id}/job-share-pairs")->assertOk()->assertJsonCount(1, 'data'));

        $this->sharer([$this->recruitment], 'Kasia Wolna', DayPart::Afternoon, ['stage' => CandidateStage::AfterLeave]);
        $loner = $this->sharer([$this->recruitment], 'Ola Mazur', DayPart::Morning);
        Sanctum::actingAs($loner->user);
        $this->assertNoStage($this->getJson("/api/v1/job-sharing/offers/{$offer->id}/partners")->assertOk()->assertJsonPath('data.0.anonymous_name', 'Kasia W.'));
    }

    public function test_employer_preview_shows_the_candidate_her_profile_without_stage(): void
    {
        Sanctum::actingAs($this->candidate->user);

        $this->assertNoStage($this->getJson('/api/v1/candidate/profile/employer-preview')->assertOk());
    }

    private function assertNoStage(TestResponse $response): void
    {
        $payload = (string) $response->getContent();

        foreach (['"stage', '&quot;stage', 'pregnant', 'after_leave', 'Po urlopie macierzyńskim', 'W ciąży'] as $secret) {
            $this->assertStringNotContainsString($secret, $payload);
        }
    }
}
