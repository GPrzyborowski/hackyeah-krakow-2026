<?php

namespace App\Notifications\Concerns;

use App\Models\User;
use App\Notifications\Channels\FcmChannel;

/**
 * Adds the FCM push channel for users who allow push and have a registered device, and builds the push
 * from the notification's `toArray()` (title, body, kind, url and target ids).
 */
trait SendsPush
{
    /**
     * @return array<string, mixed>
     */
    abstract public function toArray(object $notifiable): array;

    /**
     * @param  list<string>  $channels
     * @return list<string>
     */
    protected function withPush(array $channels, object $notifiable): array
    {
        if ($notifiable instanceof User && $notifiable->push_enabled && $notifiable->deviceTokens()->exists()) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    /**
     * Polish title/body shown by the OS plus string-only data the app uses to open the right screen.
     *
     * @return array{title: string, body: string|null, data: array<string, string>}
     */
    public function toPush(object $notifiable): array
    {
        $payload = $this->toArray($notifiable);

        $targetIds = array_filter(
            $payload,
            fn (mixed $value, string $key): bool => str_ends_with($key, '_id') && $value !== null,
            ARRAY_FILTER_USE_BOTH,
        );

        $body = $payload['body'] ?? null;

        return [
            'title' => (string) ($payload['title'] ?? config('app.name')),
            'body' => is_string($body) && $body !== '' ? $body : null,
            'data' => array_map(fn (mixed $value): string => (string) $value, [
                'kind' => (string) ($payload['kind'] ?? ''),
                'notification_id' => (string) ($this->id ?? ''),
                ...$targetIds,
                'url' => (string) ($payload['url'] ?? ''),
            ]),
        ];
    }
}
