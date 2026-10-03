<?php

namespace App\Http\Controllers\JobSharing;

use App\Enums\CandidateDecisionType;
use App\Enums\InvitationStatus;
use App\Enums\JobSharePairStatus;
use App\Enums\SkillImportance;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Employer\Concerns\InteractsWithEmployerCompany;
use App\Http\Requests\Employer\StoreInvitationRequest;
use App\Http\Resources\AnonymousCandidateResource;
use App\Models\CandidateDecision;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\JobOffer;
use App\Models\JobSharePair;
use App\Models\Skill;
use App\Services\JobSharing\PairPresenter;
use App\Services\JobSharing\Workday;
use App\Services\Matching\MatchScorer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EmployerPairController extends Controller
{
    use InteractsWithEmployerCompany;

    /**
     * Statuses of pairs the employer can see: sent to the company and already decided.
     */
    private const array VISIBLE_STATUSES = [
        JobSharePairStatus::Submitted,
        JobSharePairStatus::Invited,
        JobSharePairStatus::Rejected,
    ];

    public function __construct(private readonly PairPresenter $presenter) {}

    /**
     * Pairs that applied together for a job-sharing offer, shown anonymously.
     */
    public function index(Request $request, JobOffer $offer, MatchScorer $scorer): Response
    {
        Gate::authorize('reviewCandidates', $offer);
        abort_unless($offer->is_job_share, 404);

        $company = $this->currentCompany($request);
        $offer->load(['skills', 'company']);
        $workday = Workday::forOffer($offer);
        $requiredSkillNames = array_values($offer->skills
            ->filter(fn (Skill $skill): bool => $skill->getRelationValue('pivot')?->getAttribute('importance') === SkillImportance::Required->value)
            ->map(fn (Skill $skill): string => $skill->name)
            ->all());

        $pairs = $offer->jobSharePairs()
            ->whereIn('status', self::VISIBLE_STATUSES)
            ->visibleToCompany($company)
            ->with(['members.user', 'members.confirmedSkills'])
            ->orderByRaw('case when status = ? then 0 else 1 end', [JobSharePairStatus::Submitted->value])
            ->latest('submitted_at')
            ->get()
            ->map(function (JobSharePair $pair) use ($offer, $scorer, $request, $requiredSkillNames): array {
                $pair->setRelation('jobOffer', $offer);
                $members = $this->presenter->members($pair);
                $skillNames = $members->flatMap(fn (CandidateProfile $member) => $member->confirmedSkills->pluck('name'))->unique();
                $covered = array_values(array_filter($requiredSkillNames, fn (string $name): bool => $skillNames->contains($name)));

                return [
                    'id' => $pair->id,
                    'status' => $pair->status->value,
                    'submitted_at' => $pair->submitted_at?->toIso8601String(),
                    'members' => $members->map(fn (CandidateProfile $member): array => (new AnonymousCandidateResource($member, $scorer->score($member, $offer)))->resolve($request))->values(),
                    'coverage' => [
                        'covered' => $covered,
                        'missing' => array_values(array_diff($requiredSkillNames, $covered)),
                        'percent' => $requiredSkillNames === [] ? 100 : (int) round(count($covered) / count($requiredSkillNames) * 100),
                    ],
                    'schedule' => $this->presenter->schedule($pair, $members),
                ];
            });

        return Inertia::render('job-sharing/EmployerPairs', [
            'offer' => [
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
                'workday_starts_at' => Workday::format($workday->startsAt),
                'workday_ends_at' => Workday::format($workday->endsAt),
            ],
            'pairs' => $pairs->values(),
        ]);
    }

    /**
     * Invite both members of the pair; each of them answers her own invitation.
     */
    public function invite(StoreInvitationRequest $request, JobSharePair $pair): RedirectResponse
    {
        Gate::authorize('review', $pair);

        $company = $this->currentCompany($request);
        abort_unless($this->isVisibleTo($pair, $company), 404);

        $offer = $pair->jobOffer;
        $members = $this->presenter->members($pair);

        if ($offer->invitations()->whereIn('candidate_profile_id', $members->modelKeys())->exists()) {
            throw ValidationException::withMessages(['message' => 'Jedna z osób z pary ma już zaproszenie do tej oferty.']);
        }

        DB::transaction(function () use ($request, $pair, $offer, $members): void {
            foreach ($members as $member) {
                $offer->invitations()->create([
                    'candidate_profile_id' => $member->id,
                    'job_share_pair_id' => $pair->id,
                    'sent_by_user_id' => $request->user()?->id,
                    'message' => $request->validated('message'),
                    'status' => InvitationStatus::Pending,
                ]);

                CandidateDecision::query()->updateOrCreate(
                    ['job_offer_id' => $offer->id, 'candidate_profile_id' => $member->id],
                    ['decision' => CandidateDecisionType::Invited],
                );
            }

            $pair->update(['status' => JobSharePairStatus::Invited]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Zaproszenie wysłane do obu osób. Dane kontaktowe zobaczysz po akceptacji.']);

        return to_route('employer.offers.job-share-pairs.index', $offer);
    }

    public function reject(Request $request, JobSharePair $pair): RedirectResponse
    {
        Gate::authorize('review', $pair);
        abort_unless($this->isVisibleTo($pair, $this->currentCompany($request)), 404);

        $pair->update(['status' => JobSharePairStatus::Rejected]);

        Inertia::flash('toast', ['type' => 'info', 'message' => 'Para odrzucona.']);

        return to_route('employer.offers.job-share-pairs.index', $pair->job_offer_id);
    }

    /**
     * A pair is hidden when any of its members hid her profile from the company or unpublished it.
     */
    private function isVisibleTo(JobSharePair $pair, Company $company): bool
    {
        return JobSharePair::query()->visibleToCompany($company)->whereKey($pair->id)->exists();
    }
}
