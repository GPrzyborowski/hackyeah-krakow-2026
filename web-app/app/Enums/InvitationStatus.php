<?php

namespace App\Enums;

enum InvitationStatus: string
{
    case Pending = 'pending';

    /**
     * A job-sharing pair member accepted, but her partner has not answered yet; nothing is revealed to the company.
     */
    case AwaitingPartner = 'awaiting_partner';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';

    /**
     * Invitations the company is still waiting on; they are withdrawn when the offer closes or the pair falls apart.
     *
     * @var list<self>
     */
    public const array UNANSWERED = [self::Pending, self::AwaitingPartner];
}
