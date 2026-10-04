<?php

namespace App\Actions\Employer;

use App\Enums\OfferCategory;
use App\Enums\OfferStatus;
use App\Enums\SkillImportance;
use App\Enums\WorkMode;
use App\Http\Requests\Employer\SaveJobOfferRequest;
use App\Models\JobOffer;
use App\Models\Skill;
use Illuminate\Support\Facades\DB;

/**
 * Saves an offer as a draft or publishes it, syncing its skills by name. Shared by the web panel and the mobile API.
 */
class SaveJobOffer
{
    public function handle(JobOffer $offer, SaveJobOfferRequest $request): JobOffer
    {
        DB::transaction(function () use ($offer, $request): void {
            $offer->fill([
                ...$request->safe()->only(['title', 'city', 'work_mode', 'start_date', 'description', 'employment_fraction', 'salary_min', 'salary_max']),
                'flexible_hours' => $request->boolean('flexible_hours'),
                'fixed_meeting_hours' => $request->boolean('fixed_meeting_hours'),
                'childcare_subsidy' => $request->boolean('childcare_subsidy'),
                'nursery_distance_km' => $request->validated('work_mode') === WorkMode::Remote->value ? null : $request->validated('nursery_distance_km'),
                'is_job_share' => $request->boolean('is_job_share'),
                'workday_starts_at' => $request->boolean('is_job_share') ? $request->validated('workday_starts_at') : null,
                'workday_ends_at' => $request->boolean('is_job_share') ? $request->validated('workday_ends_at') : null,
                'status' => $request->isPublishing() ? OfferStatus::Published : OfferStatus::Draft,
            ]);

            if ($request->validated('category') !== null) {
                $offer->category = OfferCategory::from($request->validated('category'));
            } elseif (! $offer->exists) {
                $offer->category = OfferCategory::Other;
            }

            if ($request->isPublishing() && $offer->published_at === null) {
                $offer->published_at = now();
            }

            $offer->save();

            $skills = [];

            foreach ($request->niceToHaveSkillNames() as $name) {
                $skills[Skill::findOrCreateByName($name)->id] = ['importance' => SkillImportance::NiceToHave->value];
            }

            foreach ($request->requiredSkillNames() as $name) {
                $skills[Skill::findOrCreateByName($name)->id] = ['importance' => SkillImportance::Required->value];
            }

            $offer->skills()->sync($skills);
        });

        return $offer;
    }
}
