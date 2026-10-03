<?php

namespace App\Http\Controllers\Conversations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conversations\StoreMessageRequest;
use App\Models\Conversation;
use Illuminate\Http\RedirectResponse;

class MessageController extends Controller
{
    /**
     * Post a message to the thread (authorization and moderation happen in the form request).
     */
    public function store(StoreMessageRequest $request, Conversation $conversation): RedirectResponse
    {
        $message = $conversation->messages()->create([
            'user_id' => $request->user()->id,
            'body' => trim($request->validated('body')),
        ]);

        $conversation->update(['last_message_at' => $message->created_at]);

        return to_route('conversations.show', $conversation);
    }
}
