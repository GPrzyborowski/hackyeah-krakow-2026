<?php

namespace App\Enums;

enum CandidateDecisionType: string
{
    case Skipped = 'skipped';
    case Saved = 'saved';
    case Invited = 'invited';
}
