<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_turns_push_notifications_off_and_on(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/auth/me')->assertJsonPath('data.push_enabled', true);

        $this->patchJson('/api/v1/auth/me/preferences', ['push_enabled' => false])
            ->assertOk()
            ->assertJsonPath('data.push_enabled', false);
        $this->assertFalse($user->fresh()->push_enabled);

        $this->patchJson('/api/v1/auth/me/preferences', ['push_enabled' => true])->assertJsonPath('data.push_enabled', true);
        $this->assertTrue($user->fresh()->push_enabled);
    }

    public function test_push_enabled_is_required_and_boolean(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson('/api/v1/auth/me/preferences', [])->assertJsonValidationErrors('push_enabled');
        $this->patchJson('/api/v1/auth/me/preferences', ['push_enabled' => 'maybe'])->assertJsonValidationErrors('push_enabled');
    }

    public function test_guests_get_401(): void
    {
        $this->patchJson('/api/v1/auth/me/preferences', ['push_enabled' => false])->assertUnauthorized();
    }
}
