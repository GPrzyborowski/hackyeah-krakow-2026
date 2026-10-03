<?php

namespace App\Notifications;

use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Double opt-in e-mail with a signed confirmation link.
 */
class NewsletterConfirmation extends Notification
{
    use Queueable;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(NewsletterSubscriber $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Potwierdź zapis do newslettera MomJobs')
            ->greeting('Cześć!')
            ->line('Dziękujemy za zapis. Jeden nowy tekst w tygodniu, bez reklam i bez spamu.')
            ->action('Potwierdzam zapis', URL::temporarySignedRoute('newsletter.confirm', now()->addDays(7), ['token' => $notifiable->token]))
            ->line('Jeśli to nie Ty, zignoruj tę wiadomość – bez potwierdzenia nic nie wyślemy.')
            ->salutation('Zespół MomJobs');
    }
}
