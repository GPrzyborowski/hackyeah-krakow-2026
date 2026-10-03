<?php

namespace App\Notifications;

use App\Models\CandidateProfile;
use App\Models\JobSharePair;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells the pair initiator that the invited partner joined the pair.
 */
class PairInvitationAccepted extends Notification
{
    use Queueable;

    public function __construct(public JobSharePair $pair, public CandidateProfile $partner) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{kind: string, title: string, body: string|null, url: string, job_share_pair_id: int}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'pair_invitation_accepted',
            'title' => "{$this->partner->anonymousName()} przyjęła zaproszenie do pary job-sharing na stanowisko {$this->pair->jobOffer->title}",
            'body' => 'Ustalcie razem podział dnia pracy.',
            'url' => route('job-sharing.pairs.show', $this->pair, absolute: false),
            'job_share_pair_id' => $this->pair->id,
        ];
    }
}
