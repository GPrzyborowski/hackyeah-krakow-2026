<?php

namespace App\Enums;

/**
 * Where the candidate is in her parenthood journey. Private: only ever shown to the candidate herself
 * (and as anonymous aggregates to admins) – never to employers or other candidates.
 */
enum CandidateStage: string
{
    case Pregnant = 'pregnant';
    case AfterLeave = 'after_leave';

    public function label(): string
    {
        return match ($this) {
            self::Pregnant => 'W ciąży',
            self::AfterLeave => 'Po urlopie macierzyńskim',
        };
    }

    /**
     * Supporting line under the greeting on the candidate home screen.
     */
    public function homeMessage(): string
    {
        return match ($this) {
            self::Pregnant => 'Spokojnie zaplanuj powrót jeszcze przed porodem – pracodawcy widzą tylko datę, od kiedy możesz zacząć.',
            self::AfterLeave => 'Wracasz do pracy na swoich warunkach – szukamy firm, które rozumieją rodzicielstwo.',
        };
    }

    /**
     * Blog categories recommended on the candidate home screen, most relevant first.
     *
     * @return list<ArticleCategory>
     */
    public function recommendedArticleCategories(): array
    {
        return match ($this) {
            self::Pregnant => [ArticleCategory::Pregnancy, ArticleCategory::Rights, ArticleCategory::CvAndInterviews],
            self::AfterLeave => [ArticleCategory::Return, ArticleCategory::Postpartum, ArticleCategory::Leave, ArticleCategory::Rights],
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $stage): array => ['value' => $stage->value, 'label' => $stage->label()], self::cases());
    }
}
