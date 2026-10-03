<?php

namespace Tests\Feature\Conversations;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    private Conversation $conversation;

    private User $candidate;

    private User $employer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        config(['services.anthropic.key' => null]);

        $this->conversation = Conversation::factory()->create(['last_message_at' => now()->subHour()]);
        $this->candidate = $this->conversation->invitation->candidateProfile->user;
        $this->employer = User::factory()->employer($this->conversation->invitation->jobOffer->company)->create();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('conversations.show', $this->conversation))->assertRedirect(route('login'));
    }

    public function test_candidate_sees_company_header_and_her_conversations(): void
    {
        Message::factory()->for($this->conversation)->for($this->employer, 'author')->create(['body' => 'Dzień dobry!']);
        Conversation::factory()->create();

        $this->actingAs($this->candidate)
            ->get(route('conversations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('conversations/Index')
                ->has('conversations', 1)
                ->where('conversations.0.counterpart_name', $this->conversation->invitation->jobOffer->company->name)
                ->where('conversations.0.last_message', 'Dzień dobry!')
                ->where('conversations.0.has_unread', true));

        $this->actingAs($this->candidate)
            ->get(route('conversations.show', $this->conversation))
            ->assertInertia(fn (Assert $page) => $page
                ->component('conversations/Show')
                ->where('conversation.counterpart.type', 'company')
                ->where('conversation.counterpart.name', $this->conversation->invitation->jobOffer->company->name)
                ->where('conversation.offer_title', $this->conversation->invitation->jobOffer->title)
                ->has('messages', 1));
    }

    public function test_employer_sees_revealed_candidate_contact_data(): void
    {
        $this->actingAs($this->employer)
            ->get(route('conversations.show', $this->conversation))
            ->assertInertia(fn (Assert $page) => $page
                ->where('conversation.counterpart.type', 'candidate')
                ->where('conversation.counterpart.name', $this->candidate->name)
                ->where('conversation.counterpart.email', $this->candidate->email));
    }

    public function test_opening_the_thread_marks_only_counterpart_messages_as_read(): void
    {
        $fromCandidate = Message::factory()->for($this->conversation)->for($this->candidate, 'author')->create();
        $fromEmployer = Message::factory()->for($this->conversation)->for($this->employer, 'author')->create();

        $this->actingAs($this->employer)->get(route('conversations.show', $this->conversation))->assertOk();

        $this->assertNotNull($fromCandidate->fresh()->read_at);
        $this->assertNull($fromEmployer->fresh()->read_at);
    }

    public function test_outsiders_cannot_view_or_post(): void
    {
        $otherEmployer = User::factory()->employer()->create();
        $otherCandidate = User::factory()->create();

        foreach ([$otherEmployer, $otherCandidate] as $outsider) {
            $this->actingAs($outsider)->get(route('conversations.show', $this->conversation))->assertForbidden();
            $this->actingAs($outsider)
                ->post(route('conversations.messages.store', $this->conversation), ['body' => 'Cześć'])
                ->assertForbidden();

            $this->actingAs($outsider)
                ->get(route('conversations.index'))
                ->assertInertia(fn (Assert $page) => $page->has('conversations', 0));
        }

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_candidate_message_is_stored_without_moderation(): void
    {
        $this->actingAs($this->candidate)
            ->post(route('conversations.messages.store', $this->conversation), [
                'body' => 'Jestem w ciąży, termin porodu mam w marcu.',
            ])
            ->assertRedirect(route('conversations.show', $this->conversation));

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->candidate->id,
            'body' => 'Jestem w ciąży, termin porodu mam w marcu.',
        ]);
        $this->assertTrue($this->conversation->fresh()->last_message_at->isAfter(now()->subMinute()));
    }

    public function test_employer_message_asking_about_pregnancy_is_blocked_with_suggestion(): void
    {
        $this->actingAs($this->employer)
            ->from(route('conversations.show', $this->conversation))
            ->post(route('conversations.messages.store', $this->conversation), [
                'body' => 'Czy jest Pani obecnie w ciąży?',
            ])
            ->assertRedirect(route('conversations.show', $this->conversation))
            ->assertSessionHasErrors(['body', 'body_suggestion']);

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_employer_message_about_availability_is_stored(): void
    {
        $this->actingAs($this->employer)
            ->post(route('conversations.messages.store', $this->conversation), [
                'body' => 'Od kiedy może Pani zacząć i jaki wymiar godzin Pani odpowiada?',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('messages', ['user_id' => $this->employer->id]);
    }
}
