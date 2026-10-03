<?php

namespace App\Http\Controllers\Api\V1\Assistant;

use App\Actions\Content\AskLegalAssistant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\AskAssistantRequest;
use App\Http\Resources\Api\V1\AssistantMessageResource;
use App\Services\Ai\LegalAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The signed-in user's chat with the legal assistant (answers grounded in legal sources and blog articles).
 */
class AssistantMessageController extends Controller
{
    private const int PER_PAGE = 20;

    /**
     * History newest first, with the disclaimer and suggested questions in `meta`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $messages = $request->user()->assistantMessages()->latest('id')->paginate(self::PER_PAGE);

        return AssistantMessageResource::collection($messages)->additional(['meta' => [
            'disclaimer' => LegalAssistant::DISCLAIMER,
            'suggestions' => LegalAssistant::suggestionsForUser($request->user()),
        ]]);
    }

    /**
     * Ask a question; returns the stored question and the answer with its citations.
     */
    public function store(AskAssistantRequest $request, AskLegalAssistant $askLegalAssistant): JsonResponse
    {
        $messages = $askLegalAssistant->handle($request->user(), $request->validated('question'));

        return response()->json([
            'data' => [
                'question' => new AssistantMessageResource($messages['question']),
                'answer' => new AssistantMessageResource($messages['answer']),
            ],
            'meta' => ['disclaimer' => LegalAssistant::DISCLAIMER],
        ], 201);
    }
}
