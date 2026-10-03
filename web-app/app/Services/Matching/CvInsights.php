<?php

namespace App\Services\Matching;

use App\Enums\SkillImportance;
use App\Models\CandidateProfile;
use App\Models\JobOffer;
use App\Models\Skill;
use Illuminate\Support\Collection;

/**
 * Deterministic "Analiza CV": compares the candidate's confirmed skills with all published offers
 * (strengths by demand, suggested positions, missing skills with their score gain, best offers, stats).
 */
class CvInsights
{
    public const int GOOD_MATCH_THRESHOLD = 60;

    public const int MISSING_SKILL_BASE_THRESHOLD = 50;

    public const int TOP_OFFERS_LIMIT = 10;

    public const int MISSING_SKILLS_LIMIT = 6;

    public const int POSITIONS_LIMIT = 5;

    public function __construct(private readonly MatchScorer $matchScorer) {}

    /**
     * @return array{
     *     strengths: list<array{id: int, name: string, demand_count: int}>,
     *     positions: array{suggested: list<array{title: string, score: int}>, offers: list<array{title: string, score: int}>},
     *     missing_skills: list<array{id: int, name: string, offers_count: int, average_gain: int}>,
     *     offers: list<array{offer: JobOffer, match: MatchResult}>,
     *     stats: array{offers_total: int, matching_offers: int, average_score: int, can_start_on_time: int}
     * }
     */
    public function analyze(CandidateProfile $candidate): array
    {
        $ranked = $this->matchScorer->rankOffersFor($candidate);

        return [
            'strengths' => $this->strengths($candidate, $ranked),
            'positions' => [
                'suggested' => $this->suggestedPositions($candidate),
                'offers' => $this->bestOfferTitles($ranked),
            ],
            'missing_skills' => $this->missingSkills($candidate, $ranked),
            'offers' => array_values($ranked->take(self::TOP_OFFERS_LIMIT)->all()),
            'stats' => [
                'offers_total' => $ranked->count(),
                'matching_offers' => $ranked->filter(fn (array $row): bool => $row['match']->score >= self::GOOD_MATCH_THRESHOLD)->count(),
                'average_score' => (int) round((float) $ranked->avg(fn (array $row): int => $row['match']->score)),
                'can_start_on_time' => $ranked->filter(fn (array $row): bool => $row['match']->startDateCompatible)->count(),
            ],
        ];
    }

    /**
     * Confirmed skills ranked by the number of published offers that ask for them (required or nice to have).
     *
     * @param  Collection<int, array{offer: JobOffer, match: MatchResult}>  $ranked
     * @return list<array{id: int, name: string, demand_count: int}>
     */
    private function strengths(CandidateProfile $candidate, Collection $ranked): array
    {
        $demandBySkillId = $ranked
            ->flatMap(fn (array $row): array => $row['offer']->skills->modelKeys())
            ->countBy();

        return array_values($candidate->confirmedSkills
            ->map(fn (Skill $skill): array => [
                'id' => $skill->id,
                'name' => $skill->name,
                'demand_count' => (int) $demandBySkillId->get($skill->id, 0),
            ])
            ->sortBy([['demand_count', 'desc'], ['name', 'asc']])
            ->all());
    }

    /**
     * @return list<array{title: string, score: int}>
     */
    private function suggestedPositions(CandidateProfile $candidate): array
    {
        return array_values(collect($candidate->suggested_positions ?? [])
            ->sortByDesc('score')
            ->take(self::POSITIONS_LIMIT)
            ->map(fn (array $position): array => ['title' => $position['title'], 'score' => (int) $position['score']])
            ->all());
    }

    /**
     * Distinct titles of the best matching offers with their score.
     *
     * @param  Collection<int, array{offer: JobOffer, match: MatchResult}>  $ranked
     * @return list<array{title: string, score: int}>
     */
    private function bestOfferTitles(Collection $ranked): array
    {
        return array_values($ranked
            ->unique(fn (array $row): string => mb_strtolower($row['offer']->title))
            ->take(self::POSITIONS_LIMIT)
            ->map(fn (array $row): array => ['title' => $row['offer']->title, 'score' => $row['match']->score])
            ->all());
    }

    /**
     * Required skills most often missing in offers she already matches at least halfway,
     * with the average score gain computed by re-scoring with the skill virtually confirmed.
     *
     * @param  Collection<int, array{offer: JobOffer, match: MatchResult}>  $ranked
     * @return list<array{id: int, name: string, offers_count: int, average_gain: int}>
     */
    private function missingSkills(CandidateProfile $candidate, Collection $ranked): array
    {
        $confirmedSkillIds = $candidate->confirmedSkills->modelKeys();

        /** @var array<int, array{skill: Skill, gains: list<int>}> $candidates */
        $candidates = [];

        foreach ($ranked as $row) {
            if ($row['match']->score < self::MISSING_SKILL_BASE_THRESHOLD) {
                continue;
            }

            foreach ($row['offer']->skills as $skill) {
                if (in_array($skill->id, $confirmedSkillIds, true) || ! $this->isRequired($skill)) {
                    continue;
                }

                $candidates[$skill->id] ??= ['skill' => $skill, 'gains' => []];
                $candidates[$skill->id]['gains'][] = $this->scoreWith($candidate, $skill, $row['offer']) - $row['match']->score;
            }
        }

        return array_values(collect($candidates)
            ->map(fn (array $entry): array => [
                'id' => $entry['skill']->id,
                'name' => $entry['skill']->name,
                'offers_count' => count($entry['gains']),
                'average_gain' => (int) round(array_sum($entry['gains']) / count($entry['gains'])),
            ])
            ->sortBy([['offers_count', 'desc'], ['average_gain', 'desc'], ['name', 'asc']])
            ->take(self::MISSING_SKILLS_LIMIT)
            ->all());
    }

    /**
     * Score of the offer as if the candidate had also confirmed the given skill (nothing is persisted).
     */
    private function scoreWith(CandidateProfile $candidate, Skill $skill, JobOffer $offer): int
    {
        $virtual = clone $candidate;
        $virtual->setRelation('confirmedSkills', $candidate->confirmedSkills->concat([$skill]));

        return $this->matchScorer->score($virtual, $offer)->score;
    }

    private function isRequired(Skill $skill): bool
    {
        /** @var object{importance: string}|null $pivot */
        $pivot = $skill->getRelationValue('pivot');

        return $pivot !== null && $pivot->importance === SkillImportance::Required->value;
    }
}
