<?php

namespace App\Http\Controllers\Content;

use App\Actions\Content\AskLegalAssistant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\AskAssistantRequest;
use App\Models\AssistantMessage;
use App\Services\Ai\LegalAssistant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssistantController extends Controller
{
    private const int HISTORY_LIMIT = 50;

    /**
     * The signed-in user's conversation with the legal assistant.
     */
    public function index(Request $request): Response
    {
        $messages = $request->user()->assistantMessages()
            ->latest('id')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->reverse();

        return Inertia::render('assistant/Index', [
            'messages' => $messages->map(fn (AssistantMessage $message): array => [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
                'citations' => $message->citations ?? [],
            ])->values(),
            'suggestions' => LegalAssistant::SUGGESTIONS,
            'disclaimer' => LegalAssistant::DISCLAIMER,
        ]);
    }

    /**
     * Store the question and the answer grounded in legal sources and blog articles.
     */
    public function store(AskAssistantRequest $request, AskLegalAssistant $askLegalAssistant): RedirectResponse
    {
        $askLegalAssistant->handle($request->user(), $request->validated('question'));

        return to_route('assistant.index');
    }
}
