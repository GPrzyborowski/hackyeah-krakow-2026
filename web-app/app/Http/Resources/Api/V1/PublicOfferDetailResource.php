<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\SkillImportance;
use App\Http\Controllers\Public\Concerns\PresentsCompanyRatings;
use App\Models\JobOffer;
use App\Models\Skill;
use App\Services\JobSharing\Workday;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public offer detail: the card fields plus description, skills and the company's rating summary.
 * Expects `skills` and `company.approvedReviews` to be loaded.
 *
 * @property JobOffer $resource
 */
class PublicOfferDetailResource extends JsonResource
{
    use PresentsCompanyRatings;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $offer = $this->resource;
        $company = $offer->company;
        $skillNames = fn (SkillImportance $importance): array => array_values($offer->skills
            ->filter(fn (Skill $skill): bool => $skill->getRelationValue('pivot')?->getAttribute('importance') === $importance->value)
            ->map(fn (Skill $skill): string => $skill->name)
            ->all());

        return [
            ...(new PublicOfferResource($offer))->toArray($request),
            'description' => $offer->description,
            'required_skills' => $skillNames(SkillImportance::Required),
            'nice_to_have_skills' => $skillNames(SkillImportance::NiceToHave),
            'workday_starts_at' => $offer->is_job_share ? Workday::format(Workday::forOffer($offer)->startsAt) : null,
            'workday_ends_at' => $offer->is_job_share ? Workday::format(Workday::forOffer($offer)->endsAt) : null,
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'city' => $company->city,
                'verified' => $company->isVerified(),
                'rating' => $this->ratingSummary($company),
                'featured_quote' => $this->featuredQuote($company),
            ],
        ];
    }
}
