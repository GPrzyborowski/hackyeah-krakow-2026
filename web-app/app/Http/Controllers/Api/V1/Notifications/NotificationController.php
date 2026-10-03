<?php

namespace App\Http\Controllers\Api\V1\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PollRequest;
use App\Http\Resources\Api\V1\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The signed-in user's in-app notifications (same items as the web bell).
 */
class NotificationController extends Controller
{
    private const int PER_PAGE = 20;

    /**
     * Newest first; poll with `since`. `meta.unread_count` saves a separate call.
     */
    public function index(PollRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $since = $request->since();

        $notifications = $user->notifications()
            ->when($since !== null, fn ($query) => $query->where('created_at', '>', $since))
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return NotificationResource::collection($notifications)->additional(['meta' => [
            'unread_count' => $user->unreadNotifications()->count(),
        ]]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['data' => ['unread_count' => $request->user()->unreadNotifications()->count()]]);
    }

    /**
     * Mark one of the user's notifications read (another user's id is 404).
     */
    public function read(Request $request, string $notification): NotificationResource
    {
        /** @var DatabaseNotification $model */
        $model = $request->user()->notifications()->findOrFail($notification);
        $model->markAsRead();

        return new NotificationResource($model);
    }

    public function readAll(Request $request): Response
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->noContent();
    }
}
