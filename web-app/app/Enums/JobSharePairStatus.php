<?php

namespace App\Enums;

/**
 * Lifecycle of a job-sharing pair: forming (partner not yet accepted) -> formed (both in, agreeing the split)
 * -> submitted (sent to the employer) -> invited (employer invited both) | rejected.
 */
enum JobSharePairStatus: string
{
    case Forming = 'forming';
    case Formed = 'formed';
    case Submitted = 'submitted';
    case Invited = 'invited';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
