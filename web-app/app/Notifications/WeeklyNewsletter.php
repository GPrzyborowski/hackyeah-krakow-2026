<?php

namespace App\Notifications;

use App\Models\Article;
use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Symfony\Component\Mime\Email;

/**
 * "Jeden nowy tekst w tygodniu": the newest blog article(s) with a one-click unsubscribe link.
 */
class WeeklyNewsletter extends Notification
{
    use Queueable;

    /**
     * @param  Collection<int, Article>  $articles  Newest first.
     */
    public function __construct(public Collection $articles) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(NewsletterSubscriber $notifiable): MailMessage
    {
        $unsubscribeUrl = route('newsletter.unsubscribe', ['token' => $notifiable->token]);
        /** @var Article $leadArticle */
        $leadArticle = $this->articles->first();

        $message = (new MailMessage)
            ->subject($this->articles->count() === 1
                ? "Nowy tekst na blogu MomJobs: {$leadArticle->title}"
                : 'Nowe teksty na blogu MomJobs')
            ->greeting('Cześć!')
            ->line('Jeden nowy tekst w tygodniu – bez reklam i bez spamu. Oto, co przygotowałyśmy:');

        foreach ($this->articles as $article) {
            $message
                ->line("**{$article->title}** · {$article->reading_minutes} min czytania")
                ->line($article->excerpt)
                ->line('['.($article->is($leadArticle) ? 'Czytaj dalej' : 'Przeczytaj').']('.route('blog.show', $article).')');
        }

        return $message
            ->action('Przejdź do bloga', route('blog.index'))
            ->line("Nie chcesz już dostawać newslettera? [Wypisz się jednym kliknięciem]({$unsubscribeUrl}).")
            ->withSymfonyMessage(function (Email $email) use ($unsubscribeUrl): void {
                $email->getHeaders()->addTextHeader('List-Unsubscribe', "<{$unsubscribeUrl}>");
            });
    }
}
