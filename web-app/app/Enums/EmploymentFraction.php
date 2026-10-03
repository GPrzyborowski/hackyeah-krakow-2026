<?php

namespace App\Enums;

enum EmploymentFraction: string
{
    case Full = '1';
    case ThreeQuarters = '3/4';
    case ThreeFifths = '3/5';
    case Half = '1/2';

    public function label(): string
    {
        return match ($this) {
            self::Full => 'Pełny etat',
            default => $this->value.' etatu',
        };
    }
}
