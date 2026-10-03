<?php

namespace App\Http\Resources;

use App\Enums\EmploymentFraction;
use App\Enums\WorkMode;
use App\Models\CandidateProfile;
use App\Models\Skill;
use App\Services\Matching\MatchResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The only shape in which an employer may see a candidate before she accepts an invitation.
 * Never add surname, email, photo, CV, leave or due dates here.
 *
 * @mixin CandidateProfile
 */
class AnonymousCandidateResource extends JsonResource
{
    public function __construct(CandidateProfile $resource, public ?MatchResult $match = null, public bool $isInterested = false)
    {
        parent::__construct($resource);
    }

    /**
     * @return array{id: int, anonymous_name: string, initial: string, headline: string|null, years_of_experience: int|null, ai_summary: string|null, skills: list<array{name: string, matched: bool}>, available_from: string|null, employment_fractions: list<string>, work_modes: list<string>, match: array<string, mixed>|null, is_interested: bool}
     */
    public function toArray(Request $request): array
    {
        $anonymousName = $this->resource->anonymousName();
        $matchedSkillNames = $this->match ? [...$this->match->matchedRequired, ...$this->match->matchedNiceToHave] : [];

        return [
            'id' => $this->resource->id,
            'anonymous_name' => $anonymousName,
            'initial' => mb_strtoupper(mb_substr($anonymousName, 0, 1)),
            'headline' => $this->resource->headline,
            'years_of_experience' => $this->resource->years_of_experience,
            'ai_summary' => $this->resource->ai_summary,
            'skills' => $this->resource->confirmedSkills
                ->map(fn (Skill $skill): array => ['name' => $skill->name, 'matched' => in_array($skill->name, $matchedSkillNames, true)])
                ->sortByDesc('matched')
                ->values()
                ->all(),
            'available_from' => $this->resource->available_from?->toDateString(),
            'employment_fractions' => collect($this->resource->employment_fractions ?? [])
                ->map(fn (string $value): ?string => EmploymentFraction::tryFrom($value)?->label())
                ->filter()
                ->values()
                ->all(),
            'work_modes' => collect($this->resource->work_modes ?? [])
                ->map(fn (string $value): ?string => WorkMode::tryFrom($value)?->label())
                ->filter()
                ->values()
                ->all(),
            'match' => $this->match?->toArray(),
            'is_interested' => $this->isInterested,
        ];
    }
}
