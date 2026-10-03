<?php

namespace App\Enums;

/**
 * Lifecycle of a job-sharing pair: forming (partner not yet accepted) -> formed (both in, agreeing the split)
 * -> submitted (sent to the employer) -> invited (employer invited both) | rejected
 * -> hired (both members accepted their invitations) | declined (a member declined hers).
 * Any active pair is cancelled when a member leaves it or the offer is closed.
 */
enum JobSharePairStatus: string
{
    case Forming = 'forming';
    case Formed = 'formed';
    case Submitted = 'submitted';
    case Invited = 'invited';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Hired = 'hired';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Forming => 'Czeka na odpowiedź partnerki',
            self::Formed => 'Ustalacie podział dnia',
            self::Submitted => 'Wysłane do pracodawcy',
            self::Invited => 'Pracodawca zaprosił Waszą parę',
            self::Rejected => 'Pracodawca odrzucił parę',
            self::Cancelled => 'Para rozwiązana',
            self::Hired => 'Zatrudnione',
            self::Declined => 'Odrzucone przez członkinię',
        };
    }
}
