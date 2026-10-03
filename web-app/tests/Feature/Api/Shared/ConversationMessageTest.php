<?php

namespace Tests\Feature\Api\Shared;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConversationMessageTest extends TestCase
{
    use RefreshDatabase;

    private Conversation $conversation;

    private User $candidate;

    private User $employer;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.anthropic.key' => null]);

        $this->conversation = Conversation::factory()->create(['last_message_at' => now()->subHour()]);
        $this->candidate = $this->conversation->invitation->candidateProfile->user;
        $this->employer = User::factory()->employer($this->conversation->invitation->jobOffer->company)->create();
    }

    public function test_reading_messages_marks_only_counterpart_messages_as_read(): void
    {
        $fromCandidate = Message::factory()->for($this->conversation)->for($this->candidate, 'author')->create();
        $fromEmployer = Message::factory()->for($this->conversation)->for($this->employer, 'author')->create();
        Sanctum::actingAs($this->employer);

        $this->getJson("/api/v1/conversations/{$this->conversation->id}/messages")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $fromEmployer->id)
            ->assertJsonPath('data.0.is_mine', true)
            ->assertJsonPath('data.1.is_mine', false);

        $this->assertNotNull($fromCandidate->fresh()?->read_at);
        $this->assertNull($fromEmployer->fresh()?->read_at);
    }

    public function test_polling_returns_only_newer_messages(): void
    {
        $old = Message::factory()->for($this->conversation)->for($this->employer, 'author')->create(['created_at' => now()->subMinutes(10)]);
        $new = Message::factory()->for($this->conversation)->for($this->employer, 'author')->create(['created_at' => now()]);
        Sanctum::actingAs($this->candidate);

        $this->getJson("/api/v1/conversations/{$this->conversation->id}/messages?since=".urlencode(now()->subMinutes(5)->toIso8601String()))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $new->id);

        $this->getJson("/api/v1/conversations/{$this->conversation->id}/messages?after_id={$old->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $new->id);

        $this->getJson("/api/v1/conversations/{$this->conversation->id}/messages?since=wczoraj")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('since');
    }

    public function test_candidate_posts_an_unmoderated_message(): void
    {
        Sanctum::actingAs($this->candidate);

        $this->postJson("/api/v1/conversations/{$this->conversation->id}/messages", ['body' => ' Jestem w ciąży, termin mam w marcu. '])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Jestem w ciąży, termin mam w marcu.')
            ->assertJsonPath('data.is_mine', true);

        $this->assertTrue($this->conversation->fresh()?->last_message_at?->isAfter(now()->subMinute()));
    }

    public function test_employer_message_asking_about_pregnancy_is_blocked_with_suggestion(): void
    {
        Sanctum::actingAs($this->employer);

        $this->postJson("/api/v1/conversations/{$this->conversation->id}/messages", ['body' => 'Czy jest Pani obecnie w ciąży?'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['body', 'body_suggestion']);

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_body_is_required(): void
    {
        Sanctum::actingAs($this->candidate);

        $this->postJson("/api/v1/conversations/{$this->conversation->id}/messages", ['body' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');
    }

    public function test_outsiders_cannot_read_or_post(): void
    {
        Message::factory()->for($this->conversation)->for($this->employer, 'author')->create();

        foreach ([User::factory()->employer()->create(), User::factory()->create()] as $outsider) {
            Sanctum::actingAs($outsider);

            $this->getJson("/api/v1/conversations/{$this->conversation->id}/messages")->assertForbidden();
            $this->postJson("/api/v1/conversations/{$this->conversation->id}/messages", ['body' => 'Cześć'])->assertForbidden();
        }

        $this->assertDatabaseCount('messages', 1);
    }
}
