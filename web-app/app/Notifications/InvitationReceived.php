<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the candidate a company wants to talk; carries only the company name and offer title.
 */
class InvitationReceived extends Notification
{
    use Queueable;

    public function __construct(public Invitation $invitation) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nowe zaproszenie do rozmowy')
            ->greeting('Dzień dobry!')
            ->line($this->title())
            ->line('Zaproszenie możesz przyjąć albo odrzucić. Dopiero po akceptacji firma zobaczy Twoje imię, nazwisko i e-mail.')
            ->action('Zobacz zaproszenie', route('candidate.invitations.index'))
            ->salutation('Zespół MomJobs');
    }

    /**
     * @return array{kind: string, title: string, body: string|null, url: string, invitation_id: int}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'invitation_received',
            'title' => $this->title(),
            'body' => null,
            'url' => route('candidate.invitations.index', absolute: false),
            'invitation_id' => $this->invitation->id,
        ];
    }

    private function title(): string
    {
        $offer = $this->invitation->jobOffer;

        return "Firma {$offer->company->name} zaprasza Cię do rozmowy o stanowisku {$offer->title}";
    }
}
