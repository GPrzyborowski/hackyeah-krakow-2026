<?php

namespace App\Services\Ai;

use Illuminate\Support\Str;

/**
 * Tiny Polish-aware text normaliser used for keyword retrieval (no external stemmer).
 */
final class PolishText
{
    /**
     * @var list<string>
     */
    private const array STOP_WORDS = [
        'ale', 'bez', 'by', 'byc', 'bym', 'czy', 'dla', 'do', 'gdy', 'go', 'ich', 'ile', 'jak', 'jaki', 'jaka', 'jakie',
        'jest', 'jestem', 'jesli', 'juz', 'kiedy', 'ktora', 'ktore', 'ktory', 'lub', 'mam', 'mi', 'mnie', 'moge', 'moj',
        'moja', 'na', 'nad', 'nie', 'od', 'oraz', 'po', 'pod', 'przy', 'sie', 'sa', 'tak', 'to', 'tego', 'tej', 'ten',
        'ty', 'tym', 'w', 'we', 'z', 'za', 'ze', 'co', 'czego', 'musze', 'mozna', 'moze', 'albo', 'jako', 'o', 'a', 'i',
    ];

    /**
     * Lowercase and strip Polish diacritics.
     */
    public static function normalize(string $text): string
    {
        return Str::lower(Str::ascii(Str::lower($text)));
    }

    /**
     * Crude stems of the meaningful words, so that "ciąży" matches "ciąża" and "urlopu" matches "urlop".
     *
     * @return list<string>
     */
    public static function stems(string $text): array
    {
        $words = preg_split('/[^a-z0-9]+/', self::normalize($text), flags: PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_map(
            fn (string $word): string => self::stem($word),
            array_filter($words, fn (string $word): bool => mb_strlen($word) >= 3 && ! in_array($word, self::STOP_WORDS, true)),
        )));
    }

    private static function stem(string $word): string
    {
        $length = mb_strlen($word);

        return mb_substr($word, 0, min(6, max(4, $length - 2)));
    }
}
