<?php

namespace App\Http\Controllers\Conversations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conversations\StoreMessageRequest;
use App\Models\Conversation;
use App\Services\Conversations\ConversationInbox;
use Illuminate\Http\RedirectResponse;

class MessageController extends Controller
{
    /**
     * Post a message to the thread (authorization and moderation happen in the form request).
     */
    public function store(StoreMessageRequest $request, Conversation $conversation, ConversationInbox $inbox): RedirectResponse
    {
        $inbox->post($conversation, $request->user(), $request->validated('body'));

        return to_route('conversations.show', $conversation);
    }
}
