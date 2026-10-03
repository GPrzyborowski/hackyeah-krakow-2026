<?php

namespace App\Enums;

/**
 * An invitation to talk about an offer, or a lightweight question sent without an invitation
 * (only to candidates who allow direct messages). Both stay anonymous until the candidate answers.
 */
enum InvitationKind: string
{
    case Invitation = 'invitation';
    case DirectMessage = 'direct_message';

    public function label(): string
    {
        return match ($this) {
            self::Invitation => 'Zaproszenie do rozmowy',
            self::DirectMessage => 'Pytanie od firmy',
        };
    }
}
