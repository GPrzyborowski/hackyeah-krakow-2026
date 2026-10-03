<?php

namespace App\Services\Ai;

use App\Enums\ModerationContext;
use App\Models\ModerationEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Writes a moderation log entry when the message moderator blocks a text (the text itself is never saved elsewhere).
 */
class ModerationRecorder
{
    public function __construct(private readonly KeywordMessageModerator $keywordModerator) {}

    /**
     * Log a blocked text; allowed results are ignored. Candidate-authored texts are logged without an excerpt.
     */
    public function recordBlock(ModerationContext $context, ModerationResult $result, string $text, ?User $author, mixed $subject = null): ?ModerationEvent
    {
        if ($result->allowed) {
            return null;
        }

        return ModerationEvent::query()->create([
            'user_id' => $author?->id,
            'company_id' => $context->isEmployerAuthored() ? $author?->company_id : null,
            'context' => $context,
            'subject_type' => $subject instanceof Model ? $subject->getMorphClass() : null,
            'subject_id' => $subject instanceof Model ? $subject->getKey() : null,
            'excerpt' => $context->isEmployerAuthored() ? Str::limit(trim($text), ModerationEvent::EXCERPT_LENGTH - 3) : null,
            'reason' => $result->reason,
            'suggestion' => $result->suggestion,
            'moderator' => $this->moderatorFor($text),
        ]);
    }

    /**
     * The keyword list blocks first; anything it lets through was blocked by Claude.
     */
    private function moderatorFor(string $text): string
    {
        return $this->keywordModerator->check($text)->allowed
            ? ModerationEvent::MODERATOR_CLAUDE
            : ModerationEvent::MODERATOR_KEYWORD;
    }
}
