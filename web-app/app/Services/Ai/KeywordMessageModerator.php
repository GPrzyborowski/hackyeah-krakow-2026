<?php

namespace App\Services\Ai;

/**
 * Offline fallback: blocks texts matching common Polish phrasings of pregnancy and family-plan questions.
 */
class KeywordMessageModerator implements MessageModerator
{
    /**
     * @var list<string>
     */
    private const array FORBIDDEN_PATTERNS = [
        '/ci[ąa]ż/iu',
        '/macierzy[ńn]sk/iu',
        '/(masz|ma pani|posiada|ile)\s+(pani\s+)?dzieci/iu',
        '/plan\w*\s+(\w+\s+){0,3}(dzieck|dzieci|rodzin|powi[ęe]ksz)/iu',
        '/zamierza\w*\s+(\w+\s+){0,3}(dzieck|dzieci|rodzin)/iu',
        '/stan\w*\s+cywiln/iu',
        '/(m[ąa][żz]a?|partnera?)\s+(pracuje|zarabia)/iu',
        '/(opiek\w*|kto\s+zajmie\s+si[ęe])\s+(\w+\s+){0,2}dzie/iu',
    ];

    public function check(string $text): ModerationResult
    {
        foreach (self::FORBIDDEN_PATTERNS as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return ModerationResult::block('Pytania o ciążę, dzieci i plany rodzinne są niedozwolone (Kodeks pracy, art. 22¹).');
            }
        }

        return ModerationResult::allow();
    }
}
