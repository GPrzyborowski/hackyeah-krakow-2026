<?php

namespace App\Enums;

enum UserRole: string
{
    case Candidate = 'candidate';
    case Employer = 'employer';
    case Admin = 'admin';

    /**
     * Who a role-restricted feature is meant for, in the genitive plural ("dostępna tylko dla kont kandydatek").
     */
    public function audienceLabel(): string
    {
        return match ($this) {
            self::Candidate => 'kandydatek',
            self::Employer => 'pracodawców',
            self::Admin => 'administratorów',
        };
    }
}
