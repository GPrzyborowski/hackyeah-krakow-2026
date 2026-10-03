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
    ) {}

    /**
     * @return array{workday_starts_at: string|null, workday_ends_at: string|null, hours_per_person: float|null, is_open_to_job_sharing: bool, pair: array{id: int, status: string, partner_name: string|null, awaiting_my_answer: bool}|null}|null
     */
    public function present(CandidateProfile $profile, JobOffer $offer): ?array
    {
        if (! $offer->is_job_share) {
            return null;
        }

        $summary = Workday::presentOffer($offer);
        $pair = $this->finder->activePairFor($profile, $offer);
        $pairData = null;

        if ($pair !== null) {
            $members = $this->presenter->members($pair);
            $partner = $members->first(fn (CandidateProfile $member): bool => $member->id !== $profile->id);
            $me = $members->first(fn (CandidateProfile $member): bool => $member->id === $profile->id);

            $pairData = [
                'id' => $pair->id,
                'status' => $pair->status->value,
                'partner_name' => $partner?->anonymousName(),
                'awaiting_my_answer' => $me !== null && ! $this->presenter->hasAccepted($me),
            ];
        }

        return [
            'workday_starts_at' => $summary['workday_starts_at'],
            'workday_ends_at' => $summary['workday_ends_at'],
            'hours_per_person' => $summary['hours_per_person'],
            'is_open_to_job_sharing' => $profile->open_to_job_sharing,
            'pair' => $pairData,
        ];
    }
}
