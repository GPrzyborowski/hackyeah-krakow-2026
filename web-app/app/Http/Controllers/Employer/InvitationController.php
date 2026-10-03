<?php

namespace App\Http\Controllers\Employer;

use App\Actions\Employer\InviteCandidate;
use App\Enums\InvitationKind;
use App\Enums\InvitationStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Employer\Concerns\InteractsWithEmployerCompany;
use App\Http\Requests\Employer\StoreDirectMessageRequest;
use App\Http\Requests\Employer\StoreInvitationRequest;
use App\Http\Resources\RevealedCandidateResource;
use App\Models\CandidateProfile;
use App\Models\Invitation;
use App\Models\JobOffer;
use App\Services\Employer\CompanyInvitations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    use InteractsWithEmployerCompany;

    /**
     * Invitations sent by the company; contact data is revealed only for accepted ones.
     * Unanswered and declined invitations disappear once the candidate hides her profile from the company.
     */
    public function index(Request $request, CompanyInvitations $companyInvitations): Response
    {
        $company = $this->currentCompany($request);

        $invitations = $companyInvitations->query($company)
            ->latest()
            ->get()
            ->map(fn (Invitation $invitation): array => [
                'id' => $invitation->id,
                'status' => $invitation->status->value,
                'kind' => $invitation->kind->value,
                'kind_label' => $invitation->kind->label(),
                'message' => $invitation->message,
                'created_at' => $invitation->created_at->toIso8601String(),
                'responded_at' => $invitation->responded_at?->toIso8601String(),
                'offer' => ['id' => $invitation->jobOffer->id, 'title' => $invitation->jobOffer->title],
                'candidate' => $invitation->status === InvitationStatus::Accepted
                    ? (new RevealedCandidateResource($invitation->candidateProfile))->resolve($request)
                    : ['id' => $invitation->candidateProfile->id, 'anonymous_name' => $invitation->candidateProfile->anonymousName()],
                'conversation_url' => $invitation->status === InvitationStatus::Accepted && $invitation->conversation
                    ? route('conversations.show', $invitation->conversation)
                    : null,
            ]);

        return Inertia::render('employer/invitations/Index', [
            'invitations' => $invitations,
        ]);
    }

    /**
     * Invite a candidate to an offer, or turn an unanswered question into an invitation; the message is moderated by the form request.
     */
    public function store(StoreInvitationRequest $request, JobOffer $offer, CandidateProfile $candidate, InviteCandidate $inviteCandidate): RedirectResponse
    {
        Gate::authorize('reviewCandidates', $offer);

        $inviteCandidate->handle($offer, $candidate, $request->user(), $request->validated('message'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Zaproszenie wysłane. Dane kontaktowe zobaczysz po jego akceptacji.']);

        return $request->validated('from') === 'invitations'
            ? to_route('employer.invitations.index')
            : to_route('employer.candidates.index', ['offer' => $offer->id]);
    }

    /**
     * Send a direct question (no invitation) to a candidate who allows it; she stays anonymous until she answers.
     */
    public function storeDirectMessage(StoreDirectMessageRequest $request, JobOffer $offer, CandidateProfile $candidate, InviteCandidate $inviteCandidate): RedirectResponse
    {
        Gate::authorize('reviewCandidates', $offer);

        $inviteCandidate->handle($offer, $candidate, $request->user(), $request->validated('message'), InvitationKind::DirectMessage);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Wiadomość wysłana. Dane kontaktowe zobaczysz, gdy kandydatka odpowie.']);

        return to_route('employer.candidates.index', ['offer' => $offer->id]);
    }
}
