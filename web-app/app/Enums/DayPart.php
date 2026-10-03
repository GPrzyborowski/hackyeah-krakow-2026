<?php

namespace App\Enums;

enum DayPart: string
{
    case Morning = 'morning';
    case Afternoon = 'afternoon';
    case Any = 'any';

    public function label(): string
    {
        return match ($this) {
            self::Morning => 'Poranki',
            self::Afternoon => 'Popołudnia',
            self::Any => 'Bez znaczenia',
        };
    }
}
