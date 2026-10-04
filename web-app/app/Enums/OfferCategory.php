<?php

namespace App\Enums;

enum OfferCategory: string
{
    case It = 'it';
    case Health = 'health';
    case Hr = 'hr';
    case Finance = 'finance';
    case Marketing = 'marketing';
    case CustomerService = 'customer_service';
    case Administration = 'administration';
    case Sales = 'sales';
    case Education = 'education';
    case Design = 'design';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::It => 'IT i technologie',
            self::Health => 'Medycyna i zdrowie',
            self::Hr => 'HR i rekrutacja',
            self::Finance => 'Finanse i księgowość',
            self::Marketing => 'Marketing i komunikacja',
            self::CustomerService => 'Obsługa klienta',
            self::Administration => 'Administracja i biuro',
            self::Sales => 'Sprzedaż',
            self::Education => 'Edukacja',
            self::Design => 'Projektowanie i kreatywne',
            self::Other => 'Inne',
        };
    }
}
