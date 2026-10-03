<?php

namespace App\Services\JobSharing;

use App\Enums\JobSharePairStatus;
use App\Models\CandidateProfile;
use App\Models\JobOffer;
use App\Models\JobSharePair;
use Illuminate\Database\Eloquent\Builder;

/**
 * Job-sharing summary for the candidate home (web and mobile API): pairs waiting for her, her current pair,
 * a pair that recently ended without her decision and how many multi-person offers she could still apply to.
 */
class CandidatePairOverview
{
    /**
     * How long a rejected, declined or dissolved pair stays visible on the home screen.
     */
    public const int RECENTLY_ENDED_DAYS = 7;

    /**
     * @var list<JobSharePairStatus>
     */
    private const array ENDED_STATUSES = [
        JobSharePairStatus::Rejected,
        JobSharePairStatus::Declined,
        JobSharePairStatus::Cancelled,
    ];

    public function __construct(private readonly PairPresenter $presenter) {}

    /**
     * @return array{invitations_count: int, awaiting_answer_count: int, current_pair: array{id: int, status: string, status_label: string, offer_title: string, company: string, partner_name: string|null}|null, recently_ended_pair: array{id: int, status: string, status_label: string, offer_title: string, company: string, partner_name: string|null}|null, open_offers_count: int}
     */
    public function forProfile(CandidateProfile $profile): array
    {
        $pairs = $profile->jobSharePairs()
            ->with(['jobOffer.company', 'members.user'])
            ->where(fn (Builder $query) => $query
                ->whereIn('status', PartnerFinder::ACTIVE_STATUSES)
                ->orWhere(fn (Builder $query) => $query
                    ->whereIn('status', self::ENDED_STATUSES)
                    ->where('job_share_pairs.updated_at', '>=', now()->subDays(self::RECENTLY_ENDED_DAYS))))
            ->latest('job_share_pairs.updated_at')
            ->get();

        [$activePairs, $endedPairs] = $pairs->partition(
            fn (JobSharePair $pair): bool => in_array($pair->status, PartnerFinder::ACTIVE_STATUSES, true),
        );

        $currentPair = $activePairs->first(fn (JobSharePair $pair): bool => $this->presenter->viewerState($pair) !== 'invite_received');
        $recentlyEndedPair = $endedPairs->first();

        return [
            'invitations_count' => $activePairs->filter(fn (JobSharePair $pair): bool => $this->presenter->viewerState($pair) === 'invite_received')->count(),
            'awaiting_answer_count' => $activePairs->filter(fn (JobSharePair $pair): bool => $this->presenter->awaitsViewer($pair))->count(),
            'current_pair' => $currentPair ? $this->present($currentPair, $profile) : null,
            'recently_ended_pair' => $recentlyEndedPair ? $this->present($recentlyEndedPair, $profile) : null,
            'open_offers_count' => JobOffer::query()
                ->published()
                ->where('is_job_share', true)
                ->whereNotIn('id', $activePairs->pluck('job_offer_id'))
                ->count(),
        ];
    }

    /**
     * @return array{id: int, status: string, status_label: string, offer_title: string, company: string, partner_name: string|null}
     */
    private function present(JobSharePair $pair, CandidateProfile $profile): array
    {
        $partner = $pair->members->first(fn (CandidateProfile $member): bool => $member->id !== $profile->id);

        return [
            'id' => $pair->id,
            'status' => $pair->status->value,
            'status_label' => $pair->status->label(),
            'offer_title' => $pair->jobOffer->title,
            'company' => $pair->jobOffer->company->name,
            'partner_name' => $partner?->anonymousName(),
        ];
    }
}
