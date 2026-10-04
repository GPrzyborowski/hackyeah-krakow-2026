<?php

namespace App\Services\Offers;

use App\Enums\OfferCategory;

/**
 * Guesses an offer's industry from its title with simple Polish keyword rules (used to backfill existing offers).
 */
class OfferCategoryGuesser
{
    /**
     * Case-insensitive regex patterns checked in order; the first matching category wins.
     *
     * @var array<string, string>
     */
    private const array PATTERNS = [
        'health' => '/pielęgniar|lekar|medycz|fizjoterap|rejestracj.*przychodn|farmac|położn/iu',
        'hr' => '/\bhr\b|rekrut|kadr/iu',
        'it' => '/developer|programist|frontend|backend|tester|\bqa\b|devops|\bit\b|analityk danych|analityczka danych|\bdata\b|python|java|oprogramowani/iu',
        'finance' => '/księgow|finans|płac|controlling|audyt|accountant|accounting/iu',
        'marketing' => '/marketing|social media|content|\bpr\b|komunikac|copywrit/iu',
        'customer_service' => '/obsług.*klient|konsultant|customer|support|helpdesk/iu',
        'administration' => '/administrac|asystent|biur|recepcj|office/iu',
        'sales' => '/sprzeda|handlow|account/iu',
        'education' => '/nauczyc|edukac|szkoleń|szkoleni|lektor|korepetyt/iu',
        'design' => '/projektant|grafik|\bux\b|\bui\b|design|architekt/iu',
    ];

    public function fromTitle(string $title): OfferCategory
    {
        foreach (self::PATTERNS as $category => $pattern) {
            if (preg_match($pattern, $title) === 1) {
                return OfferCategory::from($category);
            }
        }

        return OfferCategory::Other;
    }
}
