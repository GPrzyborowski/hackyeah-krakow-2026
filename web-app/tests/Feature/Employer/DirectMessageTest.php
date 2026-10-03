<?php

namespace Tests\Feature\Employer;

use App\Enums\CandidateDecisionType;
use App\Enums\InvitationKind;
use App\Enums\InvitationStatus;
use App\Models\CandidateDecision;
use App\Models\Invitation;
use App\Notifications\InvitationReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DirectMessageTest extends TestCase
{
    use InteractsWithEmployerFixtures, RefreshDatabase;

    private const string QUESTION = 'Dzień dobry, czy interesuje Panią praca hybrydowa w Krakowie na 3/5 etatu?';

    private const string INVITATION = 'Dzień dobry, zapraszamy na rozmowę o stanowisku księgowej w przyszłym tygodniu.';

    public function test_employer_sends_a_direct_question_to_a_candidate_who_allows_it(): void
    {
        Notification::fake();
        $employer = $this->employer();
        $skill = $this->skill('Księgowość');
        $offer = $this->publishedOffer($employer->company, [$skill]);
        $candidate = $this->candidate([$skill], ['allow_direct_messages' => true]);

        $this->actingAs($employer)
            ->post(route('employer.offers.candidates.direct-message', [$offer, $candidate]), ['message' => self::QUESTION])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('employer.candidates.index', ['offer' => $offer->id]));

        $invitation = Invitation::sole();
        $this->assertSame(InvitationKind::DirectMessage, $invitation->kind);
        $this->assertSame(InvitationStatus::Pending, $invitation->status);
        $this->assertSame(self::QUESTION, $invitation->message);
        $this->assertSame(CandidateDecisionType::Invited, CandidateDecision::sole()->decision);
        Notification::assertSentTo(
            $candidate->user,
            InvitationReceived::class,
            fn (InvitationReceived $notification): bool => $notification->toArray($candidate->user)['title']
                === "Firma {$employer->company->name} ma pytanie dotyczące stanowiska {$offer->title}",
        );
    }

    public function test_direct_question_is_refused_when_the_candidate_does_not_allow_it(): void
    {
        $employer = $this->employer();
        $skill = $this->skill('Księgowość');
        $offer = $this->publishedOffer($employer->company, [$skill]);
        $candidate = $this->candidate([$skill], ['allow_direct_messages' => false]);

        $this->actingAs($employer)
            ->post(route('employer.offers.candidates.direct-message', [$offer, $candidate]), ['message' => self::QUESTION])
            ->assertSessionHasErrors('message');

        $this->assertSame(0, Invitation::count());
        $this->assertSame(0, CandidateDecision::count());
    }

    public function test_direct_question_about_family_plans_is_blocked_with_a_suggestion(): void
    {
        $employer = $this->employer();
        $skill = $this->skill('Księgowość');
        $offer = $this->publishedOffer($employer->company, [$skill]);
        $candidate = $this->candidate([$skill], ['allow_direct_messages' => true]);

        $this->actingAs($employer)
            ->post(route('employer.offers.candidates.direct-message', [$offer, $candidate]), ['message' => 'Czy planuje Pani dzieci?'])
            ->assertSessionHasErrors(['message', 'message_suggestion']);

        $this->assertSame(0, Invitation::count());
    }

    public function test_direct_question_is_limited_to_1000_characters(): void
    {
        $employer = $this->employer();
        $skill = $this->skill('Księgowość');
        $offer = $this->publishedOffer($employer->company, [$skill]);
        $candidate = $this->candidate([$skill], ['allow_direct_messages' => true]);

        $this->actingAs($employer)
            ->post(route('employer.offers.candidates.direct-message', [$offer, $candidate]), ['message' => str_repeat('a', 1001)])
            ->assertSessionHasErrors('message');

        $this->assertSame(0, Invitation::count());
    }

    public function test_employer_cannot_send_a_direct_question_for_another_company_offer(): void
    {
        $skill = $this->skill('Księgowość');
        $foreignOffer = $this->publishedOffer($this->employer()->company, [$skill]);
        $candidate = $this->candidate([$skill], ['allow_direct_messages' => true]);

        $this->actingAs($this->employer())
            ->post(route('employer.offers.candidates.direct-message', [$foreignOffer, $candidate]), ['message' => self::QUESTION])
            ->assertForbidden();

        $this->assertSame(0, Invitation::count());
    }

    public function test_swipe_card_tells_whether_the_candidate_accepts_direct_messages_and_stays_anonymous(): void
    {
        $employer = $this->employer();
        $skill = $this->skill('Księgowość');
        $offer = $this->publishedOffer($employer->company, [$skill]);
        $candidate = $this->candidate([$skill], ['allow_direct_messages' => true]);

        $response = $this->actingAs($employer)
            ->get(route('employer.candidates.index', ['offer' => $offer->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('candidate.id', $candidate->id)
                ->where('candidate.accepts_direct_messages', true));

        $response->assertDontSee('Kowalska')->assertDontSee($candidate->user->email);
    }

    public function test_sent_direct_question_is_labelled_in_the_company_invitations(): void
    {
        $employer = $this->employer();
        $offer = $this->publishedOffer($employer->company, [$this->skill('Księgowość')]);
        Invitation::factory()->directMessage()->for($offer)->create();

        $this->actingAs($employer)
            ->get(route('employer.invitations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invitations.0.kind', 'direct_message')
                ->where('invitations.0.kind_label', 'Pytanie od firmy'));
    }

    public function test_unanswered_or_ignored_question_can_be_turned_into_an_invitation(): void
    {
        Notification::fake();
        $employer = $this->employer();
        $skill = $this->skill('Księgowość');
        $offer = $this->publishedOffer($employer->company, [$skill]);

        foreach ([InvitationStatus::Pending, InvitationStatus::Declined] as $status) {
            $candidate = $this->candidate([$skill], ['allow_direct_messages' => true]);
            $question = Invitation::factory()->directMessage()->for($offer)->for($candidate)->create([
                'status' => $status,
                'responded_at' => $status === InvitationStatus::Declined ? now() : null,
            ]);

            $this->actingAs($employer)
                ->post(route('employer.offers.candidates.invitation', [$offer, $candidate]), [
                    'message' => self::INVITATION,
                    'from' => 'invitations',
                ])
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('employer.invitations.index'));

            $question->refresh();
            $this->assertSame(InvitationKind::Invitation, $question->kind, "{$status->value} question");
            $this->assertSame(InvitationStatus::Pending, $question->status);
            $this->assertSame(self::INVITATION, $question->message);
            $this->assertNull($question->responded_at);
            Notification::assertSentTo(
                $candidate->user,
                InvitationReceived::class,
                fn (InvitationReceived $notification): bool => $notification->invitation->is($question) && ! $notification->invitation->isDirectMessage(),
            );
        }

        $this->assertSame(2, Invitation::count());
    }

    public function test_answered_question_or_existing_invitation_blocks_another_invitation(): void
    {
        $employer = $this->employer();
        $skill = $this->skill('Księgowość');
        $offer = $this->publishedOffer($employer->company, [$skill]);
        $answered = $this->candidate([$skill], ['allow_direct_messages' => true]);
        $invited = $this->candidate([$skill], ['allow_direct_messages' => true]);
        Invitation::factory()->directMessage()->for($offer)->for($answered)->create(['status' => InvitationStatus::Accepted, 'responded_at' => now()]);
        Invitation::factory()->for($offer)->for($invited)->create();

        $this->actingAs($employer)
            ->post(route('employer.offers.candidates.invitation', [$offer, $answered]), ['message' => self::INVITATION])
            ->assertSessionHasErrors(['message' => 'Kandydatka odpowiedziała już na Twoje pytanie. Rozmawiajcie dalej na czacie.']);
        $this->actingAs($employer)
            ->post(route('employer.offers.candidates.direct-message', [$offer, $invited]), ['message' => self::QUESTION])
            ->assertSessionHasErrors(['message' => 'Ta kandydatka ma już zaproszenie do tej oferty.']);

        $this->assertSame(1, Invitation::query()->where('kind', InvitationKind::DirectMessage)->count());
        $this->assertSame(InvitationStatus::Accepted, Invitation::query()->where('candidate_profile_id', $answered->id)->sole()->status);
    }
}
