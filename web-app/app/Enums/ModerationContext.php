<?php

namespace App\Enums;

/**
 * Where a text blocked by the message moderator was written.
 */
enum ModerationContext: string
{
    case Invitation = 'invitation';
    case DirectMessage = 'direct_message';
    case ChatMessage = 'chat_message';
    case Offer = 'offer';
    case CompanyDescription = 'company_description';
    case CandidateSummary = 'candidate_summary';

    public function label(): string
    {
        return match ($this) {
            self::Invitation => 'Zaproszenie',
            self::DirectMessage => 'Pytanie do kandydatki',
            self::ChatMessage => 'Wiadomość na czacie',
            self::Offer => 'Ogłoszenie',
            self::CompanyDescription => 'Opis firmy',
            self::CandidateSummary => 'Opis kandydatki',
        };
    }

    /**
     * Texts written by employers; only these may keep an excerpt in the log (candidate texts never do).
     */
    public function isEmployerAuthored(): bool
    {
        return $this !== self::CandidateSummary;
    }
}
