<?php

namespace App\Http\Controllers\Api\V1\JobSharing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PollRequest;
use App\Http\Requests\JobSharing\StorePairMessageRequest;
use App\Http\Resources\Api\V1\PairMessageResource;
use App\Models\JobSharePair;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Private chat of a pair: only members who accepted the pair (not the employer) read and write it; not moderated.
 */
class PairMessageController extends Controller
{
    private const int PER_PAGE = 20;

    /**
     * Messages newest first; poll with `since` / `after_id`.
     */
    public function index(PollRequest $request, JobSharePair $pair): AnonymousResourceCollection
    {
        Gate::authorize('chat', $pair);

        $messages = $request->applyTo($pair->messages()->getQuery())
            ->with('author:id,name')
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return PairMessageResource::collection($messages);
    }

    public function store(StorePairMessageRequest $request, JobSharePair $pair): JsonResponse
    {
        Gate::authorize('sendMessage', $pair);

        $message = $pair->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $request->string('body')->trim()->toString(),
        ]);

        return (new PairMessageResource($message->load('author:id,name')))->response()->setStatusCode(201);
    }
}
