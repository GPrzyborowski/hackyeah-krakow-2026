<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Notifications\NotificationPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    private const int PER_PAGE = 20;

    /**
     * The signed-in user's notifications, newest first.
     */
    public function index(Request $request): Response
    {
        $notifications = $request->user()->notifications()->paginate(self::PER_PAGE);

        return Inertia::render('notifications/Index', [
            'items' => $notifications->through(
                fn (DatabaseNotification $notification): array => NotificationPresenter::present($notification),
            ),
        ]);
    }

    /**
     * Mark one notification read and open what it points to.
     */
    public function read(Request $request, string $notification): RedirectResponse
    {
        /** @var DatabaseNotification $model */
        $model = $request->user()->notifications()->findOrFail($notification);
        $model->markAsRead();

        $url = $model->data['url'] ?? null;

        return is_string($url) && str_starts_with($url, '/') && ! str_starts_with($url, '//')
            ? redirect($url)
            : to_route('notifications.index');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
