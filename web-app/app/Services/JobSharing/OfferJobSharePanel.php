<?php

namespace App\Services\JobSharing;

use App\Models\CandidateProfile;
use App\Models\JobOffer;

/**
 * Data for the job-sharing panel on a candidate's offer detail page.
 */
class OfferJobSharePanel
{
    public function __construct(
        private readonly PartnerFinder $finder,
        private readonly PairPresenter $presenter,
        private readonly PairJoinLinks $joinLinks,
    ) {}

    /**
     * @return array{workday_starts_at: string|null, workday_ends_at: string|null, hours_per_person: float|null, is_open_to_job_sharing: bool, pair: array{id: int, status: string, partner_name: string|null, awaiting_my_answer: bool, is_waiting_for_partner: bool}|null, can_create_join_link: bool, join_link: array{url: string, token: string, expires_at: string}|null}|null
     */
    public function present(CandidateProfile $profile, JobOffer $offer): ?array
    {
        if (! $offer->is_job_share) {
            return null;
        }

        $summary = Workday::presentOffer($offer);
        $pair = $this->finder->activePairFor($profile, $offer);
        $pairData = null;
        $joinLink = null;
        $isWaitingForPartner = false;

        if ($pair !== null) {
            $members = $this->presenter->members($pair);
            $partner = $members->first(fn (CandidateProfile $member): bool => $member->id !== $profile->id);
            $me = $members->first(fn (CandidateProfile $member): bool => $member->id === $profile->id);

            $isWaitingForPartner = $this->finder->isWaitingForPartner($pair) && $me !== null && $this->presenter->hasAccepted($me);
            $usableLink = $isWaitingForPartner ? $this->joinLinks->usableLinkFor($pair, $profile) : null;
            $joinLink = $usableLink !== null ? $this->joinLinks->presentLink($usableLink) : null;

            $pairData = [
                'id' => $pair->id,
                'status' => $pair->status->value,
                'partner_name' => $partner?->anonymousName(),
                'awaiting_my_answer' => $me !== null && ! $this->presenter->hasAccepted($me),
                'is_waiting_for_partner' => $isWaitingForPartner,
            ];
        }

        return [
            'workday_starts_at' => $summary['workday_starts_at'],
            'workday_ends_at' => $summary['workday_ends_at'],
            'hours_per_person' => $summary['hours_per_person'],
            'is_open_to_job_sharing' => $profile->open_to_job_sharing,
            'pair' => $pairData,
            'can_create_join_link' => $offer->isPublished() && ($pair === null || $isWaitingForPartner),
            'join_link' => $joinLink,
        ];
    }
}
