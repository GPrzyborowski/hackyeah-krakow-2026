<?php

namespace App\Notifications\Channels;

use App\Models\DeviceToken;
use App\Models\User;
use App\Services\Push\FcmAccessTokenProvider;
use App\Services\Push\FcmServiceAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends push notifications to every registered device of a user through the Firebase Cloud Messaging HTTP v1 API
 * (Android directly, iOS through APNs configured in Firebase). The notification must provide `toPush()`
 * (see {@see \App\Notifications\Concerns\SendsPush}). Without FCM configuration the channel does nothing.
 */
class FcmChannel
{
    public const string ENDPOINT = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';

    /**
     * FCM error codes meaning the device token will never work again.
     */
    private const array STALE_TOKEN_ERRORS = ['UNREGISTERED', 'INVALID_ARGUMENT'];

    private static bool $missingConfigLogged = false;

    public function __construct(private FcmAccessTokenProvider $accessTokens) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! $notifiable->push_enabled || ! method_exists($notification, 'toPush')) {
            return;
        }

        $account = FcmServiceAccount::fromConfig();

        if ($account === null) {
            $this->logMissingConfigOnce();

            return;
        }

        /** @var list<string> $tokens */
        $tokens = $notifiable->deviceTokens()->pluck('token')->all();

        if ($tokens === []) {
            return;
        }

        $accessToken = $this->accessTokens->token($account);

        if ($accessToken === null) {
            return;
        }

        /** @var array{title: string, body: string|null, data: array<string, string>} $push */
        $push = $notification->toPush($notifiable);

        foreach ($tokens as $token) {
            $response = $this->sendToDevice($account, $accessToken, $token, $push);

            if ($response?->status() === 401) {
                $this->accessTokens->forget($account);

                return;
            }
        }
    }

    /**
     * The HTTP v1 `message` for one device; every `data` value is a string, as FCM requires.
     *
     * @param  array{title: string, body: string|null, data: array<string, string>}  $push
     * @return array<string, mixed>
     */
    public static function message(string $token, array $push): array
    {
        return [
            'token' => $token,
            'notification' => array_filter(['title' => $push['title'], 'body' => $push['body']], fn (?string $value): bool => $value !== null && $value !== ''),
            'data' => array_map(strval(...), $push['data']),
            'android' => ['priority' => 'high'],
            'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
        ];
    }

    /**
     * @param  array{title: string, body: string|null, data: array<string, string>}  $push
     */
    private function sendToDevice(FcmServiceAccount $account, string $accessToken, string $token, array $push): ?Response
    {
        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout((int) config('services.fcm.timeout', 10))
                ->post(sprintf(self::ENDPOINT, $account->projectId), ['message' => self::message($token, $push)]);
        } catch (ConnectionException $exception) {
            Log::warning('FCM push could not be sent.', ['error' => $exception->getMessage()]);

            return null;
        }

        if ($response->successful()) {
            return $response;
        }

        if ($this->isStaleToken($response)) {
            DeviceToken::query()->where('token', $token)->delete();

            return $response;
        }

        Log::warning('FCM push was rejected.', ['status' => $response->status(), 'error' => $response->json('error.message')]);

        return $response;
    }

    private function isStaleToken(Response $response): bool
    {
        if (! in_array($response->status(), [400, 404], true)) {
            return false;
        }

        $errorCodes = collect($response->json('error.details', []))
            ->pluck('errorCode')
            ->push($response->json('error.status'))
            ->filter(fn (mixed $code): bool => is_string($code));

        return $errorCodes->intersect(self::STALE_TOKEN_ERRORS)->isNotEmpty();
    }

    private function logMissingConfigOnce(): void
    {
        if (self::$missingConfigLogged) {
            return;
        }

        self::$missingConfigLogged = true;
        Log::debug('FCM is not configured (FCM_CREDENTIALS / FCM_PROJECT_ID); push notifications are skipped.');
    }
}
