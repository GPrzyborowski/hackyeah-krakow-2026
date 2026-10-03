<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CandidateProfile;
use App\Models\JobSharePair;
use App\Services\JobSharing\PairPresenter;
use App\Services\JobSharing\Workday;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * A job-sharing pair as seen by one of its members: offer, both members (anonymous: first name + surname initial),
 * the day split and what the viewer may do. Messages come from the pair messages endpoint.
 *
 * @property JobSharePair $resource
 */
class PairResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pair = $this->resource;
        $presenter = app(PairPresenter::class);
        $pair->loadMissing('jobOffer.company');
        $members = $presenter->members($pair);
        $offer = $pair->jobOffer;
        $workday = Workday::forOffer($offer);
        $viewerProfileId = $request->user()?->candidateProfile?->id;

        return [
            'id' => $pair->id,
            'status' => $pair->status->value,
            'status_label' => $pair->status->label(),
            'submitted_at' => $pair->submitted_at?->toIso8601String(),
            'has_saved_schedule' => $pair->proposed_schedule !== null,
            'offer' => [
                'id' => $offer->id,
                'title' => $offer->title,
                'company' => $offer->company->name,
                'city' => $offer->city,
                'work_mode_label' => $offer->work_mode->label(),
                'employment_fraction_label' => $offer->employment_fraction->label(),
                'is_published' => $offer->isPublished(),
                'workday_starts_at' => Workday::format($workday->startsAt),
                'workday_ends_at' => Workday::format($workday->endsAt),
            ],
            'members' => $members->map(fn (CandidateProfile $member): array => [
                'id' => $member->id,
                'first_name' => $presenter->firstName($member),
                'display_name' => $member->anonymousName(),
                'headline' => $member->headline,
                'preferred_day_part' => $member->preferred_day_part?->value,
                'preferred_day_part_label' => $member->preferred_day_part?->label(),
                'is_me' => $member->id === $viewerProfileId,
                'is_initiator' => $presenter->isInitiator($member),
                'has_accepted' => $presenter->hasAccepted($member),
                'has_confirmed_schedule' => $presenter->hasConfirmedSchedule($member),
            ])->values()->all(),
            'schedule' => $presenter->schedule($pair, $members),
            'can' => [
                'respond' => Gate::allows('respond', $pair),
                'chat' => Gate::allows('chat', $pair),
                'plan_schedule' => Gate::allows('planSchedule', $pair),
                'cancel' => Gate::allows('cancel', $pair),
            ],
        ];
    }
}
