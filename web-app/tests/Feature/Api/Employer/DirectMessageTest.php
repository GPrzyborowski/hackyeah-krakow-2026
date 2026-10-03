<?php

namespace Tests\Feature\Api\Employer;

use App\Enums\InvitationKind;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Employer\InteractsWithEmployerFixtures;
use Tests\TestCase;

class DirectMessageTest extends TestCase
{
    use InteractsWithEmployerFixtures, RefreshDatabase;

    private const string QUESTION = 'Dzień dobry, czy interesuje Panią praca hybrydowa w Krakowie na 3/5 etatu?';

    public function test_employer_sends_a_direct_question_and_the_candidate_stays_anonymous(): void
    {
        $employer = $this->employer();
        $skill = $this->skill('Księgowość');
        $offer = $this->publishedOffer($employer->company, [$skill]);
        $candidate = $this->candidate([$skill], ['allow_direct_messages' => true]);
        Sanctum::actingAs($employer);

        $response = $this->postJson("/api/v1/employer/offers/{$offer->id}/candidates/{$candidate->id}/direct-message", ['message' => self::QUESTION])
            ->assertCreated()
            ->assertJsonPath('data.kind', 'direct_message')
            ->assertJsonPath('data.kind_label', 'Pytanie od firmy')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.candidate', ['id' => $candidate->id, 'anonymous_name' => 'Marta K.']);

        $this->assertStringNotContainsString($candidate->user->email, (string) $response->getContent());
        $this->assertSame(InvitationKind::DirectMessage, Invitation::sole()->kind);
    }

    public function test_direct_question_is_rejected_when_the_candidate_does_not_allow_it(): void
    {
        $employer = $this->employer();
        $skill = $this->skill('Księgowość');
        $offer = $this->publishedOffer($employer->company, [$skill]);
        $candidate = $this->candidate([$skill], ['allow_direct_messages' => false]);
        Sanctum::actingAs($employer);

        $this->postJson("/api/v1/employer/offers/{$offer->id}/candidates/{$candidate->id}/direct-message", ['message' => self::QUESTION])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');

        $this->assertSame(0, Invitation::count());
    }

    public function test_direct_question_about_pregnancy_is_blocked_with_a_suggestion(): void
    {
        $employer = $this->employer();
        $skill = $this->skill('Księgowość');
        $offer = $this->publishedOffer($employer->company, [$skill]);
        $candidate = $this->candidate([$skill], ['allow_direct_messages' => true]);
        Sanctum::actingAs($employer);

        $this->postJson("/api/v1/employer/offers/{$offer->id}/candidates/{$candidate->id}/direct-message", ['message' => 'Czy jest Pani w ciąży?'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['message', 'message_suggestion']);

        $this->assertSame(0, Invitation::count());
    }

    public function test_guest_and_candidate_cannot_send_direct_questions(): void
    {
        $skill = $this->skill('Księgowość');
        $offer = $this->publishedOffer($this->employer()->company, [$skill]);
        $candidate = $this->candidate([$skill], ['allow_direct_messages' => true]);
        $url = "/api/v1/employer/offers/{$offer->id}/candidates/{$candidate->id}/direct-message";

        $this->postJson($url, ['message' => self::QUESTION])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson($url, ['message' => self::QUESTION])->assertForbidden();

        $this->assertSame(0, Invitation::count());
    }

    public function test_candidate_card_exposes_only_whether_direct_messages_are_accepted(): void
    {
        $employer = $this->employer();
        $skill = $this->skill('Księgowość');
        $offer = $this->publishedOffer($employer->company, [$skill]);
        $candidate = $this->candidate([$skill], ['allow_direct_messages' => true]);
        Sanctum::actingAs($employer);

        $response = $this->getJson("/api/v1/employer/offers/{$offer->id}/candidates/next")
            ->assertOk()
            ->assertJsonPath('data.candidate.id', $candidate->id)
            ->assertJsonPath('data.candidate.accepts_direct_messages', true)
            ->assertJsonMissingPath('data.candidate.allow_direct_messages');

        $this->assertStringNotContainsString('Kowalska', (string) $response->getContent());
        $this->assertStringNotContainsString($candidate->user->email, (string) $response->getContent());
    }
}
