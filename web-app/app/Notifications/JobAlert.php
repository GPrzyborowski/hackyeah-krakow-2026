<?php

namespace App\Notifications;

use App\Models\JobOffer;
use App\Services\Matching\MatchResult;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Weekly digest of new published offers that match the candidate well and fit her start date.
 */
class JobAlert extends Notification
{
    use Queueable;

    /**
     * @param  Collection<int, array{offer: JobOffer, match: MatchResult}>  $matches  Best match first.
     */
    public function __construct(public Collection $matches) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = $this->matches->count();

        $message = (new MailMessage)
            ->subject($count === 1 ? 'Nowa oferta dopasowana do Ciebie' : "Nowe oferty dopasowane do Ciebie ({$count})")
            ->greeting('Cześć!')
            ->line('W tym tygodniu pojawiły się oferty, które pasują do Twoich umiejętności i daty powrotu:');

        foreach ($this->matches as $row) {
            $offer = $row['offer'];
            $details = array_filter([
                "Dopasowanie {$row['match']->score}%",
                self::formatSalary($offer->salary_min, $offer->salary_max),
            ]);

            $message
                ->line("**{$offer->title}** – {$offer->company->name}")
                ->line(implode(' · ', $details))
                ->line('[Zobacz ofertę]('.route('candidate.offers.show', $offer).')');
        }

        return $message
            ->action('Wszystkie oferty', route('candidate.offers.index'))
            ->line('Powiadomienia o ofertach wyłączysz w ustawieniach prywatności swojego profilu.');
    }

    /**
     * Same wording as the offer cards: "8 000–10 000 zł brutto", "od 8 000 zł brutto", "do 10 000 zł brutto".
     */
    public static function formatSalary(?int $min, ?int $max): ?string
    {
        $format = fn (int $amount): string => number_format($amount, 0, ',', ' ');

        return match (true) {
            $min !== null && $max !== null => "{$format($min)}–{$format($max)} zł brutto",
            $min !== null => "od {$format($min)} zł brutto",
            $max !== null => "do {$format($max)} zł brutto",
            default => null,
        };
    }
}
