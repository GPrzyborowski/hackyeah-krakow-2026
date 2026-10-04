<?php

namespace Tests\Feature\Flows;

use App\Enums\CandidateDecisionType;
use App\Enums\InvitationStatus;
use App\Enums\OfferStatus;
use App\Models\Company;
use App\Models\Conversation;
use App\Models\Invitation;
use App\Models\JobOffer;
use App\Notifications\InvitationAccepted;
use App\Notifications\InvitationReceived;
use App\Notifications\NewMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Employer\InteractsWithEmployerFixtures;
use Tests\TestCase;

class EmployerToCandidateTest extends TestCase
{
    use InteractsWithEmployerFixtures, InteractsWithFlows, RefreshDatabase;

    public function test_employer_publishes_offer_invites_anonymous_candidate_and_chats_after_acceptance(): void
    {
        $company = Company::factory()->create(['name' => 'Zielone Biuro']);
        $recruiter = $this->employer($company);
        $colleague = $this->employer($company);
        $candidate = $this->candidate(
            [$this->skill('Rekrutacja IT'), $this->skill('Onboarding')],
            ['headline' => 'Specjalistka ds. rekrutacji', 'due_date' => '2027-04-10', 'leave_starts_on' => '2027-03-14'],
            'Marta Zawadzka',
        );
        $candidate->user->update(['email' => 'marta.private@example.test']);
        $candidateUser = $candidate->user;
        Notification::fake();

        $this->actingAs($recruiter)
            ->post(route('employer.offers.store'), [
                'action' => 'publish',
                'title' => 'Specjalistka ds. rekrutacji IT',
                'category' => 'hr',
                'city' => 'Kraków',
                'work_mode' => 'hybrid',
                'start_date' => '2027-09-01',
                'description' => 'Rekrutacje IT w zespole HR, elastyczne godziny pracy.',
                'employment_fraction' => '3/5',
                'salary_min' => 7000,
                'salary_max' => 9000,
                'flexible_hours' => true,
                'fixed_meeting_hours' => false,
                'childcare_subsidy' => true,
                'is_job_share' => false,
                'required_skills' => ['Rekrutacja IT'],
                'nice_to_have_skills' => ['Onboarding'],
            ])
            ->assertRedirect();

        $offer = JobOffer::query()->where('title', 'Specjalistka ds. rekrutacji IT')->sole();
        $this->assertSame(OfferStatus::Published, $offer->status);
        $this->assertTrue($offer->company->is($company));
        $this->assertEqualsCanonicalizing(['Rekrutacja IT', 'Onboarding'], $offer->skills()->pluck('name')->all());

        $swipe = $this->actingAs($recruiter)->get(route('employer.candidates.index', ['offer' => $offer->id]));
        $swipe->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('employer/candidates/Index')
            ->where('currentOffer.id', $offer->id)
            ->where('remainingCount', 1)
            ->where('candidate.id', $candidate->id)
            ->where('candidate.anonymous_name', 'Marta Z.')
            ->where('candidate.headline', 'Specjalistka ds. rekrutacji')
            ->where('candidate.match.score', 100));
        $this->assertCandidateIdentityHidden($this->pagePropsJson($swipe));

        $this->actingAs($recruiter)
            ->post(route('employer.offers.candidates.invitation', [$offer, $candidate]), [
                'message' => 'Dzień dobry, czy jest Pani obecnie w ciąży?',
            ])
            ->assertSessionHasErrors(['message', 'message_suggestion']);
        $this->assertSame(0, Invitation::query()->count());
        Notification::assertNothingSent();

        $this->actingAs($recruiter)
            ->post(route('employer.offers.candidates.invitation', [$offer, $candidate]), [
                'message' => 'Dzień dobry, zapraszamy na rozmowę o roli rekruterki IT. Pracujemy hybrydowo w Krakowie.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('employer.candidates.index', ['offer' => $offer->id]));

        $invitation = Invitation::query()->sole();
        $this->assertSame(InvitationStatus::Pending, $invitation->status);
        $this->assertTrue($invitation->sender->is($recruiter));
        $this->assertDatabaseHas('candidate_decisions', [
            'job_offer_id' => $offer->id,
            'candidate_profile_id' => $candidate->id,
            'decision' => CandidateDecisionType::Invited->value,
        ]);
        Notification::assertSentTo($candidateUser, InvitationReceived::class);
        Notification::assertNotSentTo([$recruiter, $colleague], InvitationReceived::class);

        $this->actingAs($recruiter)
            ->get(route('employer.candidates.index', ['offer' => $offer->id]))
            ->assertInertia(fn (Assert $page) => $page->where('candidate', null)->where('remainingCount', 0));
        $pendingList = $this->actingAs($recruiter)->get(route('employer.invitations.index'));
        $pendingList->assertInertia(fn (Assert $page) => $page
            ->where('invitations.0.status', 'pending')
            ->where('invitations.0.candidate.anonymous_name', 'Marta Z.')
            ->where('invitations.0.conversation_url', null));
        $this->assertCandidateIdentityHidden($this->pagePropsJson($pendingList));

        $this->actingAs($candidateUser)
            ->get(route('candidate.invitations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('candidate/Invitations')
                ->has('invitations', 1)
                ->where('invitations.0.id', $invitation->id)
                ->where('invitations.0.status', 'pending')
                ->where('invitations.0.company.name', 'Zielone Biuro')
                ->where('invitations.0.conversation_id', null));
        $this->actingAs($candidateUser)
            ->get(route('candidate.home'))
            ->assertInertia(fn (Assert $page) => $page->where('invitations.pending_count', 1));

        $this->actingAs($recruiter)->post(route('candidate.invitations.accept', $invitation))->assertForbidden();

        $this->actingAs($candidateUser)->post(route('candidate.invitations.accept', $invitation))->assertRedirect();

        $conversation = Conversation::query()->sole();
        $this->assertSame(InvitationStatus::Accepted, $invitation->refresh()->status);
        $this->assertTrue($conversation->invitation->is($invitation));
        Notification::assertSentTo([$recruiter, $colleague], InvitationAccepted::class);
        Notification::assertNotSentTo($candidateUser, InvitationAccepted::class);

        $this->actingAs($recruiter)
            ->get(route('employer.invitations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invitations.0.status', 'accepted')
                ->where('invitations.0.candidate.full_name', 'Marta Zawadzka')
                ->where('invitations.0.candidate.email', 'marta.private@example.test')
                ->where('invitations.0.conversation_url', route('conversations.show', $conversation)));
        $this->actingAs($recruiter)
            ->get(route('conversations.show', $conversation))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('conversations/Show')
                ->where('conversation.counterpart.type', 'candidate')
                ->where('conversation.counterpart.name', 'Marta Zawadzka')
                ->where('conversation.counterpart.email', 'marta.private@example.test'));

        $outsider = $this->employer();
        $this->actingAs($outsider)->get(route('conversations.show', $conversation))->assertForbidden();
        $this->actingAs($outsider)
            ->post(route('conversations.messages.store', $conversation), ['body' => 'Dzień dobry'])
            ->assertForbidden();

        $this->actingAs($recruiter)
            ->post(route('conversations.messages.store', $conversation), ['body' => 'Czy planuje Pani zajść w ciążę w najbliższym roku?'])
            ->assertSessionHasErrors('body');
        $this->assertSame(1, $conversation->messages()->count(), 'Only the invitation message seeded on acceptance.');

        $this->actingAs($recruiter)
            ->post(route('conversations.messages.store', $conversation), ['body' => 'Dziękujemy! Czy pasuje Pani rozmowa we wtorek o 10:00?'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('conversations.show', $conversation));

        Notification::assertSentToTimes($candidateUser, NewMessage::class, 1);
        Notification::assertNotSentTo([$recruiter, $colleague], NewMessage::class);

        $this->actingAs($candidateUser)
            ->post(route('conversations.messages.store', $conversation), ['body' => 'Dzień dobry, wtorek o 10:00 mi pasuje.'])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo([$recruiter, $colleague], NewMessage::class);
        Notification::assertSentToTimes($candidateUser, NewMessage::class, 1);

        $this->actingAs($candidateUser)
            ->get(route('conversations.show', $conversation))
            ->assertInertia(fn (Assert $page) => $page
                ->where('conversation.counterpart.type', 'company')
                ->where('conversation.counterpart.name', 'Zielone Biuro')
                ->has('messages', 3)
                ->where('messages.0.body', 'Dzień dobry, zapraszamy na rozmowę o roli rekruterki IT. Pracujemy hybrydowo w Krakowie.')
                ->where('messages.1.body', 'Dziękujemy! Czy pasuje Pani rozmowa we wtorek o 10:00?')
                ->where('messages.1.is_mine', false)
                ->where('messages.2.body', 'Dzień dobry, wtorek o 10:00 mi pasuje.')
                ->where('messages.2.is_mine', true));
        $this->assertSame(0, $conversation->messages()->where('user_id', $recruiter->id)->whereNull('read_at')->count());
    }

    /**
     * Before acceptance the company may see neither the surname, the e-mail nor the private return calendar.
     */
    private function assertCandidateIdentityHidden(string $propsJson): void
    {
        $this->assertStringContainsString('Marta Z.', $propsJson);
        $this->assertStringNotContainsString('Zawadzka', $propsJson);
        $this->assertStringNotContainsString('marta.private@example.test', $propsJson);
        $this->assertStringNotContainsString('2027-04-10', $propsJson);
        $this->assertStringNotContainsString('2027-03-14', $propsJson);
    }
}
