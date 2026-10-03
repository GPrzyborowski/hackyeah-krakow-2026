<?php

namespace App\Notifications;

use App\Models\CandidateProfile;
use App\Models\JobSharePair;
use App\Notifications\Concerns\SendsPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a candidate another candidate invited her to a job-sharing pair; the initiator stays anonymous (first name + initial).
 */
class PairInvitationReceived extends Notification
{
    use Queueable, SendsPush;

    public function __construct(public JobSharePair $pair, public CandidateProfile $initiator) {}

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
            ->subject('Zaproszenie do pary job-sharing')
            ->greeting('Cześć!')
            ->line($this->title())
            ->line('Zajrzyj na stronę pary, napisz do partnerki na czacie i zdecyduj, czy dołączasz.')
            ->action('Zobacz zaproszenie', route('job-sharing.pairs.show', $this->pair));
    }

    /**
     * @return array{kind: string, title: string, body: string|null, url: string, job_share_pair_id: int}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'pair_invitation_received',
            'title' => $this->title(),
            'body' => null,
            'url' => route('job-sharing.pairs.show', $this->pair, absolute: false),
            'job_share_pair_id' => $this->pair->id,
        ];
    }

    private function title(): string
    {
        return "{$this->initiator->anonymousName()} zaprasza Cię do pary job-sharing na stanowisko {$this->pair->jobOffer->title}";
    }
}
