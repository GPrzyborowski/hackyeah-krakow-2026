<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdatePreferencesRequest;
use App\Http\Resources\Api\V1\UserResource;

/**
 * Account-wide preferences of the signed-in user (currently: push notifications on all devices).
 */
class PreferenceController extends Controller
{
    /**
     * Turning push off keeps the registered devices; nothing is sent to them until it is turned on again.
     */
    public function update(UpdatePreferencesRequest $request): UserResource
    {
        $user = $request->user();
        $user->forceFill($request->validated())->save();

        return new UserResource($user);
    }
}
