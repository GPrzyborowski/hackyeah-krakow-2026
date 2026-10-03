<?php

namespace App\Services\Ai;

interface MessageModerator
{
    /**
     * Check an employer-authored text for questions about pregnancy or family plans.
     */
    public function check(string $text): ModerationResult;
}
