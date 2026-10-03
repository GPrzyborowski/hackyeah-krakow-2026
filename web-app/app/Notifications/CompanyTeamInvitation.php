<?php

namespace App\Notifications;

use App\Models\CompanyInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * E-mail (on-demand, the address may have no account yet) with a signed link to join a company team.
 */
class CompanyTeamInvitation extends Notification
{
    use Queueable;

    public function __construct(public CompanyInvitation $invitation) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $companyName = $this->invitation->company->name;
        $inviterName = $this->invitation->invitedBy?->name;

        return (new MailMessage)
            ->subject("Zaproszenie do zespołu firmy {$companyName} w mumjobs")
            ->greeting('Cześć!')
            ->line($inviterName !== null
                ? "{$inviterName} zaprasza Cię do zespołu rekrutacyjnego firmy {$companyName} w mumjobs."
                : "Zapraszamy Cię do zespołu rekrutacyjnego firmy {$companyName} w mumjobs.")
            ->line('Po dołączeniu zobaczysz oferty firmy, dopasowane kandydatki i rozmowy zespołu.')
            ->action('Dołącz do zespołu', $this->url())
            ->line('Zaproszenie jest ważne do '.$this->invitation->expires_at->format('d.m.Y').'. Jeśli się go nie spodziewasz, zignoruj tę wiadomość.');
    }

    public function url(): string
    {
        return URL::signedRoute('company-invitations.show', ['token' => $this->invitation->token]);
    }
}
