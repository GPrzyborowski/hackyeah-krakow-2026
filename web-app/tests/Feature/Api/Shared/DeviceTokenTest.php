<?php

namespace Tests\Feature\Api\Shared;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeviceTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_a_token_is_idempotent_and_moves_it_to_the_current_user(): void
    {
        $previousOwner = User::factory()->create();
        DeviceToken::factory()->for($previousOwner)->create(['token' => 'fcm:abc', 'platform' => 'android']);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/devices', ['token' => 'fcm:abc', 'platform' => 'android'])->assertNoContent();
        $this->postJson('/api/v1/devices', ['token' => 'fcm:abc', 'platform' => 'android'])->assertNoContent();

        $this->assertSame($user->id, DeviceToken::sole()->user_id);
    }

    public function test_token_and_platform_are_validated(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/devices', ['platform' => 'windows'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token', 'platform']);
    }

    public function test_user_removes_only_her_own_token(): void
    {
        $user = User::factory()->create();
        DeviceToken::factory()->for($user)->create(['token' => 'mine']);
        DeviceToken::factory()->create(['token' => 'foreign']);
        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/devices/foreign')->assertNotFound();
        $this->deleteJson('/api/v1/devices/mine')->assertNoContent();

        $this->assertSame(['foreign'], DeviceToken::pluck('token')->all());
    }

    public function test_guests_get_401(): void
    {
        $this->postJson('/api/v1/devices', ['token' => 'x', 'platform' => 'ios'])->assertUnauthorized();
    }
}
