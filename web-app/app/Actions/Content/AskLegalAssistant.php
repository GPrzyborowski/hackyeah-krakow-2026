<?php

namespace App\Actions\Content;

use App\Enums\AssistantRole;
use App\Models\AssistantMessage;
use App\Models\User;
use App\Services\Ai\LegalAssistant;

/**
 * Stores the user's question and the legal assistant's answer (grounded in legal sources and blog articles).
 */
class AskLegalAssistant
{
    public function __construct(private readonly LegalAssistant $assistant) {}

    /**
     * @return array{question: AssistantMessage, answer: AssistantMessage}
     */
    public function handle(User $user, string $question): array
    {
        $question = trim($question);

        $questionMessage = $user->assistantMessages()->create([
            'role' => AssistantRole::User,
            'content' => $question,
        ]);

        $answer = $this->assistant->answer($question);

        $answerMessage = $user->assistantMessages()->create([
            'role' => AssistantRole::Assistant,
            'content' => $answer->content,
            'citations' => $answer->citations,
        ]);

        return ['question' => $questionMessage, 'answer' => $answerMessage];
    }
}
