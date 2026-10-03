<?php

namespace Tests\Feature\JobSharing;

use App\Enums\JobSharePairStatus;
use App\Models\Company;
use App\Models\JobSharePair;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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
}
