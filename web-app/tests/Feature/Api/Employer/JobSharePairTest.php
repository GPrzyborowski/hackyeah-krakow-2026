<?php

namespace Tests\Feature\Api\Employer;

use App\Enums\InvitationStatus;
use App\Enums\JobSharePairStatus;
use App\Models\Company;
use App\Models\Invitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\JobSharing\InteractsWithJobSharingFixtures;
use Tests\TestCase;

class JobSharePairTest extends TestCase
{
    use InteractsWithJobSharingFixtures, RefreshDatabase;

    private const string MESSAGE = 'Dzień dobry, zapraszamy Was na rozmowę o stanowisku w modelu job sharing.';

    public function test_employer_sees_submitted_pairs_anonymously_with_skill_coverage(): void
    {
        $employer = $this->employer();
        $recruitment = $this->skill('Rekrutacja IT');
        $onboarding = $this->skill('Onboarding');
        $offer = $this->jobShareOffer($employer->company, [$recruitment, $onboarding]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $ewa = $this->sharer([$onboarding], 'Ewa Nowak', attributes: ['due_date' => '2027-01-15']);
        $pair = $this->scheduledPair($offer, $marta, $ewa, JobSharePairStatus::Submitted);
        $this->pair($offer, $this->sharer([$recruitment], 'Anna Zielińska'), $this->sharer([$recruitment], 'Ola Mazur'));
        Sanctum::actingAs($employer);

        $response = $this->getJson("/api/v1/employer/offers/{$offer->id}/job-share-pairs")
            ->assertOk()
            ->assertJsonPath('offer.id', $offer->id)
            ->assertJsonPath('offer.workday_starts_at', '08:00')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pair->id)
            ->assertJsonPath('data.0.status', 'submitted')
            ->assertJsonPath('data.0.members.0.anonymous_name', 'Marta K.')
            ->assertJsonPath('data.0.members.1.anonymous_name', 'Ewa N.')
            ->assertJsonPath('data.0.coverage.percent', 100)
            ->assertJsonPath('data.0.schedule.1.starts_at', '12:00');

        $payload = (string) $response->getContent();
        $this->assertStringNotContainsString($marta->user->email, $payload);
        $this->assertStringNotContainsString('Kowalska', $payload);
        $this->assertStringNotContainsString('2027-01-15', $payload);
    }

    public function test_pairs_with_a_member_who_hid_her_profile_are_invisible(): void
    {
        $employer = $this->employer();
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer($employer->company, [$recruitment]);
        $hidden = $this->sharer([$recruitment], 'Ewa Nowak', attributes: ['hidden_from_company_id' => $employer->company_id]);
        $pair = $this->scheduledPair($offer, $this->sharer([$recruitment], 'Marta Kowalska'), $hidden, JobSharePairStatus::Submitted);
        Sanctum::actingAs($employer);

        $this->getJson("/api/v1/employer/offers/{$offer->id}/job-share-pairs")->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/v1/employer/job-share-pairs/{$pair->id}/invitation", ['message' => self::MESSAGE])->assertNotFound();
        $this->postJson("/api/v1/employer/job-share-pairs/{$pair->id}/reject")->assertNotFound();
    }

    public function test_regular_offers_have_no_pairs_list(): void
    {
        $employer = $this->employer();
        $offer = $this->publishedOffer($employer->company, [$this->skill('Excel')]);
        Sanctum::actingAs($employer);

        $this->getJson("/api/v1/employer/offers/{$offer->id}/job-share-pairs")->assertNotFound();
    }

    public function test_employer_cannot_see_or_decide_on_pairs_of_other_companies(): void
    {
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer(Company::factory()->create(), [$recruitment]);
        $pair = $this->scheduledPair($offer, $this->sharer([$recruitment], 'Marta Kowalska'), $this->sharer([$recruitment], 'Ewa Nowak'), JobSharePairStatus::Submitted);
        Sanctum::actingAs($this->employer());

        $this->getJson("/api/v1/employer/offers/{$offer->id}/job-share-pairs")->assertForbidden();
        $this->postJson("/api/v1/employer/job-share-pairs/{$pair->id}/invitation", ['message' => self::MESSAGE])->assertNotFound();
        $this->postJson("/api/v1/employer/job-share-pairs/{$pair->id}/reject")->assertNotFound();

        $this->assertSame(JobSharePairStatus::Submitted, $pair->fresh()?->status);
    }

    public function test_inviting_a_pair_creates_one_invitation_per_member(): void
    {
        $employer = $this->employer();
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer($employer->company, [$recruitment]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $ewa = $this->sharer([$recruitment], 'Ewa Nowak');
        $pair = $this->scheduledPair($offer, $marta, $ewa, JobSharePairStatus::Submitted);
        Sanctum::actingAs($employer);

        $this->postJson("/api/v1/employer/job-share-pairs/{$pair->id}/invitation", ['message' => self::MESSAGE])
            ->assertCreated()
            ->assertJsonPath('data.status', 'invited')
            ->assertJsonPath('data.status_label', 'Zaproszona')
            ->assertJsonCount(2, 'data.members');

        $invitations = Invitation::all();
        $this->assertEqualsCanonicalizing([$marta->id, $ewa->id], $invitations->pluck('candidate_profile_id')->all());
        $this->assertTrue($invitations->every(fn (Invitation $invitation): bool => $invitation->status === InvitationStatus::Pending && $invitation->job_share_pair_id === $pair->id));

        $this->postJson("/api/v1/employer/job-share-pairs/{$pair->id}/invitation", ['message' => self::MESSAGE])->assertForbidden();
    }

    public function test_pair_invitation_message_is_moderated(): void
    {
        $employer = $this->employer();
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer($employer->company, [$recruitment]);
        $pair = $this->scheduledPair($offer, $this->sharer([$recruitment], 'Marta Kowalska'), $this->sharer([$recruitment], 'Ewa Nowak'), JobSharePairStatus::Submitted);
        Sanctum::actingAs($employer);

        $this->postJson("/api/v1/employer/job-share-pairs/{$pair->id}/invitation", ['message' => 'Czy planujecie dzieci?'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['message', 'message_suggestion']);

        $this->assertSame(0, Invitation::count());
    }

    public function test_employer_rejects_a_pair(): void
    {
        $employer = $this->employer();
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer($employer->company, [$recruitment]);
        $pair = $this->scheduledPair($offer, $this->sharer([$recruitment], 'Marta Kowalska'), $this->sharer([$recruitment], 'Ewa Nowak'), JobSharePairStatus::Submitted);
        Sanctum::actingAs($employer);

        $this->postJson("/api/v1/employer/job-share-pairs/{$pair->id}/reject")
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertSame(JobSharePairStatus::Rejected, $pair->fresh()?->status);
    }

    public function test_hired_and_declined_pairs_are_listed_with_their_labels(): void
    {
        $employer = $this->employer();
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer($employer->company, [$recruitment]);
        $hired = $this->scheduledPair($offer, $this->sharer([$recruitment], 'Marta Kowalska'), $this->sharer([$recruitment], 'Ewa Nowak'), JobSharePairStatus::Hired);
        $declined = $this->scheduledPair($offer, $this->sharer([$recruitment], 'Anna Zielińska'), $this->sharer([$recruitment], 'Ola Mazur'), JobSharePairStatus::Declined);
        Sanctum::actingAs($employer);

        $response = $this->getJson("/api/v1/employer/offers/{$offer->id}/job-share-pairs")->assertOk()->assertJsonCount(2, 'data');

        $labels = collect($response->json('data'))->pluck('status_label', 'id');
        $this->assertSame('Zatrudniona', $labels[$hired->id]);
        $this->assertSame('Odrzucona przez jedną z kandydatek', $labels[$declined->id]);
    }
}
