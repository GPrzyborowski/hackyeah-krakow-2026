<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Fortify\CreateNewUser;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Token authentication for the mobile app.
 */
class AuthController extends Controller
{
    /**
     * Register a candidate or employer (same rules as the web form) and return a token.
     */
    public function register(Request $request, CreateNewUser $createNewUser): JsonResponse
    {
        $request->validate(['device_name' => ['required', 'string', 'max:255']]);

        /** @var array<string, string> $input */
        $input = $request->only(['name', 'email', 'password', 'password_confirmation', 'role', 'company_name', 'company_nip']);
        $user = $createNewUser->create($input);
        $user->sendEmailVerificationNotification();

        return $this->tokenResponse($user, $request->string('device_name')->toString(), Response::HTTP_CREATED);
    }

    /**
     * Exchange credentials for a token. While the 2FA feature is enabled, users with confirmed 2FA must also send `code` (TOTP) or `recovery_code`;
     * admins are refused (the admin panel is browser-only).
     */
    public function login(LoginRequest $request, TwoFactorAuthenticationProvider $twoFactorProvider): JsonResponse
    {
        $user = User::firstWhere('email', $request->validated('email'));

        // Hash even for unknown e-mails so the response time does not reveal which accounts exist.
        $passwordMatches = Hash::check($request->validated('password'), $user->password ?? $this->dummyPasswordHash());

        if ($user === null || ! $passwordMatches) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        abort_if($user->role === UserRole::Admin, Response::HTTP_FORBIDDEN, 'Panel administratora jest dostępny tylko w przeglądarce.');

        if (Features::enabled(Features::twoFactorAuthentication()) && $user->hasEnabledTwoFactorAuthentication() && ! $this->passesTwoFactorChallenge($user, $request, $twoFactorProvider)) {
            $message = $request->filled('code') || $request->filled('recovery_code')
                ? 'Podany kod uwierzytelniania dwuskładnikowego jest nieprawidłowy.'
                : 'Podaj kod z aplikacji uwierzytelniającej lub kod odzyskiwania.';

            return response()->json([
                'message' => $message,
                'errors' => ['code' => [$message]],
                'two_factor_required' => true,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->tokenResponse($user, $request->validated('device_name'));
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * Revoke the token used for this request.
     */
    public function logout(Request $request): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->noContent();
    }

    /**
     * Revoke every token of the user (all devices); their push tokens are removed with them.
     */
    public function logoutAll(Request $request): Response
    {
        $request->user()->tokens()->delete();

        return response()->noContent();
    }

    /**
     * Resend the e-mail verification link. The link opens in the browser (web route), not in the app.
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Adres e-mail jest już zweryfikowany.']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Wysłaliśmy nowy link weryfikacyjny na Twój adres e-mail.'], Response::HTTP_ACCEPTED);
    }

    /**
     * Valid TOTP code, or an unused recovery code (which is then replaced, i.e. single use).
     */
    private function passesTwoFactorChallenge(User $user, LoginRequest $request, TwoFactorAuthenticationProvider $twoFactorProvider): bool
    {
        $code = $request->validated('code');

        if (is_string($code) && $code !== '') {
            return $twoFactorProvider->verify(Fortify::currentEncrypter()->decrypt((string) $user->two_factor_secret), $code);
        }

        $recoveryCode = $request->validated('recovery_code');

        if (! is_string($recoveryCode) || $recoveryCode === '') {
            return false;
        }

        $matchingCode = collect($user->recoveryCodes())->first(fn (string $storedCode): bool => hash_equals($storedCode, $recoveryCode));

        if ($matchingCode === null) {
            return false;
        }

        $user->replaceRecoveryCode($matchingCode);

        return true;
    }

    private function dummyPasswordHash(): string
    {
        return once(fn (): string => Hash::make('mumjobs-timing-safe-dummy-password'));
    }

    private function tokenResponse(User $user, string $deviceName, int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'token' => $user->createToken($deviceName)->plainTextToken,
            'user' => new UserResource($user->fresh()),
        ], $status);
    }
}
