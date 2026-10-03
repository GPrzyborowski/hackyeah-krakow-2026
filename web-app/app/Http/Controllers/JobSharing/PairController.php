<?php

namespace App\Http\Controllers\JobSharing;

use App\Enums\JobSharePairStatus;
use App\Http\Controllers\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\JobSharing\StorePairRequest;
use App\Models\CandidateProfile;
use App\Models\JobOffer;
use App\Models\JobShareMessage;
use App\Models\JobSharePair;
use App\Notifications\PairInvitationAccepted;
use App\Notifications\PairInvitationReceived;
use App\Services\JobSharing\PairPresenter;
use App\Services\JobSharing\PartnerFinder;
use App\Services\JobSharing\Workday;
use App\Services\Matching\MatchScorer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PairController extends Controller
{
    use ResolvesCandidateProfile;

    public function __construct(private readonly PairPresenter $presenter) {}

    /**
     * Job-sharing hub: pair invitations waiting for an answer, the candidate's pairs and job-sharing offers.
     */
    public function index(Request $request, MatchScorer $scorer, PartnerFinder $finder): Response
    {
        $profile = $this->candidateProfile($request);

        $pairs = $profile->jobSharePairs()
            ->with(['jobOffer.company', 'members.user'])
            ->where('status', '!=', JobSharePairStatus::Cancelled)
            ->latest('job_share_pairs.updated_at')
            ->get();

        [$invitations, $ownPairs] = $pairs->partition(
            fn (JobSharePair $pair): bool => $pair->status === JobSharePairStatus::Forming && ! $this->isAcceptedMember($pair, $profile),
        );

        $offers = $scorer->rankOffersFor($profile, JobOffer::query()->where('is_job_share', true))
            ->map(function (array $row) use ($profile, $finder): array {
                $offer = $row['offer'];
                $activePair = $finder->activePairFor($profile, $offer);

                return [
                    'id' => $offer->id,
                    'title' => $offer->title,
                    'company' => $offer->company->name,
                    'city' => $offer->city,
                    'work_mode_label' => $offer->work_mode->label(),
                    'score' => $row['match']->score,
                    'job_share' => Workday::presentOffer($offer),
                    'active_pair_id' => $activePair?->id,
                    'active_pair_state' => $activePair ? $this->viewerPairState($activePair) : null,
                ];
            });

        return Inertia::render('job-sharing/Index', [
            'isOpenToJobSharing' => $profile->open_to_job_sharing,
            'invitations' => $invitations->map(fn (JobSharePair $pair): array => $this->presentListItem($pair, $profile))->values(),
            'pairs' => $ownPairs->map(fn (JobSharePair $pair): array => $this->presentListItem($pair, $profile))->values(),
            'offers' => $offers->values(),
        ]);
    }

    /**
     * Pair page: partner, offer, private chat and the split of the workday.
     */
    public function show(Request $request, JobSharePair $pair): Response
    {
        Gate::authorize('view', $pair);

        $profile = $this->candidateProfile($request);
        $pair->load(['jobOffer.company', 'members.user']);
        $members = $this->presenter->members($pair);
        $workday = Workday::forOffer($pair->jobOffer);
        $isAccepted = $this->isAcceptedMember($pair, $profile);

        $messages = $isAccepted
            ? $pair->messages()->with('author:id,name')->oldest('id')->get()
            : collect();

        return Inertia::render('job-sharing/Pair', [
            'pair' => [
                'id' => $pair->id,
                'status' => $pair->status->value,
                'submitted_at' => $pair->submitted_at?->toIso8601String(),
                'has_saved_schedule' => $pair->proposed_schedule !== null,
            ],
            'offer' => [
                'id' => $pair->jobOffer->id,
                'title' => $pair->jobOffer->title,
                'company' => $pair->jobOffer->company->name,
                'city' => $pair->jobOffer->city,
                'work_mode_label' => $pair->jobOffer->work_mode->label(),
                'employment_fraction_label' => $pair->jobOffer->employment_fraction->label(),
                'is_published' => $pair->jobOffer->isPublished(),
                'workday_starts_at' => Workday::format($workday->startsAt),
                'workday_ends_at' => Workday::format($workday->endsAt),
            ],
            'members' => $members->map(fn (CandidateProfile $member): array => [
                'id' => $member->id,
                'first_name' => $this->presenter->firstName($member),
                'display_name' => $member->anonymousName(),
                'headline' => $member->headline,
                'preferred_day_part_label' => $member->preferred_day_part?->label(),
                'is_me' => $member->id === $profile->id,
                'is_initiator' => $this->presenter->isInitiator($member),
                'has_accepted' => $this->presenter->hasAccepted($member),
                'has_confirmed_schedule' => $this->presenter->hasConfirmedSchedule($member),
            ])->values(),
            'schedule' => $this->presenter->schedule($pair, $members),
            'messages' => $messages->map(fn (JobShareMessage $message): array => [
                'id' => $message->id,
                'body' => $message->body,
                'author_name' => $this->firstNameOf($message->author->name),
                'is_mine' => $message->user_id === $request->user()?->id,
                'created_at' => $message->created_at->toIso8601String(),
            ])->values(),
            'can' => [
                'respond' => Gate::allows('respond', $pair),
                'chat' => Gate::allows('chat', $pair),
                'plan_schedule' => Gate::allows('planSchedule', $pair),
                'cancel' => Gate::allows('cancel', $pair),
            ],
        ]);
    }

    /**
     * Invite another candidate to apply together for a job-sharing offer.
     */
    public function store(StorePairRequest $request, JobOffer $offer, PartnerFinder $finder): RedirectResponse
    {
        abort_unless($offer->is_job_share && $offer->isPublished(), 404);

        $profile = $this->candidateProfile($request);

        if (! $profile->isPublished()) {
            throw ValidationException::withMessages(['partner_id' => 'Najpierw opublikuj swój profil, aby zaprosić kogoś do pary.']);
        }

        if ($finder->activePairFor($profile, $offer) !== null) {
            throw ValidationException::withMessages(['partner_id' => 'Masz już parę do tej oferty.']);
        }

        $partner = CandidateProfile::query()->findOrFail($request->integer('partner_id'));

        if (! $finder->isPossiblePartner($profile, $offer, $partner)) {
            throw ValidationException::withMessages(['partner_id' => 'Ta osoba nie może już dołączyć do pary w tej ofercie.']);
        }

        $pair = DB::transaction(function () use ($offer, $profile, $partner): JobSharePair {
            $pair = $offer->jobSharePairs()->create(['status' => JobSharePairStatus::Forming]);

            $pair->members()->attach([
                $profile->id => ['is_initiator' => true, 'accepted_at' => now()],
                $partner->id => ['is_initiator' => false, 'accepted_at' => null],
            ]);

            return $pair;
        });

        $partner->user->notify(new PairInvitationReceived($pair, $profile));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Zaproszenie do pary wysłane. Możecie już pisać na czacie pary.']);

        return to_route('job-sharing.pairs.show', $pair);
    }

    /**
     * The invited partner joins the pair.
     */
    public function accept(Request $request, JobSharePair $pair): RedirectResponse
    {
        Gate::authorize('respond', $pair);

        $profile = $this->candidateProfile($request);

        $hasOtherPair = $profile->jobSharePairs()
            ->where('job_offer_id', $pair->job_offer_id)
            ->whereIn('status', PartnerFinder::ACTIVE_STATUSES)
            ->whereKeyNot($pair->id)
            ->wherePivotNotNull('accepted_at')
            ->exists();

        if ($hasOtherPair || $pair->acceptedMembers()->count() >= JobSharePair::MAX_MEMBERS) {
            throw ValidationException::withMessages(['pair' => 'Masz już parę do tej oferty albo para jest pełna.']);
        }

        DB::transaction(function () use ($pair, $profile): void {
            $pair->members()->updateExistingPivot($profile->id, ['accepted_at' => now()]);
            $pair->update(['status' => JobSharePairStatus::Formed]);
        });

        $this->presenter->members($pair)
            ->first(fn (CandidateProfile $member): bool => $this->presenter->isInitiator($member))
            ?->user
            ->notify(new PairInvitationAccepted($pair, $profile));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Jesteście parą! Ustalcie podział dnia.']);

        return to_route('job-sharing.pairs.show', $pair);
    }

    public function decline(JobSharePair $pair): RedirectResponse
    {
        Gate::authorize('respond', $pair);

        $pair->update(['status' => JobSharePairStatus::Cancelled]);

        Inertia::flash('toast', ['type' => 'info', 'message' => 'Zaproszenie do pary odrzucone.']);

        return to_route('job-sharing.index');
    }

    /**
     * Leave a pair that has not been sent to the employer yet.
     */
    public function cancel(JobSharePair $pair): RedirectResponse
    {
        Gate::authorize('cancel', $pair);

        $pair->update(['status' => JobSharePairStatus::Cancelled]);

        Inertia::flash('toast', ['type' => 'info', 'message' => 'Para została rozwiązana.']);

        return to_route('job-sharing.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function presentListItem(JobSharePair $pair, CandidateProfile $profile): array
    {
        $partner = $this->presenter->members($pair)->first(fn (CandidateProfile $member): bool => $member->id !== $profile->id);

        return [
            'id' => $pair->id,
            'status' => $pair->status->value,
            'offer' => [
                'id' => $pair->jobOffer->id,
                'title' => $pair->jobOffer->title,
                'company' => $pair->jobOffer->company->name,
                'job_share' => Workday::presentOffer($pair->jobOffer),
            ],
            'partner' => $partner ? [
                'display_name' => $partner->anonymousName(),
                'headline' => $partner->headline,
                'preferred_day_part_label' => $partner->preferred_day_part?->label(),
            ] : null,
        ];
    }

    /**
     * How the signed-in candidate relates to her active pair, loaded through her own pairs relation (pivot = her membership).
     *
     * @return 'pair'|'invite_sent'|'invite_received'
     */
    private function viewerPairState(JobSharePair $pair): string
    {
        if ($pair->status !== JobSharePairStatus::Forming) {
            return 'pair';
        }

        return $pair->getRelationValue('pivot')?->getAttribute('accepted_at') !== null ? 'invite_sent' : 'invite_received';
    }

    private function isAcceptedMember(JobSharePair $pair, CandidateProfile $profile): bool
    {
        $member = $pair->relationLoaded('members')
            ? $pair->members->firstWhere('id', $profile->id)
            : null;

        return $member !== null ? $this->presenter->hasAccepted($member) : $pair->hasAcceptedMember($profile);
    }

    private function firstNameOf(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return $parts[0] ?? $name;
    }
}
