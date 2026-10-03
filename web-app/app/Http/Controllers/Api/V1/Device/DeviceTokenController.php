<?php

namespace App\Http\Controllers\Api\V1\Device;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Device\StoreDeviceTokenRequest;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Registers push tokens of the signed-in user's devices (no pushes are sent yet).
 */
class DeviceTokenController extends Controller
{
    /**
     * Idempotent: a token already known (also from a previous user of the device) is moved to the signed-in user
     * and bound to the API token of this request, so logging out (or any token revocation) forgets the device.
     */
    public function store(StoreDeviceTokenRequest $request): Response
    {
        $accessToken = $request->user()?->currentAccessToken();

        DeviceToken::query()->updateOrCreate(
            ['token' => $request->validated('token')],
            [
                'user_id' => $request->user()->id,
                'personal_access_token_id' => $accessToken instanceof PersonalAccessToken && $accessToken->exists ? $accessToken->getKey() : null,
                'platform' => $request->validated('platform'),
                'last_used_at' => now(),
            ],
        );

        return response()->noContent();
    }

    /**
     * Forget a token of the signed-in user (e.g. on logout); unknown or foreign tokens are 404.
     */
    public function destroy(Request $request, string $token): Response
    {
        DeviceToken::query()
            ->whereBelongsTo($request->user())
            ->where('token', $token)
            ->firstOrFail()
            ->delete();

        return response()->noContent();
    }
}
