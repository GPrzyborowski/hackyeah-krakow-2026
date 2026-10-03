<?php

namespace App\Notifications;

use App\Models\Conversation;
use App\Notifications\Concerns\SendsPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the inviting company the candidate accepted; from now on her full name is revealed.
 */
class InvitationAccepted extends Notification
{
    use Queueable, SendsPush;

    public function __construct(public Conversation $conversation) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->withPush(['mail', 'database'], $notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Kandydatka przyjęła zaproszenie')
            ->greeting('Cześć!')
            ->line($this->title())
            ->line('Możecie teraz porozmawiać w aplikacji.')
            ->action('Przejdź do rozmowy', route('conversations.show', $this->conversation));
    }

    /**
     * @return array{kind: string, title: string, body: string|null, url: string, conversation_id: int}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'invitation_accepted',
            'title' => $this->title(),
            'body' => null,
            'url' => route('conversations.show', $this->conversation, absolute: false),
            'conversation_id' => $this->conversation->id,
        ];
    }

    private function title(): string
    {
        $invitation = $this->conversation->invitation;

        return "{$invitation->candidateProfile->user->name} przyjęła zaproszenie do rozmowy o stanowisku {$invitation->jobOffer->title}";
    }
}
