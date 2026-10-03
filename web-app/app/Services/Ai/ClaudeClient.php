<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Minimal Claude Messages API client returning schema-constrained JSON (structured outputs).
 */
class ClaudeClient
{
    /**
     * Whether an API key is configured.
     */
    public function isConfigured(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    /**
     * Send one user turn and return the decoded JSON object Claude produced for the given schema.
     *
     * @param  list<array<string, mixed>>  $content  User content blocks (documents first, then text).
     * @param  array<string, mixed>  $schema  JSON schema of the expected answer.
     * @return array<string, mixed>
     *
     * @throws ClaudeException
     */
    public function json(string $system, array $content, array $schema, int $maxTokens = 4000, string $effort = 'low'): array
    {
        if (! $this->isConfigured()) {
            throw new ClaudeException('Anthropic API key is not configured.');
        }

        $payload = [
            'model' => config('services.anthropic.model'),
            'max_tokens' => $maxTokens,
            'system' => $system,
            'messages' => [
                ['role' => 'user', 'content' => $content],
            ],
            'output_config' => [
                'effort' => $effort,
                'format' => ['type' => 'json_schema', 'schema' => $schema],
            ],
        ];

        $headers = [
            'x-api-key' => (string) config('services.anthropic.key'),
            'anthropic-version' => (string) config('services.anthropic.version'),
        ];

        if (config('services.anthropic.refusal_fallback')) {
            $payload['fallbacks'] = 'default';
            $headers['anthropic-beta'] = 'server-side-fallback-2026-07-01';
        }

        try {
            $response = Http::baseUrl(rtrim((string) config('services.anthropic.base_url'), '/'))
                ->withHeaders($headers)
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('services.anthropic.timeout', 60))
                ->post('/v1/messages', $payload)
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            throw new ClaudeException('Claude request failed: '.$exception->getMessage(), previous: $exception);
        }

        $stopReason = $response->json('stop_reason');

        if (in_array($stopReason, ['refusal', 'max_tokens'], true)) {
            throw new ClaudeException("Claude stopped with reason [{$stopReason}].");
        }

        $text = $response->collect('content')
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            throw new ClaudeException('Claude returned a response that is not a JSON object.');
        }

        return $decoded;
    }
}
