<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\EmploymentFraction;
use App\Enums\WorkMode;
use App\Models\CandidateProfile;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * The candidate's own full profile, including her private stage and return dates. Only ever returned to the candidate herself.
 * Never use it for employers – they get AnonymousCandidateResource.
 *
 * @property CandidateProfile $resource
 */
class CandidateProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $profile = $this->resource;
        $profile->loadMissing(['user', 'skills', 'hiddenFromCompany']);
        $cvExists = $profile->cv_path && Storage::disk('local')->exists($profile->cv_path);

        return [
            'id' => $profile->id,
            'full_name' => $profile->user->name,
            'anonymous_name' => $profile->anonymousName(),
            'headline' => $profile->headline,
            'years_of_experience' => $profile->years_of_experience,
            'city' => $profile->city,
            'phone' => $profile->phone,
            'photo_url' => $profile->photoUrl(forApi: true),
            'ai_summary' => $profile->ai_summary,
            'stage' => $profile->stage?->value,
            'stage_label' => $profile->stage?->label(),
            'available_from' => $profile->available_from?->toDateString(),
            'leave_starts_on' => $profile->leave_starts_on?->toDateString(),
            'due_date' => $profile->due_date?->toDateString(),
            'work_modes' => array_values(collect($profile->work_modes ?? [])
                ->map(fn (string $value): ?array => ($mode = WorkMode::tryFrom($value)) ? ['value' => $mode->value, 'label' => $mode->label()] : null)
                ->filter()
                ->all()),
            'employment_fractions' => array_values(collect($profile->employment_fractions ?? [])
                ->map(fn (string $value): ?array => ($fraction = EmploymentFraction::tryFrom($value)) ? ['value' => $fraction->value, 'label' => $fraction->label()] : null)
                ->filter()
                ->all()),
            'wants_flexible_hours' => (bool) $profile->wants_flexible_hours,
            'open_to_job_sharing' => (bool) $profile->open_to_job_sharing,
            'preferred_day_part' => $profile->preferred_day_part?->value,
            'preferred_day_part_label' => $profile->preferred_day_part?->label(),
            'privacy' => [
                'show_availability_instead_of_gap' => (bool) $profile->show_availability_instead_of_gap,
                'career_gap_note' => $profile->career_gap_note,
                'allow_direct_messages' => (bool) $profile->allow_direct_messages,
                'job_alerts_enabled' => (bool) $profile->job_alerts_enabled,
                'hidden_from_company' => $profile->hiddenFromCompany
                    ? ['id' => $profile->hiddenFromCompany->id, 'name' => $profile->hiddenFromCompany->name]
                    : null,
            ],
            'cv' => [
                'original_name' => $profile->cv_original_name,
                'size' => $cvExists ? Storage::disk('local')->size((string) $profile->cv_path) : null,
                'status' => $profile->cv_status?->value,
                'has_text' => filled($profile->cv_text),
            ],
            'skills' => array_values($profile->skills
                ->sortBy('name')
                ->map(fn (Skill $skill): array => [
                    'id' => $skill->id,
                    'name' => $skill->name,
                    'source' => $skill->getRelationValue('pivot')?->source,
                    'confirmed' => $skill->getRelationValue('pivot')?->confirmed_at !== null,
                ])
                ->all()),
            'suggested_positions' => $profile->suggested_positions ?? [],
            'onboarding_step' => $profile->onboarding_step,
            'published' => $profile->isPublished(),
            'published_at' => $profile->published_at?->toIso8601String(),
        ];
    }
}
