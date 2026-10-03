<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Shapes database notifications for the bell dropdown and the notifications page.
 */
class NotificationPresenter
{
    public const int BELL_LIMIT = 5;

    /**
     * @return array{id: string, kind: string|null, title: string, body: string|null, url: string|null, read: bool, created_at: string|null, created_at_diff: string|null}
     */
    public static function present(DatabaseNotification $notification): array
    {
        /** @var array<string, mixed> $data */
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'kind' => $data['kind'] ?? null,
            'title' => (string) ($data['title'] ?? ''),
            'body' => $data['body'] ?? null,
            'url' => $data['url'] ?? null,
            'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at?->toIso8601String(),
            'created_at_diff' => $notification->created_at?->diffForHumans(),
        ];
    }

    /**
     * Unread count and the latest few notifications for the bell in the app shell.
     *
     * @return array{unread_count: int, latest: array<int, array<string, mixed>>}
     */
    public static function summary(User $user): array
    {
        return [
            'unread_count' => $user->unreadNotifications()->count(),
            'latest' => $user->notifications()
                ->limit(self::BELL_LIMIT)
                ->get()
                ->map(fn (DatabaseNotification $notification): array => self::present($notification))
                ->values()
                ->all(),
        ];
    }
}
