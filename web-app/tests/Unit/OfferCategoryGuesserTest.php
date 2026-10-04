<?php

namespace Tests\Unit;

use App\Enums\OfferCategory;
use App\Services\Offers\OfferCategoryGuesser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OfferCategoryGuesserTest extends TestCase
{
    /**
     * @return array<string, array{string, OfferCategory}>
     */
    public static function titles(): array
    {
        return [
            'developer' => ['Senior PHP Developer', OfferCategory::It],
            'data analyst' => ['Analityczka danych – raportowanie', OfferCategory::It],
            'it project coordinator' => ['Koordynatorka projektów IT', OfferCategory::It],
            'nurse' => ['Pielęgniarka środowiskowa', OfferCategory::Health],
            'clinic registration' => ['Rejestracja w przychodni', OfferCategory::Health],
            'it recruiter' => ['Specjalistka ds. rekrutacji IT', OfferCategory::Hr],
            'payroll and hr' => ['Specjalistka ds. kadr i płac', OfferCategory::Hr],
            'accountant' => ['Księgowa ds. rozrachunków', OfferCategory::Finance],
            'marketing' => ['Specjalistka ds. marketingu', OfferCategory::Marketing],
            'customer service' => ['Konsultantka obsługi klienta', OfferCategory::CustomerService],
            'office assistant' => ['Asystentka biura zarządu', OfferCategory::Administration],
            'sales' => ['Przedstawicielka handlowa', OfferCategory::Sales],
            'trainings' => ['Specjalistka ds. szkoleń', OfferCategory::Education],
            'ux' => ['Projektantka UX', OfferCategory::Design],
            'no keyword' => ['Koordynatorka projektów', OfferCategory::Other],
            'hr inside a word is not hr' => ['Chrome extension tester', OfferCategory::It],
        ];
    }

    #[DataProvider('titles')]
    public function test_title_maps_to_the_expected_category(string $title, OfferCategory $expected): void
    {
        $this->assertSame($expected, (new OfferCategoryGuesser)->fromTitle($title));
    }
}
