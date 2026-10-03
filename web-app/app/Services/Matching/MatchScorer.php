<?php

namespace App\Services\Matching;

use App\Enums\SkillImportance;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\JobOffer;
use App\Models\Skill;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Scores candidates against offers: 70% required skills, 20% nice-to-have skills, 10% work mode and FTE fit.
 * A candidate is only eligible when she can start no later than START_DATE_TOLERANCE_DAYS after the offer start.
 */
class MatchScorer
{
    public const int START_DATE_TOLERANCE_DAYS = 30;

    private const int REQUIRED_WEIGHT = 70;

    private const int NICE_TO_HAVE_WEIGHT = 20;

    private const int PREFERENCES_WEIGHT = 10;

    public function score(CandidateProfile $candidate, JobOffer $offer): MatchResult
    {
        $candidateSkills = $candidate->confirmedSkills()->pluck('skills.name', 'skills.id');
        $offerSkills = $offer->relationLoaded('skills') ? $offer->skills : $offer->skills()->get();

        $required = $offerSkills->filter(fn (Skill $skill): bool => $this->importanceOf($skill) === SkillImportance::Required);
        $niceToHave = $offerSkills->filter(fn (Skill $skill): bool => $this->importanceOf($skill) === SkillImportance::NiceToHave);

        [$matchedRequired, $missingRequired] = $required->partition(fn (Skill $skill): bool => $candidateSkills->has($skill->id));
        [$matchedNiceToHave, $missingNiceToHave] = $niceToHave->partition(fn (Skill $skill): bool => $candidateSkills->has($skill->id));

        $requiredCoverage = $required->isEmpty() ? 1.0 : $matchedRequired->count() / $required->count();
        $niceToHaveCoverage = $niceToHave->isEmpty() ? 1.0 : $matchedNiceToHave->count() / $niceToHave->count();

        $score = (int) round(
            self::REQUIRED_WEIGHT * $requiredCoverage
            + self::NICE_TO_HAVE_WEIGHT * $niceToHaveCoverage
            + self::PREFERENCES_WEIGHT * $this->preferencesFit($candidate, $offer)
        );

        return new MatchResult(
            score: $score,
            matchedRequired: $matchedRequired->pluck('name')->values()->all(),
            missingRequired: $missingRequired->pluck('name')->values()->all(),
            matchedNiceToHave: $matchedNiceToHave->pluck('name')->values()->all(),
            missingNiceToHave: $missingNiceToHave->pluck('name')->values()->all(),
            startDateCompatible: $this->canStartFor($candidate, $offer->start_date),
        );
    }

    public function canStartFor(CandidateProfile $candidate, CarbonInterface $startDate): bool
    {
        return $candidate->available_from !== null
            && $candidate->available_from->lte($startDate->copy()->addDays(self::START_DATE_TOLERANCE_DAYS));
    }

    /**
     * Published candidates visible to the company who can start in time for the given date.
     *
     * @return Builder<CandidateProfile>
     */
    public function eligibleCandidates(Company $company, CarbonInterface $startDate): Builder
    {
        return CandidateProfile::query()
            ->visibleTo($company)
            ->whereNotNull('available_from')
            ->whereDate('available_from', '<=', $startDate->copy()->addDays(self::START_DATE_TOLERANCE_DAYS));
    }

    /**
     * Live counters for the "Pasujące kandydatki" box while an employer edits an offer.
     *
     * @param  list<int>  $requiredSkillIds
     * @param  list<int>  $niceToHaveSkillIds
     * @return array{with_required: int, with_nice_to_have: int}
     */
    public function previewCounts(Company $company, CarbonInterface $startDate, array $requiredSkillIds, array $niceToHaveSkillIds): array
    {
        $withRequired = $this->eligibleCandidates($company, $startDate);

        foreach ($requiredSkillIds as $skillId) {
            $withRequired->whereHas('confirmedSkills', fn (Builder $query) => $query->whereKey($skillId));
        }

        $withNiceToHave = (clone $withRequired)->when(
            $niceToHaveSkillIds !== [],
            fn (Builder $query) => $query->whereHas('confirmedSkills', fn (Builder $query) => $query->whereKey($niceToHaveSkillIds)),
            fn (Builder $query) => $query->whereRaw('1 = 0'),
        );

        return [
            'with_required' => $withRequired->count(),
            'with_nice_to_have' => $withNiceToHave->count(),
        ];
    }

    /**
     * Eligible candidates for an offer that share at least one of its skills, best match first.
     *
     * @param  list<int>  $excludedCandidateIds
     * @return Collection<int, array{candidate: CandidateProfile, match: MatchResult}>
     */
    public function rankCandidatesFor(JobOffer $offer, array $excludedCandidateIds = []): Collection
    {
        $offer->loadMissing('skills', 'company');

        return $this->eligibleCandidates($offer->company, $offer->start_date)
            ->whereNotIn('id', $excludedCandidateIds)
            ->whereHas('confirmedSkills', fn (Builder $query) => $query->whereKey($offer->skills->modelKeys()))
            ->with(['user', 'confirmedSkills'])
            ->get()
            ->map(fn (CandidateProfile $candidate): array => ['candidate' => $candidate, 'match' => $this->score($candidate, $offer)])
            ->sortByDesc(fn (array $row): int => $row['match']->score)
            ->values();
    }

    /**
     * Published offers ranked for a candidate, best match first.
     *
     * @param  Builder<JobOffer>|null  $offers
     * @return Collection<int, array{offer: JobOffer, match: MatchResult}>
     */
    public function rankOffersFor(CandidateProfile $candidate, ?Builder $offers = null): Collection
    {
        return ($offers ?? JobOffer::query())
            ->published()
            ->with(['skills', 'company.approvedReviews'])
            ->get()
            ->map(fn (JobOffer $offer): array => ['offer' => $offer, 'match' => $this->score($candidate, $offer)])
            ->sortByDesc(fn (array $row): int => $row['match']->score)
            ->values();
    }

    private function importanceOf(Skill $skill): ?SkillImportance
    {
        /** @var object{importance: string}|null $pivot */
        $pivot = $skill->getRelationValue('pivot');

        return $pivot ? SkillImportance::tryFrom($pivot->importance) : null;
    }

    private function preferencesFit(CandidateProfile $candidate, JobOffer $offer): float
    {
        $workModeFits = empty($candidate->work_modes) || in_array($offer->work_mode->value, $candidate->work_modes, true);
        $fractionFits = empty($candidate->employment_fractions) || in_array($offer->employment_fraction->value, $candidate->employment_fractions, true);

        return ((int) $workModeFits + (int) $fractionFits) / 2;
    }
}
