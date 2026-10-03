<?php

namespace App\Services\Push;

/**
 * Google service account used to call the Firebase Cloud Messaging HTTP v1 API.
 * Read from `services.fcm.credentials` (path to the JSON key file or the JSON itself); `services.fcm.project_id` overrides the key's project.
 */
final readonly class FcmServiceAccount
{
    public const string DEFAULT_TOKEN_URI = 'https://oauth2.googleapis.com/token';

    public function __construct(
        public string $projectId,
        public string $clientEmail,
        public string $privateKey,
        public string $tokenUri = self::DEFAULT_TOKEN_URI,
    ) {}

    /**
     * Null when push is not configured (or the key is unusable), which turns the FCM channel into a no-op.
     */
    public static function fromConfig(): ?self
    {
        $credentials = self::decodeCredentials(config('services.fcm.credentials'));

        if ($credentials === null) {
            return null;
        }

        $projectId = config('services.fcm.project_id') ?: ($credentials['project_id'] ?? null);
        $clientEmail = $credentials['client_email'] ?? null;
        $privateKey = $credentials['private_key'] ?? null;

        if (! is_string($projectId) || $projectId === '' || ! is_string($clientEmail) || $clientEmail === '' || ! is_string($privateKey) || $privateKey === '') {
            return null;
        }

        $tokenUri = $credentials['token_uri'] ?? null;

        return new self($projectId, $clientEmail, $privateKey, is_string($tokenUri) && $tokenUri !== '' ? $tokenUri : self::DEFAULT_TOKEN_URI);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function decodeCredentials(mixed $credentials): ?array
    {
        if (! is_string($credentials) || trim($credentials) === '') {
            return null;
        }

        $json = str_starts_with(ltrim($credentials), '{') ? $credentials : (is_file($credentials) ? (string) file_get_contents($credentials) : null);

        if ($json === null) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }
}
