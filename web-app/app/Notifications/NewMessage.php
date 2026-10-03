<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Tells the other side of the conversation about a new message.
 */
class NewMessage extends Notification
{
    use Queueable;

    public function __construct(public Message $message, public string $senderName) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{kind: string, title: string, body: string|null, url: string, conversation_id: int}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'new_message',
            'title' => "Nowa wiadomość od: {$this->senderName}",
            'body' => Str::limit($this->message->body, 120),
            'url' => route('conversations.show', $this->message->conversation_id, absolute: false),
            'conversation_id' => $this->message->conversation_id,
        ];
    }
}
