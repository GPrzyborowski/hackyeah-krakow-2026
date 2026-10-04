<?php

namespace App\Enums;

enum ContractType: string
{
    case EmploymentContract = 'employment';
    case Mandate = 'mandate';

    public function label(): string
    {
        return match ($this) {
            self::EmploymentContract => 'Umowa o pracę',
            self::Mandate => 'Umowa zlecenie',
        };
    }
}
