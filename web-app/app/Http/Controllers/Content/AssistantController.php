<?php

namespace App\Http\Controllers\Content;

use App\Enums\AssistantRole;
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
     * @var list<string>
     */
    private const array SUGGESTIONS = [
        'Zasiłek macierzyński',
        'Urlop rodzicielski',
        'Powrót na część etatu',
        'Czy muszę mówić o ciąży na rozmowie?',
    ];

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
            'suggestions' => self::SUGGESTIONS,
            'disclaimer' => LegalAssistant::DISCLAIMER,
        ]);
    }

    /**
     * Store the question and the answer grounded in legal sources and blog articles.
     */
    public function store(AskAssistantRequest $request, LegalAssistant $assistant): RedirectResponse
    {
        $question = trim($request->validated('question'));
        $user = $request->user();

        $user->assistantMessages()->create([
            'role' => AssistantRole::User,
            'content' => $question,
        ]);

        $answer = $assistant->answer($question);

        $user->assistantMessages()->create([
            'role' => AssistantRole::Assistant,
            'content' => $answer->content,
            'citations' => $answer->citations,
        ]);

        return to_route('assistant.index');
    }
}
