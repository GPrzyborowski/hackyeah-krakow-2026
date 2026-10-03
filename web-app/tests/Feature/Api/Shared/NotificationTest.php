<?php

namespace Tests\Feature\Api\Shared;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_guests_get_401(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
    }

    public function test_lists_own_notifications_with_target_ids_and_polls_with_since(): void
    {
        $old = $this->notify($this->user, ['kind' => 'new_message', 'title' => 'Stara', 'conversation_id' => 7], now()->subHour());
        $new = $this->notify($this->user, ['kind' => 'pair_invitation_received', 'title' => 'Nowa', 'job_share_pair_id' => 3], now());
        $this->notify(User::factory()->create(), ['kind' => 'new_message', 'title' => 'Cudza'], now());
        Sanctum::actingAs($this->user);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $new->id)
            ->assertJsonPath('data.0.target.job_share_pair_id', 3)
            ->assertJsonPath('data.1.target.conversation_id', 7)
            ->assertJsonPath('meta.unread_count', 2)
            ->assertDontSee('Cudza');

        $this->getJson('/api/v1/notifications?since='.urlencode(now()->subMinutes(5)->toIso8601String()))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $new->id);

        $this->assertNotSame($old->id, $new->id);
    }

    public function test_marks_one_and_all_notifications_read(): void
    {
        $first = $this->notify($this->user, ['kind' => 'new_message', 'title' => 'A'], now());
        $this->notify($this->user, ['kind' => 'new_message', 'title' => 'B'], now());
        Sanctum::actingAs($this->user);

        $this->getJson('/api/v1/notifications/unread-count')->assertOk()->assertJsonPath('data.unread_count', 2);

        $this->postJson("/api/v1/notifications/{$first->id}/read")
            ->assertOk()
            ->assertJsonPath('data.read', true);
        $this->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.unread_count', 1);

        $this->postJson('/api/v1/notifications/read-all')->assertNoContent();
        $this->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.unread_count', 0);
    }

    public function test_cannot_mark_another_users_notification(): void
    {
        $foreign = $this->notify(User::factory()->create(), ['kind' => 'new_message', 'title' => 'Cudza'], now());
        Sanctum::actingAs($this->user);

        $this->postJson("/api/v1/notifications/{$foreign->id}/read")->assertNotFound();
        $this->assertNull($foreign->fresh()?->read_at);
    }

    public function test_real_message_notification_is_listed(): void
    {
        $conversation = Conversation::factory()->create();
        $employer = User::factory()->employer($conversation->invitation->jobOffer->company)->create();
        Message::factory()->for($conversation)->for($employer, 'author')->create();
        Sanctum::actingAs($conversation->invitation->candidateProfile->user);

        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.kind', 'new_message')
            ->assertJsonPath('data.0.target.conversation_id', $conversation->id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function notify(User $user, array $data, \DateTimeInterface $createdAt): DatabaseNotification
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\NewMessage',
            'data' => $data,
            'created_at' => $createdAt,
        ]);
    }
}
