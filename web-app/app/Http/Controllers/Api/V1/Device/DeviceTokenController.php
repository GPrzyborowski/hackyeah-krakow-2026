<?php

namespace App\Http\Controllers\Api\V1\Device;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Device\StoreDeviceTokenRequest;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Registers push tokens of the signed-in user's devices (no pushes are sent yet).
 */
class DeviceTokenController extends Controller
{
    /**
     * Idempotent: a token already known (also from a previous user of the device) is moved to the signed-in user.
     */
    public function store(StoreDeviceTokenRequest $request): Response
    {
        DeviceToken::query()->updateOrCreate(
            ['token' => $request->validated('token')],
            [
                'user_id' => $request->user()->id,
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
