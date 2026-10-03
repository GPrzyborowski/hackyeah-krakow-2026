<?php

namespace App\Http\Controllers\Api\V1\JobSharing;

use App\Http\Controllers\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PartnerCandidateResource;
use App\Models\JobOffer;
use App\Services\JobSharing\PartnerFinder;
use App\Services\JobSharing\Workday;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PartnerController extends Controller
{
    use ResolvesCandidateProfile;

    /**
     * Anonymous candidates who could share the offer with the signed-in candidate (interested ones first, then by match).
     * Empty until her own profile is published or when she already has an active pair for the offer.
     */
    public function index(Request $request, JobOffer $offer, PartnerFinder $finder): AnonymousResourceCollection
    {
        abort_unless($offer->is_job_share && $offer->isPublished(), 404);

        $profile = $this->candidateProfile($request);
        $offer->load(['company', 'skills']);
        $offerSkillNames = array_values($offer->skills->pluck('name')->all());
        $activePair = $finder->activePairFor($profile, $offer);

        $partners = $profile->isPublished() ? $finder->partnersFor($profile, $offer) : collect();

        return PartnerCandidateResource::collection(
            $partners->map(fn (array $row): array => [...$row, 'offer_skill_names' => $offerSkillNames])->values(),
        )->additional(['meta' => [
            'offer' => [
                'id' => $offer->id,
                'title' => $offer->title,
                'company' => $offer->company->name,
                'city' => $offer->city,
                'work_mode_label' => $offer->work_mode->label(),
                'job_share' => Workday::presentOffer($offer),
            ],
            'my_day_part_label' => $profile->preferred_day_part?->label(),
            'is_profile_published' => $profile->isPublished(),
            'active_pair_id' => $activePair?->id,
        ]]);
    }
}
