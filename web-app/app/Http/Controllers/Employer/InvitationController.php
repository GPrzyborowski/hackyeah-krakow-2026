<?php

namespace App\Http\Controllers\Employer;

use App\Enums\CandidateDecisionType;
use App\Enums\InvitationStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Employer\Concerns\InteractsWithEmployerCompany;
use App\Http\Requests\Employer\StoreInvitationRequest;
use App\Http\Resources\RevealedCandidateResource;
use App\Models\CandidateDecision;
use App\Models\CandidateProfile;
use App\Models\Invitation;
use App\Models\JobOffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    use InteractsWithEmployerCompany;

    /**
     * Invitations sent by the company; contact data is revealed only for accepted ones.
     */
    public function index(Request $request): Response
    {
        $company = $this->currentCompany($request);

        $invitations = Invitation::query()
            ->whereRelation('jobOffer', 'company_id', $company->id)
            ->with(['jobOffer', 'candidateProfile.user', 'conversation'])
            ->latest()
            ->get()
            ->map(fn (Invitation $invitation): array => [
                'id' => $invitation->id,
                'status' => $invitation->status->value,
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
     * Invite a candidate to an offer; the message is moderated by the form request.
     */
    public function store(StoreInvitationRequest $request, JobOffer $offer, CandidateProfile $candidate): RedirectResponse
    {
        Gate::authorize('reviewCandidates', $offer);

        abort_unless(CandidateProfile::query()->visibleTo($this->currentCompany($request))->whereKey($candidate->id)->exists(), 404);

        if ($offer->invitations()->where('candidate_profile_id', $candidate->id)->exists()) {
            throw ValidationException::withMessages(['message' => 'Ta kandydatka ma już zaproszenie do tej oferty.']);
        }

        DB::transaction(function () use ($request, $offer, $candidate): void {
            $offer->invitations()->create([
                'candidate_profile_id' => $candidate->id,
                'sent_by_user_id' => $request->user()->id,
                'message' => $request->validated('message'),
                'status' => InvitationStatus::Pending,
            ]);

            CandidateDecision::query()->updateOrCreate(
                ['job_offer_id' => $offer->id, 'candidate_profile_id' => $candidate->id],
                ['decision' => CandidateDecisionType::Invited],
            );
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Zaproszenie wysłane. Dane kontaktowe zobaczysz po jego akceptacji.']);

        return to_route('employer.candidates.index', ['offer' => $offer->id]);
    }
}
