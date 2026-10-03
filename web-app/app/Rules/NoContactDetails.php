<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects text containing an e-mail address or a Polish phone number, e.g. in anonymous profiles, skills and reviews.
 */
class NoContactDetails implements ValidationRule
{
    /**
     * @var list<string>
     */
    private const array PATTERNS = [
        '/[^\s@]+@[^\s@]+\.[a-z]{2,}/i',
        '/[\p{L}0-9._%+-]+@[\p{L}0-9-]+(\.[\p{L}0-9-]+)+/u',
        '/(?<!\d)(?:\+?48[\s-]?)?\d{3}[\s-]?\d{3}[\s-]?\d{3}(?!\d)/',
        '/(?<!\d)\d{2}[\s-]?\d{3}[\s-]?\d{2}[\s-]?\d{2}(?!\d)/',
        '/(?:\+?\d[\s\-().]{0,2}){9,}/',
    ];

    public function __construct(
        private readonly string $message = 'Usuń e-mail i numer telefonu – pracodawca dostanie Twój kontakt dopiero po przyjęciu zaproszenia.',
    ) {}

    public static function containsContactDetails(string $text): bool
    {
        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && self::containsContactDetails($value)) {
            $fail($this->message);
        }
    }
}
