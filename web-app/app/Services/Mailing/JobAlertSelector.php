<?php

namespace App\Services\Mailing;

use App\Models\CandidateProfile;
use App\Models\JobOffer;
use App\Services\Matching\MatchResult;
use App\Services\Matching\MatchScorer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Picks the offers worth a weekly job alert: new, published, well matched, startable in time and not alerted before.
 */
class JobAlertSelector
{
    public const int MIN_SCORE = 60;

    public const int MAX_OFFERS = 5;

    public const int LOOKBACK_DAYS = 7;

    public function __construct(private MatchScorer $scorer) {}

    /**
     * Published candidates who want job alerts.
     *
     * @return Builder<CandidateProfile>
     */
    public function recipients(): Builder
    {
        return CandidateProfile::query()
            ->published()
            ->where('job_alerts_enabled', true)
            ->whereNotNull('available_from')
            ->whereHas('user')
            ->with(['user', 'confirmedSkills']);
    }

    /**
     * Offers published within the lookback window, loaded once for all candidates.
     *
     * @return EloquentCollection<int, JobOffer>
     */
    public function recentOffers(): EloquentCollection
    {
        return JobOffer::query()
            ->published()
            ->where('published_at', '>=', now()->subDays(self::LOOKBACK_DAYS))
            ->with(['skills', 'company'])
            ->get();
    }

    /**
     * @param  EloquentCollection<int, JobOffer>  $recentOffers
     * @return Collection<int, array{offer: JobOffer, match: MatchResult}>
     */
    public function offersFor(CandidateProfile $candidate, EloquentCollection $recentOffers): Collection
    {
        $alreadyAlertedIds = $candidate->alertedOffers()->pluck('job_offers.id')->all();

        return $recentOffers
            ->reject(fn (JobOffer $offer): bool => in_array($offer->id, $alreadyAlertedIds, true)
                || $offer->company_id === $candidate->hidden_from_company_id
                || ! $this->scorer->canStartFor($candidate, $offer->start_date))
            ->map(fn (JobOffer $offer): array => ['offer' => $offer, 'match' => $this->scorer->score($candidate, $offer)])
            ->filter(fn (array $row): bool => $row['match']->score >= self::MIN_SCORE)
            ->sortByDesc(fn (array $row): int => $row['match']->score)
            ->take(self::MAX_OFFERS)
            ->values()
            ->toBase();
    }

    /**
     * @param  Collection<int, JobOffer>  $offers
     */
    public function recordDelivery(CandidateProfile $candidate, Collection $offers): void
    {
        $candidate->alertedOffers()->syncWithoutDetaching(
            $offers->mapWithKeys(fn (JobOffer $offer): array => [$offer->id => ['sent_at' => now()]])->all(),
        );
    }
}
