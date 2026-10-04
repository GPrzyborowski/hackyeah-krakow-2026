<?php

namespace App\Http\Resources;

use App\Enums\SkillImportance;
use App\Models\JobOffer;
use App\Models\Skill;
use App\Services\JobSharing\Workday;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A job offer as its own company sees it in the employer panel.
 *
 * @mixin JobOffer
 */
class EmployerJobOfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $skills = $this->resource->relationLoaded('skills') ? $this->resource->skills : $this->resource->skills()->get();

        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'category' => $this->resource->category->value,
            'category_label' => $this->resource->category->label(),
            'city' => $this->resource->city,
            'work_mode' => $this->resource->work_mode->value,
            'work_mode_label' => $this->resource->work_mode->label(),
            'employment_fraction' => $this->resource->employment_fraction->value,
            'employment_fraction_label' => $this->resource->employment_fraction->label(),
            'salary_min' => $this->resource->salary_min,
            'salary_max' => $this->resource->salary_max,
            'start_date' => $this->resource->start_date->toDateString(),
            'description' => $this->resource->description,
            'flexible_hours' => $this->resource->flexible_hours,
            'fixed_meeting_hours' => $this->resource->fixed_meeting_hours,
            'childcare_subsidy' => $this->resource->childcare_subsidy,
            'nursery_distance_km' => $this->resource->nursery_distance_km,
            ...Workday::presentOffer($this->resource),
            'status' => $this->resource->status->value,
            'published_at' => $this->resource->published_at?->toIso8601String(),
            'required_skills' => $this->skillNames($skills, SkillImportance::Required),
            'nice_to_have_skills' => $this->skillNames($skills, SkillImportance::NiceToHave),
        ];
    }

    /**
     * @param  iterable<Skill>  $skills
     * @return list<string>
     */
    private function skillNames(iterable $skills, SkillImportance $importance): array
    {
        return array_values(collect($skills)
            ->filter(fn (Skill $skill): bool => $skill->getRelationValue('pivot')?->importance === $importance->value)
            ->map(fn (Skill $skill): string => $skill->name)
            ->all());
    }
}
