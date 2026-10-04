<?php

namespace App\Services\JobSharing;

use App\Enums\JobSharePairStatus;
use App\Enums\JoinLinkProblem;
use App\Models\CandidateProfile;
use App\Models\JobOffer;
use App\Models\JobSharePair;
use App\Models\JobSharePairInvitation;
use App\Models\User;

/**
 * Read side of the links with which a candidate invites a friend to her job-sharing pair:
 * finding a link, deciding whether the visitor may join and presenting it. Changes live in PairLifecycle.
 */
class PairJoinLinks
{
    public function __construct(
        private readonly PartnerFinder $finder,
        private readonly PairPresenter $presenter,
    ) {}

    public function find(string $token): ?JobSharePairInvitation
    {
        return JobSharePairInvitation::query()
            ->with(['pair.jobOffer.company', 'invitedBy.user'])
            ->where('token', $token)
            ->first();
    }

    /**
     * The candidate's own pair for the offer that still waits for a second person, if any.
     */
    public function waitingPairFor(CandidateProfile $profile, JobOffer $offer): ?JobSharePair
    {
        $pair = $this->finder->activePairFor($profile, $offer);

        return $pair !== null && $this->finder->isWaitingForPartner($pair) && $pair->hasAcceptedMember($profile) ? $pair : null;
    }

    /**
     * The usable link of a pair waiting for a partner, shown only to the initiator who shares it.
     */
    public function usableLinkFor(JobSharePair $pair, CandidateProfile $viewer): ?JobSharePairInvitation
    {
        if (! $this->finder->isWaitingForPartner($pair) || ! $pair->hasAcceptedMember($viewer)) {
            return null;
        }

        return $pair->joinLinks()->usable()->latest('id')->first();
    }

    /**
     * Why the visitor cannot join through the link (null when she can). Problems of the link itself come first,
     * so a guest sees them too; the rest depends on who is signed in.
     */
    public function problemWith(?JobSharePairInvitation $invitation, ?User $user): ?JoinLinkProblem
    {
        if ($invitation === null) {
            return JoinLinkProblem::Invalid;
        }

        $pair = $invitation->pair;
        $offer = $pair->jobOffer;

        $problem = match (true) {
            $invitation->isAccepted() => JoinLinkProblem::Used,
            $invitation->isExpired() => JoinLinkProblem::Expired,
            ! $offer->is_job_share || ! $offer->isPublished() => JoinLinkProblem::OfferClosed,
            $pair->status !== JobSharePairStatus::Forming => JoinLinkProblem::PairClosed,
            $pair->members()->count() >= JobSharePair::MAX_MEMBERS => JoinLinkProblem::PairFull,
            default => null,
        };

        if ($problem !== null || $user === null) {
            return $problem;
        }

        if (! $user->isCandidate()) {
            return JoinLinkProblem::NotCandidate;
        }

        $profile = $user->candidateProfile;

        if ($profile === null) {
            return null;
        }

        if ($profile->id === $invitation->invited_by_candidate_profile_id || $pair->hasMember($profile)) {
            return JoinLinkProblem::OwnLink;
        }

        $hasOtherPair = $profile->jobSharePairs()
            ->where('job_offer_id', $pair->job_offer_id)
            ->whereIn('status', PartnerFinder::ACTIVE_STATUSES)
            ->wherePivotNotNull('accepted_at')
            ->exists();

        return $hasOtherPair ? JoinLinkProblem::AlreadyPaired : null;
    }

    /**
     * @return array{url: string, token: string, expires_at: string}
     */
    public function presentLink(JobSharePairInvitation $invitation): array
    {
        return [
            'url' => $invitation->url(),
            'token' => $invitation->token,
            'expires_at' => $invitation->expires_at->toIso8601String(),
        ];
    }

    /**
     * What the invited friend sees before joining: the offer and the inviter (first name + surname initial only).
     *
     * @return array{offer: array{id: int, title: string, company: string, city: string|null, work_mode_label: string, employment_fraction_label: string, workday_starts_at: string|null, workday_ends_at: string|null, hours_per_person: float|null}, inviter: array{first_name: string, display_name: string}, expires_at: string}
     */
    public function preview(JobSharePairInvitation $invitation): array
    {
        $offer = $invitation->pair->jobOffer;
        $summary = Workday::presentOffer($offer);

        return [
            'offer' => [
                'id' => $offer->id,
                'title' => $offer->title,
                'company' => $offer->company->name,
                'city' => $offer->city,
                'work_mode_label' => $offer->work_mode->label(),
                'employment_fraction_label' => $offer->employment_fraction->label(),
                'workday_starts_at' => $summary['workday_starts_at'],
                'workday_ends_at' => $summary['workday_ends_at'],
                'hours_per_person' => $summary['hours_per_person'],
            ],
            'inviter' => [
                'first_name' => $this->presenter->firstName($invitation->invitedBy),
                'display_name' => $invitation->invitedBy->anonymousName(),
            ],
            'expires_at' => $invitation->expires_at->toIso8601String(),
        ];
    }
}
