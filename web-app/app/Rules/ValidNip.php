<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Polish tax id (NIP): 10 digits (dashes/spaces allowed) with a valid mod-11 checksum.
 */
class ValidNip implements ValidationRule
{
    private const array WEIGHTS = [6, 5, 7, 2, 3, 4, 5, 6, 7];

    public static function normalize(string $nip): string
    {
        return preg_replace('/[\s-]/', '', $nip) ?? '';
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = is_string($value) ? self::normalize($value) : '';

        if (preg_match('/^\d{10}$/', $digits) !== 1) {
            $fail('NIP musi składać się z 10 cyfr.');

            return;
        }

        $checksum = 0;

        foreach (self::WEIGHTS as $index => $weight) {
            $checksum += (int) $digits[$index] * $weight;
        }

        if ($checksum % 11 !== (int) $digits[9]) {
            $fail('Podany NIP jest nieprawidłowy.');
        }
    }
}
