<?php

namespace App\Notifications;

use App\Models\JobSharePair;
use App\Notifications\Concerns\SendsPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Congratulates a member of a job-sharing pair: the employer marked the pair as hired.
 */
class PairHired extends Notification
{
    use Queueable, SendsPush;

    public function __construct(public JobSharePair $pair) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->withPush(['database'], $notifiable);
    }

    /**
     * @return array{kind: string, title: string, body: string|null, url: string, job_share_pair_id: int}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'pair_hired',
            'title' => "Gratulacje! Wasza para została zatrudniona na stanowisko {$this->pair->jobOffer->title}",
            'body' => "{$this->pair->jobOffer->company->name} potwierdziła zatrudnienie Waszej pary. Szczegóły ustalicie w rozmowie z firmą.",
            'url' => route('job-sharing.pairs.show', $this->pair, absolute: false),
            'job_share_pair_id' => $this->pair->id,
        ];
    }
}
