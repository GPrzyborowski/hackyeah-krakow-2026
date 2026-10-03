<?php

namespace Tests\Feature\Employer;

use App\Enums\DayPart;
use App\Enums\JobSharePairStatus;
use App\Models\CandidateProfile;
use App\Models\Invitation;
use App\Models\JobOffer;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\JobSharing\InteractsWithJobSharingFixtures;
use Tests\TestCase;

/**
 * Phone and photo are contact data: no employer-facing payload may carry them before the candidate accepts an invitation.
 */
class CandidateContactPrivacyTest extends TestCase
{
    use InteractsWithJobSharingFixtures, RefreshDatabase;

    private const array CONTACT = ['phone' => '+48 600 100 200', 'photo_path' => 'photos/marta-secret.jpg'];

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
        $this->candidate = $this->candidate([$this->recruitment], self::CONTACT);
    }

    public function test_web_payloads_before_acceptance_have_no_phone_or_photo(): void
    {
        $invited = $this->candidate([$this->recruitment], [...self::CONTACT, 'photo_path' => 'photos/anna-secret.jpg'], 'Anna Zaproszona');
        Invitation::factory()->for($this->offer)->for($invited)->create();

        $this->assertNoContactData($this->actingAs($this->employer)->get(route('employer.candidates.index', ['offer' => $this->offer->id]))->assertOk()->assertSee('Marta K.'));
        $this->assertNoContactData($this->actingAs($this->employer)->get(route('employer.invitations.index'))->assertOk()->assertSee('Anna Z.'));
    }

    public function test_api_payloads_before_acceptance_have_no_phone_or_photo(): void
    {
        $saved = $this->candidate([$this->recruitment], [...self::CONTACT, 'photo_path' => 'photos/saved-secret.jpg'], 'Joanna Zapisana');
        $this->offer->decisions()->create(['candidate_profile_id' => $saved->id, 'decision' => 'saved']);
        $invited = $this->candidate([$this->recruitment], [...self::CONTACT, 'photo_path' => 'photos/anna-secret.jpg'], 'Anna Zaproszona');
        Invitation::factory()->for($this->offer)->for($invited)->create();
        Sanctum::actingAs($this->employer);

        $this->assertNoContactData($this->getJson("/api/v1/employer/offers/{$this->offer->id}/candidates/next")->assertOk()->assertJsonPath('data.candidate.id', $this->candidate->id));
        $this->assertNoContactData($this->getJson("/api/v1/employer/offers/{$this->offer->id}/candidates/saved")->assertOk()->assertJsonPath('data.0.id', $saved->id));
        $this->assertNoContactData($this->getJson('/api/v1/employer/invitations')->assertOk()->assertJsonPath('data.0.status', 'pending'));
    }

    public function test_job_sharing_payloads_have_no_phone_or_photo(): void
    {
        $offer = $this->jobShareOffer($this->employer->company, [$this->recruitment]);
        $marta = $this->sharer([$this->recruitment], 'Marta Kowalska', DayPart::Morning, self::CONTACT);
        $ewa = $this->sharer([$this->recruitment], 'Ewa Nowak', DayPart::Afternoon, [...self::CONTACT, 'photo_path' => 'photos/ewa-secret.jpg']);
        $this->scheduledPair($offer, $marta, $ewa, JobSharePairStatus::Submitted);

        Sanctum::actingAs($this->employer);
        $this->assertNoContactData($this->getJson("/api/v1/employer/offers/{$offer->id}/job-share-pairs")->assertOk()->assertJsonCount(1, 'data'));
        $this->assertNoContactData($this->actingAs($this->employer)->get(route('employer.offers.job-share-pairs.index', $offer))->assertOk()->assertSee('Ewa N.'));

        $this->sharer([$this->recruitment], 'Kasia Wolna', DayPart::Afternoon, [...self::CONTACT, 'photo_path' => 'photos/kasia-secret.jpg']);
        $loner = $this->sharer([$this->recruitment], 'Ola Mazur', DayPart::Morning);
        Sanctum::actingAs($loner->user);
        $this->assertNoContactData($this->getJson("/api/v1/job-sharing/offers/{$offer->id}/partners")->assertOk()->assertJsonPath('data.0.anonymous_name', 'Kasia W.'));
    }

    public function test_employer_preview_shows_the_candidate_what_employers_see_without_contact_data(): void
    {
        Sanctum::actingAs($this->candidate->user);

        $this->assertNoContactData($this->getJson('/api/v1/candidate/profile/employer-preview')->assertOk());
    }

    public function test_accepted_invitation_reveals_phone_and_photo_on_web(): void
    {
        $conversation = Invitation::factory()->for($this->offer)->for($this->candidate)->create()->accept();

        $this->actingAs($this->employer)
            ->get(route('employer.invitations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invitations.0.candidate.phone', '+48 600 100 200')
                ->where('invitations.0.candidate.photo_url', $this->candidate->photoUrl()));

        $this->actingAs($this->employer)
            ->get(route('conversations.show', $conversation))
            ->assertInertia(fn (Assert $page) => $page
                ->where('conversation.counterpart.phone', '+48 600 100 200')
                ->where('conversation.counterpart.photo_url', $this->candidate->photoUrl()));
    }

    public function test_accepted_invitation_reveals_phone_and_api_photo_url_on_the_api(): void
    {
        $conversation = Invitation::factory()->for($this->offer)->for($this->candidate)->create()->accept();
        Sanctum::actingAs($this->employer);

        $this->getJson('/api/v1/employer/invitations')
            ->assertOk()
            ->assertJsonPath('data.0.candidate.phone', '+48 600 100 200')
            ->assertJsonPath('data.0.candidate.photo_url', $this->candidate->photoUrl(forApi: true));

        $this->getJson("/api/v1/conversations/{$conversation->id}")
            ->assertOk()
            ->assertJsonPath('data.counterpart.phone', '+48 600 100 200')
            ->assertJsonPath('data.counterpart.photo_url', $this->candidate->photoUrl(forApi: true));
    }

    private function assertNoContactData(TestResponse $response): void
    {
        $payload = (string) $response->getContent();

        foreach (['600 100 200', 'photo_url', 'candidate-photos', 'secret.jpg', '"phone"', '&quot;phone&quot;'] as $secret) {
            $this->assertStringNotContainsString($secret, $payload);
        }
    }
}
