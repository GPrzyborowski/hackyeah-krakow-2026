<?php

namespace Tests\Feature\JobSharing;

use App\Enums\InvitationStatus;
use App\Enums\JobSharePairStatus;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\JobOffer;
use App\Models\JobSharePair;
use App\Models\User;
use App\Notifications\PairAcceptedForCompany;
use App\Notifications\PairHired;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PairLifecycleTest extends TestCase
{
    use InteractsWithJobSharingFixtures, RefreshDatabase;

    public function test_candidate_invites_a_partner_and_the_partner_accepts()
    {
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer(Company::factory()->create(), [$recruitment]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $ewa = $this->sharer([$recruitment], 'Ewa Nowak');

        $this->actingAs($marta->user)
            ->post(route('job-sharing.pairs.store', $offer), ['partner_id' => $ewa->id])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('job-sharing.pairs.show', JobSharePair::sole()));

        $pair = JobSharePair::sole();
        $this->assertSame(JobSharePairStatus::Forming, $pair->status);
        $this->assertTrue($pair->hasAcceptedMember($marta));
        $this->assertTrue($pair->hasMember($ewa));
        $this->assertFalse($pair->hasAcceptedMember($ewa));

        $this->actingAs($ewa->user)
            ->get(route('job-sharing.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('job-sharing/Index')
                ->has('invitations', 1)
                ->where('invitations.0.partner.display_name', 'Marta K.'));

        $this->actingAs($ewa->user)
            ->post(route('job-sharing.pairs.accept', $pair))
            ->assertRedirect(route('job-sharing.pairs.show', $pair));

        $this->assertSame(JobSharePairStatus::Formed, $pair->fresh()?->status);
        $this->assertTrue($pair->hasAcceptedMember($ewa));
    }

    public function test_partner_declines_the_pair_invitation()
    {
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer(Company::factory()->create(), [$recruitment]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $ewa = $this->sharer([$recruitment], 'Ewa Nowak');
        $pair = $this->pair($offer, $marta, $ewa, JobSharePairStatus::Forming);

        $this->actingAs($marta->user)->post(route('job-sharing.pairs.accept', $pair))->assertForbidden();

        $this->actingAs($ewa->user)
            ->post(route('job-sharing.pairs.decline', $pair))
            ->assertRedirect(route('job-sharing.index'));

        $this->assertSame(JobSharePairStatus::Cancelled, $pair->fresh()?->status);
        $this->actingAs($ewa->user)->post(route('job-sharing.pairs.accept', $pair))->assertForbidden();
    }

    public function test_candidate_cannot_start_a_second_pair_or_invite_an_ineligible_partner()
    {
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer(Company::factory()->create(), [$recruitment]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $notOpen = $this->candidate([$recruitment], name: 'Anna Zielińska');

        $this->actingAs($marta->user)
            ->post(route('job-sharing.pairs.store', $offer), ['partner_id' => $notOpen->id])
            ->assertSessionHasErrors('partner_id');

        $this->pair($offer, $marta, $this->sharer([$recruitment], 'Ewa Nowak'), JobSharePairStatus::Forming);

        $this->actingAs($marta->user)
            ->post(route('job-sharing.pairs.store', $offer), ['partner_id' => $this->sharer([$recruitment], 'Ola Mazur')->id])
            ->assertSessionHasErrors('partner_id');

        $this->assertSame(1, JobSharePair::count());
    }

    public function test_candidate_already_in_a_pair_cannot_be_invited_by_someone_else()
    {
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer(Company::factory()->create(), [$recruitment]);
        $ewa = $this->sharer([$recruitment], 'Ewa Nowak');
        $this->pair($offer, $this->sharer([$recruitment], 'Marta Kowalska'), $ewa);
        $anna = $this->sharer([$recruitment], 'Anna Zielińska');

        $this->actingAs($anna->user)
            ->post(route('job-sharing.pairs.store', $offer), ['partner_id' => $ewa->id])
            ->assertSessionHasErrors('partner_id');
    }

    public function test_only_members_can_open_a_pair_and_only_accepted_members_can_chat()
    {
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer(Company::factory()->create(), [$recruitment]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $ewa = $this->sharer([$recruitment], 'Ewa Nowak');
        $outsider = $this->sharer([$recruitment], 'Anna Zielińska');
        $pair = $this->pair($offer, $marta, $ewa, JobSharePairStatus::Forming);

        $this->actingAs($outsider->user)->get(route('job-sharing.pairs.show', $pair))->assertForbidden();
        $this->actingAs($outsider->user)
            ->post(route('job-sharing.pairs.messages.store', $pair), ['body' => 'Cześć'])
            ->assertForbidden();
        $this->actingAs($ewa->user)
            ->post(route('job-sharing.pairs.messages.store', $pair), ['body' => 'Cześć'])
            ->assertForbidden();

        $this->actingAs($marta->user)
            ->post(route('job-sharing.pairs.messages.store', $pair), ['body' => 'Mogę brać poranki.'])
            ->assertSessionHasNoErrors();

        $this->actingAs($marta->user)
            ->get(route('job-sharing.pairs.show', $pair))
            ->assertInertia(fn (Assert $page) => $page
                ->component('job-sharing/Pair')
                ->has('messages', 1)
                ->where('messages.0.author_name', 'Marta')
                ->where('messages.0.is_mine', true)
                ->where('members.0.display_name', 'Marta K.')
                ->where('members.1.display_name', 'Ewa N.')
                ->where('can.chat', true));
    }

    public function test_ended_pair_keeps_its_chat_readable_but_closed_for_new_messages()
    {
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer(Company::factory()->create(), [$recruitment]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $pair = $this->pair($offer, $marta, $this->sharer([$recruitment], 'Ewa Nowak'), JobSharePairStatus::Cancelled);

        $this->actingAs($marta->user)
            ->post(route('job-sharing.pairs.messages.store', $pair), ['body' => 'Jeszcze jedno'])
            ->assertForbidden();

        $this->actingAs($marta->user)
            ->get(route('job-sharing.pairs.show', $pair))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can.chat', true)
                ->where('can.send_message', false));

        $this->assertDatabaseCount('job_share_messages', 0);
    }

    public function test_pair_moves_to_talks_with_the_company_once_both_members_accept_their_invitations(): void
    {
        Notification::fake();
        [$employer, $offer, $marta, $ewa, $pair] = $this->invitedPair();

        $this->actingAs($marta->user)->post(route('candidate.invitations.accept', $this->invitationOf($pair, $marta)))->assertRedirect();

        $this->assertSame(JobSharePairStatus::Invited, $pair->fresh()?->status);
        Notification::assertNotSentTo($employer, PairAcceptedForCompany::class);

        $this->actingAs($ewa->user)->post(route('candidate.invitations.accept', $this->invitationOf($pair, $ewa)))->assertRedirect();

        $this->assertSame(JobSharePairStatus::Accepted, $pair->fresh()?->status);
        Notification::assertSentToTimes($employer, PairAcceptedForCompany::class, 1);
        Notification::assertNotSentTo([$marta->user, $ewa->user], PairHired::class);

        $this->actingAs($employer)
            ->get(route('employer.offers.job-share-pairs.index', $offer))
            ->assertInertia(fn (Assert $page) => $page
                ->where('pairs.0.id', $pair->id)
                ->where('pairs.0.status', 'accepted'));
    }

    public function test_employer_hires_a_pair_after_both_members_accepted(): void
    {
        Notification::fake();
        [$employer, $offer, $marta, $ewa, $pair] = $this->invitedPair();
        $this->invitationOf($pair, $marta)->accept();
        $this->invitationOf($pair, $ewa)->accept();

        $this->actingAs($employer)
            ->post(route('employer.job-share-pairs.hire', $pair))
            ->assertRedirect(route('employer.offers.job-share-pairs.index', $offer));

        $this->assertSame(JobSharePairStatus::Hired, $pair->fresh()?->status);
        foreach ([$marta->user, $ewa->user] as $member) {
            Notification::assertSentToTimes($member, PairHired::class, 1);
            Notification::assertSentTo($member, PairHired::class, fn (PairHired $notification): bool => $notification->toArray($member)['title'] === "Gratulacje! Wasza para została zatrudniona na stanowisko {$offer->title}");
        }

        $this->actingAs($marta->user)
            ->get(route('candidate.invitations.index'))
            ->assertInertia(fn (Assert $page) => $page->where('invitations.0.job_share_pair.status', 'hired'));
    }

    public function test_pair_cannot_be_hired_before_both_members_accept_or_by_another_company(): void
    {
        Notification::fake();
        [$employer, , $marta, , $pair] = $this->invitedPair();
        $this->invitationOf($pair, $marta)->accept();

        $this->actingAs($employer)->post(route('employer.job-share-pairs.hire', $pair))->assertForbidden();
        $this->actingAs($this->employer())->post(route('employer.job-share-pairs.hire', $pair))->assertNotFound();

        $this->assertSame(JobSharePairStatus::Invited, $pair->fresh()?->status);
        Notification::assertNotSentTo($marta->user, PairHired::class);
    }

    public function test_member_declining_her_invitation_declines_the_pair_and_withdraws_the_partners_invitation(): void
    {
        Notification::fake();
        [, , $marta, $ewa, $pair] = $this->invitedPair();

        $this->actingAs($marta->user)
            ->post(route('candidate.invitations.decline', $this->invitationOf($pair, $marta)))
            ->assertRedirect(route('candidate.invitations.index'));

        $this->assertSame(JobSharePairStatus::Declined, $pair->fresh()?->status);
        $this->assertSame(InvitationStatus::Withdrawn, $this->invitationOf($pair, $ewa)->status);
        $this->actingAs($ewa->user)->post(route('candidate.invitations.accept', $this->invitationOf($pair, $ewa)))->assertForbidden();
        Notification::assertNotSentTo([$marta->user, $ewa->user], PairHired::class);
    }

    public function test_partner_declining_after_the_first_acceptance_reveals_nothing_to_the_company(): void
    {
        Notification::fake();
        [$employer, , $marta, $ewa, $pair] = $this->invitedPair();

        $this->actingAs($marta->user)
            ->post(route('candidate.invitations.accept', $this->invitationOf($pair, $marta)))
            ->assertRedirect(route('candidate.invitations.index'));

        $this->assertSame(InvitationStatus::AwaitingPartner, $this->invitationOf($pair, $marta)->status);

        $this->actingAs($ewa->user)->post(route('candidate.invitations.decline', $this->invitationOf($pair, $ewa)))->assertRedirect();

        $this->assertSame(JobSharePairStatus::Declined, $pair->fresh()?->status);
        $this->assertSame(InvitationStatus::Withdrawn, $this->invitationOf($pair, $marta)->status);
        $this->assertDatabaseCount('conversations', 0);
        Notification::assertNotSentTo($employer, PairAcceptedForCompany::class);

        $this->actingAs($employer)
            ->get(route('employer.invitations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invitations', fn ($rows): bool => collect($rows)->every(fn (array $row): bool => ! isset($row['candidate']['full_name']))));
    }

    public function test_closing_the_offer_cancels_pairs_in_progress_and_keeps_hired_ones(): void
    {
        $employer = $this->employer();
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer($employer->company, [$recruitment]);
        $pairs = collect([
            JobSharePairStatus::Forming,
            JobSharePairStatus::Formed,
            JobSharePairStatus::Submitted,
            JobSharePairStatus::Invited,
            JobSharePairStatus::Hired,
            JobSharePairStatus::Rejected,
        ])->mapWithKeys(fn (JobSharePairStatus $status): array => [
            $status->value => $this->pair($offer, $this->sharer([$recruitment], 'Anna Nowak'), $this->sharer([$recruitment], 'Ola Mazur'), $status),
        ]);
        $invitedMember = $pairs['invited']->members()->firstOrFail();
        $pendingInvitation = Invitation::factory()->for($offer)->for($invitedMember)->create(['job_share_pair_id' => $pairs['invited']->id]);

        $this->actingAs($employer)->post(route('employer.offers.close', $offer))->assertRedirect();

        foreach (['forming', 'formed', 'submitted', 'invited'] as $status) {
            $this->assertSame(JobSharePairStatus::Cancelled, $pairs[$status]->fresh()?->status, "{$status} pair should be cancelled");
        }
        $this->assertSame(JobSharePairStatus::Hired, $pairs['hired']->fresh()?->status);
        $this->assertSame(JobSharePairStatus::Rejected, $pairs['rejected']->fresh()?->status);
        $this->assertSame(InvitationStatus::Withdrawn, $pendingInvitation->fresh()?->status);
    }

    public function test_member_cannot_dissolve_a_pair_once_it_is_invited_or_hired(): void
    {
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer(Company::factory()->create(), [$recruitment]);

        foreach ([JobSharePairStatus::Invited, JobSharePairStatus::Hired] as $status) {
            $marta = $this->sharer([$recruitment], 'Marta Kowalska');
            $pair = $this->scheduledPair($offer, $marta, $this->sharer([$recruitment], 'Ewa Nowak'), $status);

            $this->actingAs($marta->user)->post(route('job-sharing.pairs.cancel', $pair))->assertForbidden();

            Sanctum::actingAs($marta->user);
            $this->postJson("/api/v1/job-sharing/pairs/{$pair->id}/cancel")
                ->assertForbidden()
                ->assertJsonPath('message', 'Tej pary nie można już rozwiązać – została wysłana do pracodawcy lub zakończyła się decyzją.');

            $this->assertSame($status, $pair->fresh()?->status);
        }
    }

    /**
     * A submitted pair invited by the employer through the web app: one pending invitation per member.
     *
     * @return array{0: User, 1: JobOffer, 2: CandidateProfile, 3: CandidateProfile, 4: JobSharePair}
     */
    private function invitedPair(): array
    {
        $employer = $this->employer();
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer($employer->company, [$recruitment]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $ewa = $this->sharer([$recruitment], 'Ewa Nowak');
        $pair = $this->scheduledPair($offer, $marta, $ewa, JobSharePairStatus::Submitted);

        $this->actingAs($employer)
            ->post(route('employer.job-share-pairs.invitation', $pair), [
                'message' => 'Dzień dobry, zapraszamy Was na rozmowę o stanowisku w modelu job sharing.',
            ])
            ->assertSessionHasNoErrors();

        return [$employer, $offer, $marta, $ewa, $pair];
    }

    private function invitationOf(JobSharePair $pair, CandidateProfile $member): Invitation
    {
        return Invitation::query()->where('job_share_pair_id', $pair->id)->where('candidate_profile_id', $member->id)->sole();
    }
}
