<?php

namespace App\Enums;

enum WorkMode: string
{
    case Remote = 'remote';
    case Hybrid = 'hybrid';
    case Onsite = 'onsite';

    public function label(): string
    {
        return match ($this) {
            self::Remote => 'Zdalnie',
            self::Hybrid => 'Hybrydowo',
            self::Onsite => 'Stacjonarnie',
        };
    }
}
