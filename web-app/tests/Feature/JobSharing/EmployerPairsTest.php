<?php

namespace Tests\Feature\JobSharing;

use App\Enums\InvitationStatus;
use App\Enums\JobSharePairStatus;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\Invitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmployerPairsTest extends TestCase
{
    use InteractsWithJobSharingFixtures, RefreshDatabase;

    public function test_employer_sees_submitted_pairs_anonymously_with_combined_skill_coverage()
    {
        $employer = $this->employer();
        $recruitment = $this->skill('Rekrutacja IT');
        $onboarding = $this->skill('Onboarding');
        $offer = $this->jobShareOffer($employer->company, [$recruitment, $onboarding]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $ewa = $this->sharer([$onboarding], 'Ewa Nowak');
        $pair = $this->scheduledPair($offer, $marta, $ewa, JobSharePairStatus::Submitted);
        $this->pair($offer, $this->sharer([$recruitment], 'Anna Zielińska'), $this->sharer([$recruitment], 'Ola Mazur'));

        $response = $this->actingAs($employer)
            ->get(route('employer.offers.job-share-pairs.index', $offer))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('job-sharing/EmployerPairs')
                ->has('pairs', 1)
                ->where('pairs.0.id', $pair->id)
                ->where('pairs.0.members.0.anonymous_name', 'Marta K.')
                ->where('pairs.0.members.1.anonymous_name', 'Ewa N.')
                ->where('pairs.0.coverage.percent', 100)
                ->where('pairs.0.schedule.1.starts_at', '12:00'));

        $this->assertStringNotContainsString($marta->user->email, $response->getContent());
        $this->assertStringNotContainsString('Kowalska', $response->getContent());
    }

    public function test_employer_cannot_see_or_decide_on_pairs_of_other_companies()
    {
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer(Company::factory()->create(), [$recruitment]);
        $pair = $this->scheduledPair($offer, $this->sharer([$recruitment], 'Marta Kowalska'), $this->sharer([$recruitment], 'Ewa Nowak'), JobSharePairStatus::Submitted);
        $stranger = $this->employer();

        $this->actingAs($stranger)->get(route('employer.offers.job-share-pairs.index', $offer))->assertForbidden();
        $this->actingAs($stranger)
            ->post(route('employer.job-share-pairs.invitation', $pair), ['message' => 'Zapraszamy na rozmowę o stanowisku.'])
            ->assertNotFound();
        $this->actingAs($stranger)->post(route('employer.job-share-pairs.reject', $pair))->assertNotFound();

        $this->assertSame(JobSharePairStatus::Submitted, $pair->fresh()?->status);
    }

    public function test_inviting_a_pair_creates_one_invitation_per_member()
    {
        $employer = $this->employer();
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer($employer->company, [$recruitment]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $ewa = $this->sharer([$recruitment], 'Ewa Nowak');
        $pair = $this->scheduledPair($offer, $marta, $ewa, JobSharePairStatus::Submitted);

        $this->actingAs($employer)
            ->post(route('employer.job-share-pairs.invitation', $pair), [
                'message' => 'Dzień dobry, zapraszamy Was na rozmowę o stanowisku w modelu job sharing. Widełki 8500–11000 zł.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('employer.offers.job-share-pairs.index', $offer));

        $this->assertSame(JobSharePairStatus::Invited, $pair->fresh()?->status);
        $invitations = Invitation::query()->orderBy('candidate_profile_id')->get();
        $this->assertCount(2, $invitations);
        $this->assertEqualsCanonicalizing([$marta->id, $ewa->id], $invitations->pluck('candidate_profile_id')->all());
        $this->assertTrue($invitations->every(fn (Invitation $invitation): bool => $invitation->job_share_pair_id === $pair->id
            && $invitation->status === InvitationStatus::Pending
            && $invitation->sent_by_user_id === $employer->id));

        $this->actingAs($employer)
            ->post(route('employer.job-share-pairs.invitation', $pair), ['message' => 'Jeszcze raz zapraszamy.'])
            ->assertForbidden();
    }

    public function test_pair_invitation_message_is_moderated()
    {
        $employer = $this->employer();
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer($employer->company, [$recruitment]);
        $pair = $this->scheduledPair($offer, $this->sharer([$recruitment], 'Marta Kowalska'), $this->sharer([$recruitment], 'Ewa Nowak'), JobSharePairStatus::Submitted);

        $this->actingAs($employer)
            ->post(route('employer.job-share-pairs.invitation', $pair), ['message' => 'Czy planuje Pani dzieci?'])
            ->assertSessionHasErrors(['message', 'message_suggestion']);

        $this->assertSame(0, Invitation::count());
        $this->assertSame(JobSharePairStatus::Submitted, $pair->fresh()?->status);
    }

    public function test_employer_rejects_a_pair()
    {
        $employer = $this->employer();
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer($employer->company, [$recruitment]);
        $pair = $this->scheduledPair($offer, $this->sharer([$recruitment], 'Marta Kowalska'), $this->sharer([$recruitment], 'Ewa Nowak'), JobSharePairStatus::Submitted);

        $this->actingAs($employer)
            ->post(route('employer.job-share-pairs.reject', $pair))
            ->assertRedirect(route('employer.offers.job-share-pairs.index', $offer));

        $this->assertSame(JobSharePairStatus::Rejected, $pair->fresh()?->status);
    }

    public function test_pairs_with_a_hidden_or_unpublished_member_are_not_counted_listed_or_rejectable()
    {
        $employer = $this->employer();
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer($employer->company, [$recruitment]);
        $visiblePair = $this->scheduledPair($offer, $this->sharer([$recruitment], 'Marta Kowalska'), $this->sharer([$recruitment], 'Ewa Nowak'), JobSharePairStatus::Submitted);
        $hiddenPair = $this->scheduledPair($offer, $this->sharer([$recruitment], 'Anna Zielińska'), $this->sharer([$recruitment], 'Ola Mazur', attributes: ['hidden_from_company_id' => $employer->company_id]), JobSharePairStatus::Submitted);
        $this->scheduledPair($offer, $this->sharer([$recruitment], 'Iza Wójcik'), $this->sharer([$recruitment], 'Kasia Lis', attributes: ['published_at' => null]), JobSharePairStatus::Submitted);

        $this->actingAs($employer)
            ->get(route('employer.offers.index'))
            ->assertInertia(fn (Assert $page) => $page->where('offers.0.submitted_pairs_count', 1));
        $this->actingAs($employer)
            ->get(route('employer.candidates.index', ['offer' => $offer->id]))
            ->assertInertia(fn (Assert $page) => $page->where('currentOffer.submitted_pairs_count', 1));
        $this->actingAs($employer)
            ->get(route('employer.offers.job-share-pairs.index', $offer))
            ->assertInertia(fn (Assert $page) => $page->has('pairs', 1)->where('pairs.0.id', $visiblePair->id));
        $this->actingAs($employer)->post(route('employer.job-share-pairs.reject', $hiddenPair))->assertNotFound();

        $this->assertSame(JobSharePairStatus::Submitted, $hiddenPair->fresh()?->status);
    }

    public function test_candidate_accepts_her_pair_invitation_and_gets_a_conversation()
    {
        $employer = $this->employer();
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer($employer->company, [$recruitment]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $pair = $this->scheduledPair($offer, $marta, $this->sharer([$recruitment], 'Ewa Nowak'), JobSharePairStatus::Invited);
        $invitation = Invitation::factory()->for($offer)->for($marta)->create(['job_share_pair_id' => $pair->id]);

        $this->actingAs($marta->user)
            ->get(route('candidate.invitations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invitations.0.job_share_pair.id', $pair->id)
                ->where('invitations.0.job_share_pair.partner_name', 'Ewa N.'));

        $this->actingAs($marta->user)->post(route('candidate.invitations.accept', $invitation))->assertRedirect();

        $this->assertSame(InvitationStatus::Accepted, $invitation->fresh()?->status);
        $this->assertSame($invitation->id, Conversation::query()->whereNull('job_share_pair_id')->sole()->invitation_id);
    }
}
