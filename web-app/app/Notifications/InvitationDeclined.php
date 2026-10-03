<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells the inviting company the candidate declined; she stays anonymous.
 */
class InvitationDeclined extends Notification
{
    use Queueable;

    public function __construct(public Invitation $invitation) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{kind: string, title: string, body: string|null, url: string, invitation_id: int}
     */
    public function toArray(object $notifiable): array
    {
        $name = $this->invitation->candidateProfile->anonymousName();

        return [
            'kind' => 'invitation_declined',
            'title' => "{$name} odrzuciła zaproszenie na stanowisko {$this->invitation->jobOffer->title}",
            'body' => null,
            'url' => route('employer.invitations.index', absolute: false),
            'invitation_id' => $this->invitation->id,
        ];
    }
}
