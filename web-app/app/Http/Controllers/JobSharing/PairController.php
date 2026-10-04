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
use App\Services\JobSharing\PairJoinLinks;
use App\Services\JobSharing\PairLifecycle;
use App\Services\JobSharing\PairPresenter;
use App\Services\JobSharing\PartnerFinder;
use App\Services\JobSharing\Workday;
use App\Services\Matching\MatchScorer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
                    'active_pair_state' => $activePair ? $this->presenter->viewerState($activePair) : null,
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
    public function show(Request $request, JobSharePair $pair, PairJoinLinks $joinLinks): Response
    {
        Gate::authorize('view', $pair);

        $profile = $this->candidateProfile($request);
        $pair->load(['jobOffer.company', 'members.user']);
        $members = $this->presenter->members($pair);
        $workday = Workday::forOffer($pair->jobOffer);
        $isAccepted = $this->isAcceptedMember($pair, $profile);
        $isWaitingForPartner = $isAccepted && $members->count() < JobSharePair::MAX_MEMBERS && $pair->status === JobSharePairStatus::Forming;
        $joinLink = $isWaitingForPartner ? $joinLinks->usableLinkFor($pair, $profile) : null;

        $messages = $isAccepted
            ? $pair->messages()->with('author:id,name')->oldest('id')->get()
            : collect();

        return Inertia::render('job-sharing/Pair', [
            'pair' => [
                'id' => $pair->id,
                'status' => $pair->status->value,
                'submitted_at' => $pair->submitted_at?->toIso8601String(),
                'has_saved_schedule' => $pair->proposed_schedule !== null,
                'is_waiting_for_partner' => $isWaitingForPartner,
            ],
            'joinLink' => $joinLink !== null ? $joinLinks->presentLink($joinLink) : null,
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
                'send_message' => Gate::allows('sendMessage', $pair),
                'plan_schedule' => Gate::allows('planSchedule', $pair),
                'cancel' => Gate::allows('cancel', $pair),
            ],
        ]);
    }

    /**
     * Invite another candidate to apply together for a job-sharing offer.
     */
    public function store(StorePairRequest $request, JobOffer $offer, PairLifecycle $lifecycle): RedirectResponse
    {
        abort_unless($offer->is_job_share && $offer->isPublished(), 404);

        $pair = $lifecycle->invite($this->candidateProfile($request), $offer, $request->integer('partner_id'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Zaproszenie do pary wysłane. Możecie już pisać na czacie pary.']);

        return to_route('job-sharing.pairs.show', $pair);
    }

    /**
     * The invited partner joins the pair.
     */
    public function accept(Request $request, JobSharePair $pair, PairLifecycle $lifecycle): RedirectResponse
    {
        Gate::authorize('respond', $pair);

        $lifecycle->accept($this->candidateProfile($request), $pair);

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
