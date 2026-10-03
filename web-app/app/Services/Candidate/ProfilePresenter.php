<?php

namespace App\Services\Candidate;

use App\Enums\EmploymentFraction;
use App\Enums\WorkMode;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Skill;
use Illuminate\Support\Facades\Storage;

/**
 * Shapes the candidate's own profile for the web onboarding wizard and the standalone profile page.
 * Contains private data (return dates, gap note) – only ever render it for the candidate herself.
 */
class ProfilePresenter
{
    /**
     * @return array<string, mixed>
     */
    public function profile(CandidateProfile $profile): array
    {
        $cvExists = $profile->cv_path && Storage::disk('local')->exists($profile->cv_path);

        return [
            'anonymous_name' => $profile->anonymousName(),
            'headline' => $profile->headline,
            'years_of_experience' => $profile->years_of_experience,
            'city' => $profile->city,
            'phone' => $profile->phone,
            'photo_url' => $profile->photoUrl(),
            'ai_summary' => $profile->ai_summary,
            'available_from' => $profile->available_from?->toDateString(),
            'leave_starts_on' => $profile->leave_starts_on?->toDateString(),
            'due_date' => $profile->due_date?->toDateString(),
            'work_modes' => $profile->work_modes ?? [],
            'employment_fractions' => $profile->employment_fractions ?? [],
            'wants_flexible_hours' => (bool) $profile->wants_flexible_hours,
            'open_to_job_sharing' => (bool) $profile->open_to_job_sharing,
            'preferred_day_part' => $profile->preferred_day_part?->value,
            'hidden_from_company_id' => $profile->hidden_from_company_id,
            'allow_direct_messages' => (bool) $profile->allow_direct_messages,
            'job_alerts_enabled' => (bool) $profile->job_alerts_enabled,
            'show_availability_instead_of_gap' => (bool) $profile->show_availability_instead_of_gap,
            'career_gap_note' => $profile->career_gap_note,
            'onboarding_step' => $profile->onboarding_step,
            'cv_original_name' => $profile->cv_original_name,
            'cv_size' => $cvExists ? Storage::disk('local')->size((string) $profile->cv_path) : null,
            'cv_status' => $profile->cv_status?->value,
            'cv_text' => $profile->cv_text,
            'suggested_positions' => $profile->suggested_positions ?? [],
            'is_published' => $profile->isPublished(),
        ];
    }

    /**
     * All tags on the profile, including unconfirmed AI suggestions (expects the skills relation).
     *
     * @return list<array{id: int, name: string, source: string|null, confirmed: bool}>
     */
    public function skills(CandidateProfile $profile): array
    {
        return array_values($profile->skills
            ->sortBy('name')
            ->map(fn (Skill $skill): array => [
                'id' => $skill->id,
                'name' => $skill->name,
                'source' => $skill->getRelationValue('pivot')?->source,
                'confirmed' => $skill->getRelationValue('pivot')?->confirmed_at !== null,
            ])
            ->all());
    }

    /**
     * Lookup lists shared by the wizard and the profile page.
     *
     * @return array{skillSuggestions: mixed, companies: mixed, workModes: mixed, employmentFractions: mixed}
     */
    public function options(): array
    {
        return [
            'skillSuggestions' => Skill::query()->suggestable()->orderBy('name')->pluck('name'),
            'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
            'workModes' => collect(WorkMode::cases())->map(fn (WorkMode $mode): array => ['value' => $mode->value, 'label' => $mode->label()]),
            'employmentFractions' => collect(EmploymentFraction::cases())->map(fn (EmploymentFraction $fraction): array => ['value' => $fraction->value, 'label' => $fraction->label()]),
        ];
    }
}
