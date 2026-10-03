<?php

namespace Tests\Unit;

use App\Services\Ai\KeywordMessageModerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class KeywordMessageModeratorTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function forbiddenQuestions(): array
    {
        return [
            'pregnancy with diacritics' => ['Czy jest Pani w ciąży?'],
            'pregnancy without diacritics' => ['Czy jest Pani w ciazy?'],
            'offspring plans' => ['Czy planuje Pani potomstwo w najbliższym roku?'],
            'marital status' => ['Czy jest Pani mężatką?'],
            'children' => ['Czy ma Pani dzieci?'],
            'english' => ['Are you pregnant?'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function allowedTexts(): array
    {
        return [
            'availability question' => ['Od kiedy może Pani zacząć?'],
            'word containing a forbidden stem' => ['Duże obciążenie pracą w okresie rozliczeń.'],
            'childcare benefit' => ['Oferujemy dofinansowanie żłobka i elastyczne godziny.'],
        ];
    }

    #[DataProvider('forbiddenQuestions')]
    public function test_family_questions_are_blocked(string $text)
    {
        $this->assertFalse((new KeywordMessageModerator)->check($text)->allowed);
    }

    #[DataProvider('allowedTexts')]
    public function test_neutral_texts_are_allowed(string $text)
    {
        $this->assertTrue((new KeywordMessageModerator)->check($text)->allowed);
    }
}
