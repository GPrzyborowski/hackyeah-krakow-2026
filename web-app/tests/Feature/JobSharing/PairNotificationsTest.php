<?php

namespace Tests\Feature\JobSharing;

use App\Enums\JobSharePairStatus;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\JobSharePair;
use App\Notifications\InvitationReceived;
use App\Notifications\PairInvitationAccepted;
use App\Notifications\PairInvitationReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PairNotificationsTest extends TestCase
{
    use InteractsWithJobSharingFixtures, RefreshDatabase;

    public function test_invited_partner_gets_an_anonymous_mail_and_bell_notification(): void
    {
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer(Company::factory()->create(), [$recruitment]);
        $offer->update(['title' => 'Rekruterka IT']);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $ewa = $this->sharer([$recruitment], 'Ewa Nowak');
        Notification::fake();

        $this->actingAs($marta->user)
            ->post(route('job-sharing.pairs.store', $offer), ['partner_id' => $ewa->id])
            ->assertSessionHasNoErrors();

        $pair = JobSharePair::sole();
        Notification::assertSentTo(
            $ewa->user,
            PairInvitationReceived::class,
            function (PairInvitationReceived $notification, array $channels) use ($ewa, $pair): bool {
                $data = $notification->toArray($ewa->user);

                return $channels === ['mail', 'database']
                    && $data['title'] === 'Marta K. zaprasza Cię do pary job-sharing na stanowisko Rekruterka IT'
                    && $data['url'] === route('job-sharing.pairs.show', $pair, absolute: false)
                    && $notification->toMail($ewa->user)->actionUrl === route('job-sharing.pairs.show', $pair);
            },
        );
        Notification::assertNotSentTo($marta->user, PairInvitationReceived::class);
    }

    public function test_initiator_is_notified_when_the_partner_joins(): void
    {
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer(Company::factory()->create(), [$recruitment]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $ewa = $this->sharer([$recruitment], 'Ewa Nowak');
        $pair = $this->pair($offer, $marta, $ewa, JobSharePairStatus::Forming);
        Notification::fake();

        $this->actingAs($ewa->user)->post(route('job-sharing.pairs.accept', $pair))->assertRedirect();

        Notification::assertSentTo(
            $marta->user,
            PairInvitationAccepted::class,
            fn (PairInvitationAccepted $notification, array $channels): bool => $channels === ['database']
                && str_starts_with($notification->toArray($marta->user)['title'], 'Ewa N. przyjęła zaproszenie do pary job-sharing'),
        );
        Notification::assertNotSentTo($ewa->user, PairInvitationAccepted::class);
    }

    public function test_declining_the_pair_does_not_notify_as_accepted(): void
    {
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer(Company::factory()->create(), [$recruitment]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $pair = $this->pair($offer, $marta, $ewa = $this->sharer([$recruitment], 'Ewa Nowak'), JobSharePairStatus::Forming);
        Notification::fake();

        $this->actingAs($ewa->user)->post(route('job-sharing.pairs.decline', $pair));

        Notification::assertNotSentTo($marta->user, PairInvitationAccepted::class);
    }

    public function test_both_members_get_a_pair_worded_invitation_from_the_employer(): void
    {
        $employer = $this->employer();
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer($employer->company, [$recruitment]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $ewa = $this->sharer([$recruitment], 'Ewa Nowak');
        $pair = $this->scheduledPair($offer, $marta, $ewa, JobSharePairStatus::Submitted);
        Notification::fake();

        $this->actingAs($employer)
            ->post(route('employer.job-share-pairs.invitation', $pair), [
                'message' => 'Dzień dobry, zapraszamy Was na rozmowę o stanowisku w modelu job sharing.',
            ])
            ->assertSessionHasNoErrors();

        $expectedTitle = "Firma {$employer->company->name} zaprasza Waszą parę job-sharing do rozmowy o stanowisku {$offer->title}";
        Notification::assertSentTo(
            [$marta->user, $ewa->user],
            InvitationReceived::class,
            fn (InvitationReceived $notification): bool => $notification->toArray($marta->user)['title'] === $expectedTitle,
        );
    }

    public function test_offer_button_reflects_the_viewers_side_of_a_pending_pair(): void
    {
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer(Company::factory()->create(), [$recruitment]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $ewa = $this->sharer([$recruitment], 'Ewa Nowak');
        $pair = $this->pair($offer, $marta, $ewa, JobSharePairStatus::Forming);

        $this->actingAs($marta->user)
            ->get(route('job-sharing.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('offers.0.active_pair_id', $pair->id)
                ->where('offers.0.active_pair_state', 'invite_sent'));

        $this->actingAs($ewa->user)
            ->get(route('job-sharing.index'))
            ->assertInertia(fn (Assert $page) => $page->where('offers.0.active_pair_state', 'invite_received'));

        $pair->update(['status' => JobSharePairStatus::Formed]);
        $pair->members()->updateExistingPivot($ewa->id, ['accepted_at' => now()]);

        $this->actingAs($ewa->user)
            ->get(route('job-sharing.index'))
            ->assertInertia(fn (Assert $page) => $page->where('offers.0.active_pair_state', 'pair'));
    }

    public function test_conversation_from_a_pair_invitation_names_the_partner_anonymously(): void
    {
        $employer = $this->employer();
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer($employer->company, [$recruitment]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $ewa = $this->sharer([$recruitment], 'Ewa Nowak');
        $pair = $this->scheduledPair($offer, $marta, $ewa, JobSharePairStatus::Invited);
        $invitation = Invitation::factory()->for($offer)->create([
            'candidate_profile_id' => $marta->id,
            'job_share_pair_id' => $pair->id,
            'sent_by_user_id' => $employer->id,
        ]);
        $conversation = $invitation->accept();

        $this->actingAs($marta->user)
            ->get(route('conversations.show', $conversation))
            ->assertInertia(fn (Assert $page) => $page->where('conversation.pair_partner_name', 'Ewa N.'));

        $this->actingAs($employer)
            ->get(route('conversations.show', $conversation))
            ->assertInertia(fn (Assert $page) => $page->where('conversation.pair_partner_name', 'Ewa N.'));
    }
}
