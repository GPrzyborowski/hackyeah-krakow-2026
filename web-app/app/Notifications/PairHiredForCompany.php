<?php

namespace App\Notifications;

use App\Models\CandidateProfile;
use App\Models\JobSharePair;
use App\Notifications\Concerns\SendsPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells the offer's company that both members of an invited job-sharing pair accepted; their names are revealed.
 */
class PairHiredForCompany extends Notification
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
        $names = $this->pair->members->map(fn (CandidateProfile $member): string => $member->user->name)->join(' i ');

        return [
            'kind' => 'pair_hired_company',
            'title' => "Para job-sharing {$names} przyjęła zaproszenie na stanowisko {$this->pair->jobOffer->title}",
            'body' => 'Obie osoby zaakceptowały zaproszenie – para jest zatrudniona.',
            'url' => route('employer.offers.job-share-pairs.index', $this->pair->job_offer_id, absolute: false),
            'job_share_pair_id' => $this->pair->id,
        ];
    }
}
