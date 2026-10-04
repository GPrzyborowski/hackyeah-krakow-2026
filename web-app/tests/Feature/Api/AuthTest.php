<?php

namespace Tests\Feature\Api;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
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

    public function test_login_requires_a_two_factor_code_when_2fa_is_enabled(): void
    {
        $this->enableTwoFactorChallenge();
        $user = $this->userWithTwoFactor();

        $this->postJson('/api/v1/auth/login', $this->credentials($user))
            ->assertUnprocessable()
            ->assertJsonPath('two_factor_required', true)
            ->assertJsonValidationErrors('code');

        $this->postJson('/api/v1/auth/login', [...$this->credentials($user), 'code' => '000000'])
            ->assertUnprocessable()
            ->assertJsonPath('two_factor_required', true);

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_login_with_a_valid_totp_code_returns_a_token(): void
    {
        $this->enableTwoFactorChallenge();
        $user = $this->userWithTwoFactor();
        $code = (new Google2FA)->getCurrentOtp(decrypt($user->two_factor_secret));

        $response = $this->postJson('/api/v1/auth/login', [...$this->credentials($user), 'code' => $code])->assertOk();

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_a_recovery_code_logs_in_once(): void
    {
        $this->enableTwoFactorChallenge();
        $user = $this->userWithTwoFactor();

        $this->postJson('/api/v1/auth/login', [...$this->credentials($user), 'recovery_code' => 'recovery-code-1'])->assertOk();
        $this->postJson('/api/v1/auth/login', [...$this->credentials($user), 'recovery_code' => 'recovery-code-1'])
            ->assertUnprocessable()
            ->assertJsonPath('two_factor_required', true);

        $this->assertNotContains('recovery-code-1', $user->refresh()->recoveryCodes());
    }

    public function test_login_skips_the_two_factor_code_while_the_challenge_is_switched_off(): void
    {
        $user = $this->userWithTwoFactor();

        $response = $this->postJson('/api/v1/auth/login', $this->credentials($user))->assertOk();

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_admins_cannot_log_in_through_the_api(): void
    {
        $admin = User::factory()->admin()->create();

        $this->postJson('/api/v1/auth/login', $this->credentials($admin))
            ->assertForbidden()
            ->assertJsonPath('message', 'Panel administratora jest dostępny tylko w przeglądarce.');

        $this->assertSame(0, $admin->tokens()->count());
    }

    public function test_unknown_email_fails_like_a_wrong_password(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'nobody@example.com', 'password' => 'password', 'device_name' => 'iPhone'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => __('auth.failed')]);
    }

    public function test_login_is_throttled_per_email_and_ip_and_per_email(): void
    {
        $attempt = fn (string $ip) => $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/v1/auth/login', ['email' => 'marta@example.com', 'password' => 'wrong', 'device_name' => 'iPhone']);

        foreach (range(1, 5) as $ignored) {
            $attempt('10.0.0.1')->assertUnprocessable();
        }
        $attempt('10.0.0.1')->assertTooManyRequests();

        foreach (['10.0.0.2', '10.0.0.3', '10.0.0.4'] as $ip) {
            foreach (range(1, 5) as $ignored) {
                $attempt($ip)->assertUnprocessable();
            }
        }
        $attempt('10.0.0.5')->assertTooManyRequests();
    }

    public function test_tokens_expire(): void
    {
        $token = User::factory()->create()->createToken('iPhone')->plainTextToken;

        $this->travel(config('sanctum.expiration') + 1)->minutes();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_logout_all_revokes_every_token_and_device(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('iPhone')->plainTextToken;
        $user->createToken('iPad');

        $this->withToken($token)->postJson('/api/v1/auth/logout-all')->assertNoContent();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_logout_forgets_the_push_token_registered_with_that_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('iPhone')->plainTextToken;
        $otherToken = $user->createToken('iPad')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/devices', ['token' => 'apns:phone', 'platform' => 'ios'])->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken)->postJson('/api/v1/devices', ['token' => 'apns:tablet', 'platform' => 'ios'])->assertNoContent();
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->assertSame(['apns:tablet'], DeviceToken::pluck('token')->all());
    }

    public function test_unverified_user_can_resend_the_verification_link(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/email/verification-notification')->assertAccepted();

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_verified_user_gets_no_new_verification_link(): void
    {
        Notification::fake();
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/auth/email/verification-notification')
            ->assertOk()
            ->assertJsonPath('message', 'Adres e-mail jest już zweryfikowany.');

        Notification::assertNothingSent();
    }

    public function test_authenticated_requests_are_throttled_per_user(): void
    {
        Sanctum::actingAs(User::factory()->create());

        foreach (range(1, 120) as $ignored) {
            $this->getJson('/api/v1/auth/me')->assertOk();
        }

        $this->getJson('/api/v1/auth/me')->assertTooManyRequests();
    }

    private function userWithTwoFactor(): User
    {
        return User::factory()->withTwoFactor()->create([
            'two_factor_secret' => encrypt(app(TwoFactorAuthenticationProvider::class)->generateSecretKey()),
        ]);
    }

    /**
     * @return array{email: string, password: string, device_name: string}
     */
    private function credentials(User $user): array
    {
        return ['email' => $user->email, 'password' => 'password', 'device_name' => 'iPhone'];
    }
}
