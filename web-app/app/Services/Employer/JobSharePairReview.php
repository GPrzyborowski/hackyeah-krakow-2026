<?php

namespace App\Services\Employer;

use App\Enums\JobSharePairStatus;
use App\Enums\SkillImportance;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\JobOffer;
use App\Models\JobSharePair;
use App\Models\Skill;
use App\Services\JobSharing\PairPresenter;
use App\Services\Matching\MatchResult;
use App\Services\Matching\MatchScorer;
use Illuminate\Support\Collection;

/**
 * Job-sharing pairs as the offer's company reviews them: anonymous members, combined skill coverage and the day split.
 *
 * @phpstan-type PairRow array{pair: JobSharePair, members: Collection<int, array{candidate: CandidateProfile, match: MatchResult}>, coverage: array{covered: list<string>, missing: list<string>, percent: int}, schedule: list<array{candidate_profile_id: int, starts_at: string, ends_at: string}>}
 */
class JobSharePairReview
{
    /**
     * Statuses of pairs the employer can see: sent to the company and already decided.
     */
    public const array VISIBLE_STATUSES = [
        JobSharePairStatus::Submitted,
        JobSharePairStatus::Invited,
        JobSharePairStatus::Accepted,
        JobSharePairStatus::Rejected,
        JobSharePairStatus::Hired,
        JobSharePairStatus::Declined,
    ];

    public function __construct(private readonly PairPresenter $presenter, private readonly MatchScorer $scorer) {}

    /**
     * Visible pairs of the offer, those waiting for a decision first.
     *
     * @return Collection<int, PairRow>
     */
    public function rows(JobOffer $offer, Company $company): Collection
    {
        $offer->loadMissing(['skills', 'company']);
        $requiredSkillNames = $this->requiredSkillNames($offer);

        return $offer->jobSharePairs()
            ->whereIn('status', self::VISIBLE_STATUSES)
            ->visibleToCompany($company)
            ->with(['members.user', 'members.confirmedSkills'])
            ->orderByRaw('case when status = ? then 0 else 1 end', [JobSharePairStatus::Submitted->value])
            ->latest('submitted_at')
            ->get()
            ->map(fn (JobSharePair $pair): array => $this->buildRow($pair, $offer, $requiredSkillNames))
            ->values();
    }

    /**
     * @return PairRow
     */
    public function row(JobSharePair $pair): array
    {
        $offer = $pair->jobOffer->loadMissing(['skills', 'company']);
        $pair->loadMissing(['members.user', 'members.confirmedSkills']);

        return $this->buildRow($pair, $offer, $this->requiredSkillNames($offer));
    }

    /**
     * A pair is hidden when any of its members hid her profile from the company or unpublished it.
     */
    public function isVisibleTo(JobSharePair $pair, Company $company): bool
    {
        return JobSharePair::query()->visibleToCompany($company)->whereKey($pair->id)->exists();
    }

    /**
     * @param  list<string>  $requiredSkillNames
     * @return PairRow
     */
    private function buildRow(JobSharePair $pair, JobOffer $offer, array $requiredSkillNames): array
    {
        $pair->setRelation('jobOffer', $offer);
        $members = $this->presenter->members($pair);
        $skillNames = $members->flatMap(fn (CandidateProfile $member) => $member->confirmedSkills->pluck('name'))->unique();
        $covered = array_values(array_filter($requiredSkillNames, fn (string $name): bool => $skillNames->contains($name)));

        return [
            'pair' => $pair,
            'members' => $members->toBase()->map(fn (CandidateProfile $member): array => ['candidate' => $member, 'match' => $this->scorer->score($member, $offer)])->values(),
            'coverage' => [
                'covered' => $covered,
                'missing' => array_values(array_diff($requiredSkillNames, $covered)),
                'percent' => $requiredSkillNames === [] ? 100 : (int) round(count($covered) / count($requiredSkillNames) * 100),
            ],
            'schedule' => $this->presenter->schedule($pair, $members),
        ];
    }

    /**
     * @return list<string>
     */
    private function requiredSkillNames(JobOffer $offer): array
    {
        return array_values($offer->skills
            ->filter(fn (Skill $skill): bool => $skill->getRelationValue('pivot')?->getAttribute('importance') === SkillImportance::Required->value)
            ->map(fn (Skill $skill): string => $skill->name)
            ->all());
    }
}
