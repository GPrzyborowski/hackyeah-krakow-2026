<?php

namespace Tests\Feature\Notifications;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->candidate = Invitation::factory()->create()->candidateProfile->user;
        Invitation::factory()->create(['candidate_profile_id' => $this->candidate->candidateProfile->id]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    }

    public function test_bell_summary_is_shared_with_unread_count_and_latest_notifications(): void
    {
        $this->candidate->notifications()->first()->markAsRead();

        $this->actingAs($this->candidate)
            ->get(route('notifications.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('notifications/Index')
                ->where('notifications.unread_count', 1)
                ->has('notifications.latest', 2)
                ->has('items.data', 2));
    }

    public function test_opening_a_notification_marks_it_read_and_redirects_to_its_target(): void
    {
        $notification = $this->candidate->unreadNotifications()->first();

        $this->actingAs($this->candidate)
            ->post(route('notifications.read', $notification->id))
            ->assertRedirect(route('candidate.invitations.index'));

        $this->assertNotNull($notification->refresh()->read_at);
        $this->assertSame(1, $this->candidate->unreadNotifications()->count());
    }

    public function test_users_cannot_open_someone_elses_notification(): void
    {
        $notification = $this->candidate->notifications()->first();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->post(route('notifications.read', $notification->id))
            ->assertNotFound();

        $this->assertNull($notification->refresh()->read_at);
    }

    public function test_mark_all_read_only_touches_own_notifications(): void
    {
        $otherCandidate = Invitation::factory()->create()->candidateProfile->user;

        $this->actingAs($this->candidate)
            ->from(route('notifications.index'))
            ->post(route('notifications.read-all'))
            ->assertRedirect(route('notifications.index'));

        $this->assertSame(0, $this->candidate->unreadNotifications()->count());
        $this->assertSame(1, $otherCandidate->unreadNotifications()->count());
        $this->assertSame(1, DatabaseNotification::query()->whereNull('read_at')->count());
    }
}
