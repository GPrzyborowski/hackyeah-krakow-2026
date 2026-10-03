<?php

namespace App\Services\Ai;

final readonly class ModerationResult
{
    public const string DEFAULT_SUGGESTION = 'Zamiast pytać o sytuację rodzinną, zapytaj o dostępność, np. „Od kiedy możesz zacząć i jaki wymiar godzin Ci odpowiada?”';

    public function __construct(
        public bool $allowed,
        public ?string $reason = null,
        public ?string $suggestion = null,
    ) {}

    public static function allow(): self
    {
        return new self(allowed: true);
    }

    public static function block(string $reason, ?string $suggestion = null): self
    {
        return new self(allowed: false, reason: $reason, suggestion: $suggestion ?? self::DEFAULT_SUGGESTION);
    }
}
