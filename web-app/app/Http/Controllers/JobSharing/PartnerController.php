<?php

namespace App\Http\Controllers\JobSharing;

use App\Http\Controllers\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Models\JobOffer;
use App\Models\Skill;
use App\Services\JobSharing\PairJoinLinks;
use App\Services\JobSharing\PartnerFinder;
use App\Services\JobSharing\Workday;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartnerController extends Controller
{
    use ResolvesCandidateProfile;

    /**
     * Anonymous list of candidates who could share the offer's position with the signed-in candidate.
     * Only data employers may see is exposed: first name + surname initial, headline, experience, confirmed skills.
     */
    public function index(Request $request, JobOffer $offer, PartnerFinder $finder, PairJoinLinks $joinLinks): Response
    {
        abort_unless($offer->is_job_share && $offer->isPublished(), 404);

        $profile = $this->candidateProfile($request);
        $offer->load(['company', 'skills']);
        $activePair = $finder->activePairFor($profile, $offer);
        $waitingPair = $joinLinks->waitingPairFor($profile, $offer);
        $joinLink = $waitingPair !== null ? $joinLinks->usableLinkFor($waitingPair, $profile) : null;
        $offerSkillNames = $offer->skills->pluck('name')->all();

        $partners = $profile->isPublished() ? $finder->partnersFor($profile, $offer) : collect();

        return Inertia::render('job-sharing/Partners', [
            'offer' => [
                'id' => $offer->id,
                'title' => $offer->title,
                'company' => $offer->company->name,
                'city' => $offer->city,
                'work_mode_label' => $offer->work_mode->label(),
                'job_share' => Workday::presentOffer($offer),
            ],
            'myDayPartLabel' => $profile->preferred_day_part?->label(),
            'isProfilePublished' => $profile->isPublished(),
            'activePairId' => $waitingPair === null ? $activePair?->id : null,
            'waitingPairId' => $waitingPair?->id,
            'joinLink' => $joinLink !== null ? $joinLinks->presentLink($joinLink) : null,
            'partners' => $partners->map(fn (array $row): array => [
                'id' => $row['candidate']->id,
                'anonymous_name' => $row['candidate']->anonymousName(),
                'initial' => mb_strtoupper(mb_substr($row['candidate']->anonymousName(), 0, 1)),
                'headline' => $row['candidate']->headline,
                'years_of_experience' => $row['candidate']->years_of_experience,
                'available_from' => $row['candidate']->available_from?->toDateString(),
                'preferred_day_part' => $row['candidate']->preferred_day_part?->value,
                'preferred_day_part_label' => $row['candidate']->preferred_day_part?->label(),
                'skills' => array_values($row['candidate']->confirmedSkills
                    ->map(fn (Skill $skill): array => ['name' => $skill->name, 'matched' => in_array($skill->name, $offerSkillNames, true)])
                    ->sortByDesc('matched')
                    ->all()),
                'score' => $row['match']->score,
                'is_interested' => $row['is_interested'],
                'is_complementary' => $row['is_complementary'],
            ])->values(),
        ]);
    }
}
