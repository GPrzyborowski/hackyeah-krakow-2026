<?php

namespace App\Services\Push;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OAuth2 access token for FCM via the JWT bearer grant (RFC 7523): a JWT signed with the service account's
 * private key (RS256) is exchanged at Google's token endpoint. Tokens live an hour; they are cached for 55 minutes.
 */
class FcmAccessTokenProvider
{
    public const string SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const int CACHE_SECONDS = 55 * 60;

    private const int JWT_LIFETIME_SECONDS = 3600;

    /**
     * Null when Google refuses the assertion or cannot be reached (logged as a warning).
     */
    public function token(FcmServiceAccount $account): ?string
    {
        $cacheKey = $this->cacheKey($account);
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $token = $this->fetch($account);

        if ($token !== null) {
            Cache::put($cacheKey, $token, self::CACHE_SECONDS);
        }

        return $token;
    }

    /**
     * Drop the cached token, e.g. after FCM answered 401.
     */
    public function forget(FcmServiceAccount $account): void
    {
        Cache::forget($this->cacheKey($account));
    }

    public function assertion(FcmServiceAccount $account): ?string
    {
        $issuedAt = now()->getTimestamp();

        $signingInput = $this->base64UrlEncode((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']))
            .'.'.$this->base64UrlEncode((string) json_encode([
                'iss' => $account->clientEmail,
                'scope' => self::SCOPE,
                'aud' => $account->tokenUri,
                'iat' => $issuedAt,
                'exp' => $issuedAt + self::JWT_LIFETIME_SECONDS,
            ]));

        $privateKey = openssl_pkey_get_private($account->privateKey);

        if ($privateKey === false || ! openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            Log::warning('FCM service account private key could not sign the token request.');

            return null;
        }

        return $signingInput.'.'.$this->base64UrlEncode($signature);
    }

    private function fetch(FcmServiceAccount $account): ?string
    {
        $assertion = $this->assertion($account);

        if ($assertion === null) {
            return null;
        }

        try {
            $response = Http::asForm()
                ->timeout((int) config('services.fcm.timeout', 10))
                ->post($account->tokenUri, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('FCM access token request failed.', ['error' => $exception->getMessage()]);

            return null;
        }

        $token = $response->json('access_token');

        if ($response->failed() || ! is_string($token) || $token === '') {
            Log::warning('FCM access token request was refused.', ['status' => $response->status(), 'error' => $response->json('error')]);

            return null;
        }

        return $token;
    }

    private function cacheKey(FcmServiceAccount $account): string
    {
        return 'fcm:access-token:'.sha1($account->clientEmail);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
