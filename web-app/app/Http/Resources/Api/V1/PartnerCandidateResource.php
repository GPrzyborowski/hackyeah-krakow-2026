<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CandidateProfile;
use App\Models\Skill;
use App\Services\Matching\MatchResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A possible job-sharing partner, anonymous like for employers: first name + surname initial, headline, experience,
 * availability, preferred part of the day and confirmed skills. Never surname, e-mail, CV or leave/due dates.
 *
 * @property array{candidate: CandidateProfile, match: MatchResult, is_interested: bool, is_complementary: bool, offer_skill_names: list<string>} $resource
 */
class PartnerCandidateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $candidate = $this->resource['candidate'];
        $offerSkillNames = $this->resource['offer_skill_names'];
        $anonymousName = $candidate->anonymousName();

        return [
            'id' => $candidate->id,
            'anonymous_name' => $anonymousName,
            'initial' => mb_strtoupper(mb_substr($anonymousName, 0, 1)),
            'headline' => $candidate->headline,
            'years_of_experience' => $candidate->years_of_experience,
            'available_from' => $candidate->available_from?->toDateString(),
            'preferred_day_part' => $candidate->preferred_day_part?->value,
            'preferred_day_part_label' => $candidate->preferred_day_part?->label(),
            'skills' => array_values($candidate->confirmedSkills
                ->map(fn (Skill $skill): array => ['name' => $skill->name, 'matched' => in_array($skill->name, $offerSkillNames, true)])
                ->sortByDesc('matched')
                ->all()),
            'score' => $this->resource['match']->score,
            'is_interested' => $this->resource['is_interested'],
            'is_complementary' => $this->resource['is_complementary'],
        ];
    }
}
