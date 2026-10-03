<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * Raised when a Claude API call fails or returns an unusable answer.
 */
class ClaudeException extends RuntimeException {}
