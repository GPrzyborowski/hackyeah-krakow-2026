<?php

namespace App\Services\Ai;

use Illuminate\Support\Str;

/**
 * Offline fallback: blocks texts matching common Polish phrasings of pregnancy and family-plan questions.
 */
class KeywordMessageModerator implements MessageModerator
{
    /**
     * Patterns run against the lowercased ASCII form of the text (diacritics stripped), so "ciąży" and "ciazy" both match.
     *
     * @var list<string>
     */
    private const array FORBIDDEN_PATTERNS = [
        '/\bciaz/u',
        '/\bciezarn/u',
        '/macierzynsk/u',
        '/potomstw/u',
        '/\b(mezat|zonat|narzeczon|slub)/u',
        '/(masz|ma pani|posiada\w*|ile|czy\s+ma\w*)\s+(pani\s+)?(\w+\s+)?dzieci/u',
        '/plan\w*\s+(\w+\s+){0,3}(dzieck|dzieci|rodzin|powieksz|potomstw)/u',
        '/zamierza\w*\s+(\w+\s+){0,3}(dzieck|dzieci|rodzin|potomstw)/u',
        '/stan\w*\s+cywiln/u',
        '/(maz|meza|partner\w*)\s+(pracuje|zarabia)/u',
        '/(opiek\w*|kto\s+zajmie\s+sie)\s+(\w+\s+){0,2}dzie/u',
        '/\b(pregnan|married|maternity)/u',
        '/(have|any|plan\w*\s+(to\s+have\s+)?)\s*(kids|children)/u',
    ];

    public function check(string $text): ModerationResult
    {
        $normalized = Str::lower(Str::ascii($text));

        foreach (self::FORBIDDEN_PATTERNS as $pattern) {
            if (preg_match($pattern, $normalized) === 1) {
                return ModerationResult::block('Pytania o ciążę, dzieci, stan cywilny i plany rodzinne są niedozwolone (Kodeks pracy, art. 22¹).');
            }
        }

        return ModerationResult::allow();
    }
}
