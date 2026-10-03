<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
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

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::firstWhere('email', $request->validated('email'));

        if ($user === null || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
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

    private function tokenResponse(User $user, string $deviceName, int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'token' => $user->createToken($deviceName)->plainTextToken,
            'user' => new UserResource($user->fresh()),
        ], $status);
    }
}
