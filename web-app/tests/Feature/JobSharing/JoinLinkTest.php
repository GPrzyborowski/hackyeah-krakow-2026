<?php

namespace Tests\Feature\JobSharing;

use App\Enums\JobSharePairStatus;
use App\Enums\OfferStatus;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\JobOffer;
use App\Models\JobSharePair;
use App\Models\JobSharePairInvitation;
use App\Models\User;
use App\Notifications\PairInvitationAccepted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JoinLinkTest extends TestCase
{
    use InteractsWithJobSharingFixtures, RefreshDatabase;

    public function test_candidate_creates_a_join_link_and_gets_the_same_link_again(): void
    {
        $offer = $this->jobShareOffer(Company::factory()->create(), []);
        $marta = $this->sharer([], 'Marta Kowalska');

        $this->actingAs($marta->user)
            ->from(route('candidate.offers.show', $offer))
            ->post(route('job-sharing.join-links.store', $offer))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('candidate.offers.show', $offer));
        $this->actingAs($marta->user)
            ->post(route('job-sharing.join-links.store', $offer))
            ->assertSessionHasNoErrors();

        $pair = JobSharePair::sole();
        $link = JobSharePairInvitation::sole();
        $this->assertSame(JobSharePairStatus::Forming, $pair->status);
        $this->assertTrue($pair->hasAcceptedMember($marta));
        $this->assertSame(1, $pair->members()->count());
        $this->assertSame($pair->id, $link->job_share_pair_id);
        $this->assertSame($marta->id, $link->invited_by_candidate_profile_id);
        $this->assertSame(64, strlen($link->token));
        $this->assertTrue($link->expires_at->isSameDay(now()->addDays(7)));

        $this->actingAs($marta->user)
            ->get(route('candidate.offers.show', $offer))
            ->assertInertia(fn (Assert $page) => $page
                ->where('jobSharing.pair.is_waiting_for_partner', true)
                ->where('jobSharing.can_create_join_link', true)
                ->where('jobSharing.join_link.url', route('job-sharing.join.show', $link->token)));

        $this->actingAs($marta->user)
            ->get(route('job-sharing.pairs.show', $pair))
            ->assertInertia(fn (Assert $page) => $page
                ->where('pair.is_waiting_for_partner', true)
                ->where('joinLink.url', route('job-sharing.join.show', $link->token)));
    }

    public function test_an_expired_link_is_replaced_by_a_new_one_for_the_same_pair(): void
    {
        $offer = $this->jobShareOffer(Company::factory()->create(), []);
        $marta = $this->sharer([], 'Marta Kowalska');
        $expired = $this->joinLink($offer, $marta, ['expires_at' => now()->subDay()]);

        $this->actingAs($marta->user)
            ->post(route('job-sharing.join-links.store', $offer))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, JobSharePair::count());
        $this->assertSame(2, $expired->pair->joinLinks()->count());
        $this->assertSame(1, $expired->pair->joinLinks()->usable()->count());
    }

    public function test_creating_a_join_link_requires_a_published_profile(): void
    {
        $offer = $this->jobShareOffer(Company::factory()->create(), []);
        $marta = $this->sharer([], 'Marta Kowalska', attributes: ['published_at' => null]);

        $this->actingAs($marta->user)
            ->post(route('job-sharing.join-links.store', $offer))
            ->assertSessionHasErrors(['join_link' => 'Najpierw opublikuj swój profil, aby zaprosić kogoś do pary.']);

        $this->assertSame(0, JobSharePair::count());
        $this->assertSame(0, JobSharePairInvitation::count());
    }

    public function test_candidate_with_a_pair_for_the_offer_cannot_create_a_join_link(): void
    {
        $offer = $this->jobShareOffer(Company::factory()->create(), []);
        $marta = $this->sharer([], 'Marta Kowalska');
        $this->pair($offer, $marta, $this->sharer([], 'Ewa Nowak'));

        $this->actingAs($marta->user)
            ->post(route('job-sharing.join-links.store', $offer))
            ->assertSessionHasErrors(['join_link' => 'Masz już parę do tej oferty.']);

        $this->assertSame(0, JobSharePairInvitation::count());
    }

    public function test_join_links_exist_only_for_published_job_sharing_offers(): void
    {
        $company = Company::factory()->create();
        $regular = JobOffer::factory()->published()->for($company)->create();
        $draft = JobOffer::factory()->jobShare()->for($company)->create();
        $marta = $this->sharer([], 'Marta Kowalska');

        $this->actingAs($marta->user)->post(route('job-sharing.join-links.store', $regular))->assertNotFound();
        $this->actingAs($marta->user)->post(route('job-sharing.join-links.store', $draft))->assertNotFound();

        $this->assertSame(0, JobSharePairInvitation::count());
    }

    public function test_friend_joins_the_pair_through_the_link_without_matching_skills(): void
    {
        Notification::fake();
        $offer = $this->jobShareOffer(Company::factory()->create(), [$this->skill('Rekrutacja IT')]);
        $marta = $this->sharer([], 'Marta Kowalska');
        $ewa = $this->candidate([], ['open_to_job_sharing' => false], 'Ewa Nowak');
        $link = $this->joinLink($offer, $marta);

        $this->actingAs($ewa->user)
            ->get(route('job-sharing.join.show', $link->token))
            ->assertInertia(fn (Assert $page) => $page
                ->component('job-sharing/Join')
                ->where('state', 'join')
                ->where('problem', null)
                ->where('preview.offer.title', $offer->title)
                ->where('preview.inviter.display_name', 'Marta K.'))
            ->assertDontSee('Kowalska');

        $this->actingAs($ewa->user)
            ->post(route('job-sharing.join.store', $link->token))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('job-sharing.pairs.show', $link->pair));

        $pair = $link->pair->fresh();
        $this->assertSame(JobSharePairStatus::Formed, $pair?->status);
        $this->assertTrue($pair->hasAcceptedMember($ewa));
        $this->assertTrue($ewa->fresh()?->open_to_job_sharing);
        $link->refresh();
        $this->assertSame($ewa->id, $link->accepted_by_candidate_profile_id);
        $this->assertNotNull($link->accepted_at);
        Notification::assertSentTo($marta->user, PairInvitationAccepted::class, fn (PairInvitationAccepted $notification): bool => $notification->partner->is($ewa));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function unusableLinks(): array
    {
        return [
            'expired' => ['expired', 'Ten link do pary wygasł. Poproś koleżankę o nowy.'],
            'used' => ['used', 'Ktoś już dołączył do pary z tego linku.'],
            'own link' => ['own link', 'To Twój link. Wyślij go koleżance, z którą chcesz aplikować w parze.'],
            'pair cancelled' => ['pair cancelled', 'Ta para została rozwiązana albo ma już komplet, więc link nie działa.'],
            'pair full' => ['pair full', 'Ta para ma już drugą osobę.'],
            'offer closed' => ['offer closed', 'Ta oferta nie przyjmuje już zgłoszeń par.'],
            'already paired' => ['already paired', 'Masz już parę do tej oferty. Żeby dołączyć do tej, najpierw rozwiąż tamtą.'],
        ];
    }

    #[DataProvider('unusableLinks')]
    public function test_unusable_link_shows_why_and_cannot_be_used(string $case, string $message): void
    {
        Notification::fake();
        $offer = $this->jobShareOffer(Company::factory()->create(), []);
        $marta = $this->sharer([], 'Marta Kowalska');
        $ewa = $this->sharer([], 'Ewa Nowak');
        $link = $this->joinLink($offer, $marta);
        $visitor = $case === 'own link' ? $marta : $ewa;

        match ($case) {
            'expired' => $link->update(['expires_at' => now()->subMinute()]),
            'used' => $link->update(['accepted_at' => now(), 'accepted_by_candidate_profile_id' => $this->sharer([], 'Anna Zielińska')->id]),
            'pair cancelled' => $link->pair->update(['status' => JobSharePairStatus::Cancelled]),
            'pair full' => $link->pair->members()->attach($this->sharer([], 'Anna Zielińska')->id, ['is_initiator' => false]),
            'offer closed' => $offer->update(['status' => OfferStatus::Closed]),
            'already paired' => $this->pair($offer, $ewa, $this->sharer([], 'Anna Zielińska')),
            default => null,
        };

        $this->actingAs($visitor->user)
            ->get(route('job-sharing.join.show', $link->token))
            ->assertInertia(fn (Assert $page) => $page
                ->where('state', 'invalid')
                ->where('problem', $message));

        $this->actingAs($visitor->user)
            ->post(route('job-sharing.join.store', $link->token))
            ->assertSessionHasErrors(['join_link' => $message]);

        $this->assertFalse($link->pair->hasAcceptedMember($ewa));
        Notification::assertNothingSent();
    }

    public function test_unknown_token_shows_an_invalid_link(): void
    {
        $this->get(route('job-sharing.join.show', str_repeat('x', 64)))
            ->assertInertia(fn (Assert $page) => $page
                ->where('state', 'invalid')
                ->where('problem', 'Ten link do pary jest nieprawidłowy. Poproś koleżankę o nowy.')
                ->where('preview', null));
    }

    public function test_employers_see_that_only_candidates_can_join(): void
    {
        $offer = $this->jobShareOffer(Company::factory()->create(), []);
        $link = $this->joinLink($offer, $this->sharer([], 'Marta Kowalska'));
        $employer = $this->employer();

        $this->actingAs($employer)
            ->get(route('job-sharing.join.show', $link->token))
            ->assertInertia(fn (Assert $page) => $page
                ->where('state', 'invalid')
                ->where('problem', 'Do pary mogą dołączyć tylko kandydatki. Zaloguj się na konto kandydatki.'));

        $this->actingAs($employer)->post(route('job-sharing.join.store', $link->token))->assertForbidden();
    }

    public function test_guest_is_asked_to_sign_in_and_returns_to_the_link_after_login(): void
    {
        $offer = $this->jobShareOffer(Company::factory()->create(), []);
        $link = $this->joinLink($offer, $this->sharer([], 'Marta Kowalska'));
        $ewa = $this->sharer([], 'Ewa Nowak');
        $joinUrl = route('job-sharing.join.show', $link->token);

        $this->get($joinUrl)
            ->assertInertia(fn (Assert $page) => $page
                ->where('state', 'guest')
                ->where('preview.inviter.display_name', 'Marta K.'))
            ->assertSessionHas('url.intended', $joinUrl);

        $this->post(route('login.store'), ['email' => $ewa->user->email, 'password' => 'password'])
            ->assertRedirect($joinUrl);
    }

    public function test_guest_returns_to_the_link_after_registering_and_joins_as_a_new_candidate(): void
    {
        Notification::fake();
        $offer = $this->jobShareOffer(Company::factory()->create(), []);
        $link = $this->joinLink($offer, $this->sharer([], 'Marta Kowalska'));
        $joinUrl = route('job-sharing.join.show', $link->token);
        $this->get($joinUrl);

        $this->post(route('register.store'), [
            'name' => 'Ewa Nowak',
            'email' => 'ewa@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'candidate',
        ])->assertRedirect($joinUrl);

        $this->post(route('job-sharing.join.store', $link->token))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('job-sharing.pairs.show', $link->pair));

        $ewa = User::firstWhere('email', 'ewa@example.com')->candidateProfile;
        $this->assertInstanceOf(CandidateProfile::class, $ewa);
        $this->assertTrue($link->pair->hasAcceptedMember($ewa));
        $this->assertSame(JobSharePairStatus::Formed, $link->pair->fresh()?->status);
    }

    public function test_inviting_from_the_partner_list_reuses_the_waiting_pair_and_closes_the_link(): void
    {
        $recruitment = $this->skill('Rekrutacja IT');
        $offer = $this->jobShareOffer(Company::factory()->create(), [$recruitment]);
        $marta = $this->sharer([$recruitment], 'Marta Kowalska');
        $ewa = $this->sharer([$recruitment], 'Ewa Nowak');
        $link = $this->joinLink($offer, $marta);

        $this->actingAs($marta->user)
            ->get(route('job-sharing.partners.index', $offer))
            ->assertInertia(fn (Assert $page) => $page
                ->where('activePairId', null)
                ->where('waitingPairId', $link->job_share_pair_id)
                ->has('partners', 1));

        $this->actingAs($marta->user)
            ->post(route('job-sharing.pairs.store', $offer), ['partner_id' => $ewa->id])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('job-sharing.pairs.show', $link->pair));

        $this->assertSame(1, JobSharePair::count());
        $this->assertTrue($link->pair->hasMember($ewa));
        $this->actingAs($this->sharer([], 'Anna Zielińska')->user)
            ->get(route('job-sharing.join.show', $link->token))
            ->assertInertia(fn (Assert $page) => $page->where('problem', 'Ta para ma już drugą osobę.'));
    }

    /**
     * A forming pair with only the inviter in it and her link.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function joinLink(JobOffer $offer, CandidateProfile $inviter, array $attributes = []): JobSharePairInvitation
    {
        $pair = JobSharePair::factory()->for($offer)->create(['status' => JobSharePairStatus::Forming]);
        $pair->members()->attach($inviter->id, ['is_initiator' => true, 'accepted_at' => now()]);

        return JobSharePairInvitation::factory()->for($pair, 'pair')->create([
            'invited_by_candidate_profile_id' => $inviter->id,
            ...$attributes,
        ]);
    }
}
