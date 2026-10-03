<?php

namespace App\Enums;

enum ArticleCategory: string
{
    case Pregnancy = 'pregnancy';
    case Leave = 'leave';
    case Return = 'return';
    case Rights = 'rights';
    case CvAndInterviews = 'cv_and_interviews';

    public function label(): string
    {
        return match ($this) {
            self::Pregnancy => 'W ciąży',
            self::Leave => 'Urlop',
            self::Return => 'Powrót do pracy',
            self::Rights => 'Prawa',
            self::CvAndInterviews => 'CV i rozmowy',
        };
    }
}
