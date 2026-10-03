<?php

namespace App\Http\Controllers\JobSharing;

use App\Actions\Employer\InviteJobSharePair;
use App\Enums\JobSharePairStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Employer\Concerns\InteractsWithEmployerCompany;
use App\Http\Requests\Employer\StoreInvitationRequest;
use App\Http\Resources\AnonymousCandidateResource;
use App\Models\JobOffer;
use App\Models\JobSharePair;
use App\Services\Employer\JobSharePairReview;
use App\Services\JobSharing\Workday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EmployerPairController extends Controller
{
    use InteractsWithEmployerCompany;

    public function __construct(private readonly JobSharePairReview $pairReview) {}

    /**
     * Pairs that applied together for a job-sharing offer, shown anonymously.
     */
    public function index(Request $request, JobOffer $offer): Response
    {
        Gate::authorize('reviewCandidates', $offer);
        abort_unless($offer->is_job_share, 404);

        $company = $this->currentCompany($request);
        $offer->load(['skills', 'company']);
        $workday = Workday::forOffer($offer);

        $pairs = $this->pairReview->rows($offer, $company)->map(fn (array $row): array => [
            'id' => $row['pair']->id,
            'status' => $row['pair']->status->value,
            'submitted_at' => $row['pair']->submitted_at?->toIso8601String(),
            'members' => $row['members']->map(fn (array $member): array => (new AnonymousCandidateResource($member['candidate'], $member['match']))->resolve($request))->values(),
            'coverage' => $row['coverage'],
            'schedule' => $row['schedule'],
        ]);

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
    public function invite(StoreInvitationRequest $request, JobSharePair $pair, InviteJobSharePair $inviteJobSharePair): RedirectResponse
    {
        Gate::authorize('review', $pair);

        $company = $this->currentCompany($request);
        abort_unless($this->pairReview->isVisibleTo($pair, $company), 404);

        $inviteJobSharePair->handle($pair, $request->user(), $request->validated('message'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Zaproszenie wysłane do obu osób. Dane kontaktowe zobaczysz po akceptacji.']);

        return to_route('employer.offers.job-share-pairs.index', $pair->job_offer_id);
    }

    public function reject(Request $request, JobSharePair $pair): RedirectResponse
    {
        Gate::authorize('review', $pair);
        abort_unless($this->pairReview->isVisibleTo($pair, $this->currentCompany($request)), 404);

        $pair->update(['status' => JobSharePairStatus::Rejected]);

        Inertia::flash('toast', ['type' => 'info', 'message' => 'Para odrzucona.']);

        return to_route('employer.offers.job-share-pairs.index', $pair->job_offer_id);
    }
}
