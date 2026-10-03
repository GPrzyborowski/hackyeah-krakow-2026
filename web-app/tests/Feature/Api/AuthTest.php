<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_registers_and_receives_a_token()
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Marta Kowalska',
            'email' => 'marta@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'candidate',
            'device_name' => 'iPhone',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.role', 'candidate')
            ->assertJsonPath('user.candidate_profile.published', false);
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_registration_validates_like_the_web_form()
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Rekruterka',
            'email' => 'hr@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'employer',
            'company_name' => 'Zielone Biuro',
            'company_nip' => '1234567890',
            'device_name' => 'Pixel',
        ])->assertUnprocessable()->assertJsonValidationErrors('company_nip');
    }

    public function test_login_returns_a_token_that_authenticates_requests()
    {
        $user = User::factory()->create(['email' => 'marta@example.com']);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'marta@example.com',
            'password' => 'password',
            'device_name' => 'iPhone',
        ])->assertOk()->json('token');

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonMissingPath('data.password');
    }

    public function test_login_with_wrong_password_fails()
    {
        User::factory()->create(['email' => 'marta@example.com']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'marta@example.com',
            'password' => 'wrong',
            'device_name' => 'iPhone',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_logout_revokes_the_current_token()
    {
        $user = User::factory()->create();
        $token = $user->createToken('iPhone')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_guests_get_401()
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }
}
