<?php

namespace App\Http\Controllers\Employer;

use App\Enums\CandidateDecisionType;
use App\Enums\JobSharePairStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Employer\Concerns\InteractsWithEmployerCompany;
use App\Http\Resources\AnonymousCandidateResource;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\JobOffer;
use App\Models\OfferInterest;
use App\Services\Matching\MatchResult;
use App\Services\Matching\MatchScorer;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CandidateController extends Controller
{
    use InteractsWithEmployerCompany;

    /**
     * Swipe view: one anonymous candidate at a time for the selected published offer.
     */
    public function index(Request $request, MatchScorer $scorer): Response
    {
        $company = $this->currentCompany($request);

        $publishedOffers = $company->jobOffers()->published()->with(['skills', 'company', 'invitations', 'decisions:id,job_offer_id,candidate_profile_id'])->latest('published_at')->get();

        $offer = $this->selectedOffer($request, $publishedOffers);

        if ($offer === null) {
            return Inertia::render('employer/candidates/Index', [
                'offers' => [],
                'currentOffer' => null,
                'candidate' => null,
                'remainingCount' => 0,
                'saved' => [],
                'invitationStats' => ['invited' => 0, 'responded' => 0],
            ]);
        }

        $interestedIds = array_values($offer->interests()->get(['candidate_profile_id'])->map(fn (OfferInterest $interest): int => $interest->candidate_profile_id)->all());
        $queue = $this->reviewQueue($offer, $scorer, $interestedIds);
        $savedCandidates = $this->savedCandidates($offer, $company, $scorer);

        $broughtBack = $savedCandidates->firstWhere('candidate.id', $request->integer('candidate'));
        $current = $broughtBack ?? $queue->first();

        return Inertia::render('employer/candidates/Index', [
            'offers' => $publishedOffers->map(fn (JobOffer $publishedOffer): array => [
                'id' => $publishedOffer->id,
                'title' => $publishedOffer->title,
                ...$this->offerStatistics($publishedOffer, $scorer),
            ])->values(),
            'currentOffer' => [
                'id' => $offer->id,
                'title' => $offer->title,
                'city' => $offer->city,
                'start_date' => $offer->start_date->toDateString(),
                'salary_min' => $offer->salary_min,
                'salary_max' => $offer->salary_max,
                'employment_fraction_label' => $offer->employment_fraction->label(),
                'work_mode_label' => $offer->work_mode->label(),
                'flexible_hours' => $offer->flexible_hours,
                'fixed_meeting_hours' => $offer->fixed_meeting_hours,
                'is_job_share' => $offer->is_job_share,
                'submitted_pairs_count' => $offer->is_job_share
                    ? $offer->jobSharePairs()->where('status', JobSharePairStatus::Submitted)->visibleToCompany($company)->count()
                    : 0,
            ],
            'candidate' => $current
                ? (new AnonymousCandidateResource($current['candidate'], $current['match'], in_array($current['candidate']->id, $interestedIds, true)))->resolve($request)
                : null,
            'isSavedCandidate' => $broughtBack !== null,
            'remainingCount' => $queue->count(),
            'saved' => $savedCandidates->map(fn (array $row): array => [
                'id' => $row['candidate']->id,
                'anonymous_name' => $row['candidate']->anonymousName(),
                'initial' => mb_strtoupper(mb_substr($row['candidate']->anonymousName(), 0, 1)),
                'available_from' => $row['candidate']->available_from?->toDateString(),
                'score' => $row['match']->score,
            ])->values(),
            'invitationStats' => [
                'invited' => $offer->invitations->count(),
                'responded' => $offer->invitations->whereNotNull('responded_at')->count(),
            ],
        ]);
    }

    /**
     * @param  Collection<int, JobOffer>  $publishedOffers
     */
    private function selectedOffer(Request $request, Collection $publishedOffers): ?JobOffer
    {
        if (! $request->filled('offer')) {
            return $publishedOffers->first();
        }

        $offer = $publishedOffers->firstWhere('id', $request->integer('offer'));

        if ($offer === null) {
            $requested = JobOffer::query()->findOrFail($request->integer('offer'));
            Gate::authorize('reviewCandidates', $requested);
        }

        return $offer;
    }

    /**
     * Undecided matching candidates; those who expressed interest in the offer come first.
     *
     * @param  list<int>  $interestedIds
     * @return Collection<int, array{candidate: CandidateProfile, match: MatchResult}>
     */
    private function reviewQueue(JobOffer $offer, MatchScorer $scorer, array $interestedIds): Collection
    {
        [$interested, $others] = $scorer->rankCandidatesFor($offer, $this->reviewedCandidateIds($offer))
            ->partition(fn (array $row): bool => in_array($row['candidate']->id, $interestedIds, true));

        return $interested->concat($others)->values();
    }

    /**
     * @return Collection<int, array{candidate: CandidateProfile, match: MatchResult}>
     */
    private function savedCandidates(JobOffer $offer, Company $company, MatchScorer $scorer): Collection
    {
        $savedIds = $offer->decisions()->where('decision', CandidateDecisionType::Saved)->pluck('candidate_profile_id');

        return CandidateProfile::query()
            ->visibleTo($company)
            ->whereKey($savedIds)
            ->with(['user', 'confirmedSkills'])
            ->get()
            ->map(fn (CandidateProfile $candidate): array => ['candidate' => $candidate, 'match' => $scorer->score($candidate, $offer)])
            ->sortByDesc(fn (array $row): int => $row['match']->score)
            ->values();
    }
}
