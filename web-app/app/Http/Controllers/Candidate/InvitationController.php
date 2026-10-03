<?php

namespace App\Http\Controllers\Candidate;

use App\Enums\InvitationStatus;
use App\Http\Controllers\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Models\CandidateProfile;
use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    use ResolvesCandidateProfile;

    /**
     * Invitations received from companies, pending ones first.
     */
    public function index(Request $request): Response
    {
        $invitations = $this->candidateProfile($request)->invitations()
            ->with(['jobOffer.company.approvedReviews', 'conversation', 'jobSharePair.members.user'])
            ->orderByRaw('case when status = ? then 0 else 1 end', [InvitationStatus::Pending->value])
            ->latest()
            ->get();

        return Inertia::render('candidate/Invitations', [
            'invitations' => $invitations->map(fn (Invitation $invitation): array => [
                'id' => $invitation->id,
                'status' => $invitation->status->value,
                'kind' => $invitation->kind->value,
                'kind_label' => $invitation->kind->label(),
                'message' => $invitation->message,
                'created_at' => $invitation->created_at->toIso8601String(),
                'responded_at' => $invitation->responded_at?->toIso8601String(),
                'conversation_id' => $invitation->conversation?->id,
                'job_share_pair' => $invitation->jobSharePair ? [
                    'id' => $invitation->jobSharePair->id,
                    'status' => $invitation->jobSharePair->status->value,
                    'partner_name' => $invitation->jobSharePair->members
                        ->first(fn (CandidateProfile $member): bool => $member->id !== $invitation->candidate_profile_id)
                        ?->anonymousName(),
                ] : null,
                'offer' => [
                    'id' => $invitation->jobOffer->id,
                    'title' => $invitation->jobOffer->title,
                    'city' => $invitation->jobOffer->city,
                    'work_mode_label' => $invitation->jobOffer->work_mode->label(),
                    'employment_fraction_label' => $invitation->jobOffer->employment_fraction->label(),
                    'is_published' => $invitation->jobOffer->isPublished(),
                ],
                'company' => [
                    'name' => $invitation->jobOffer->company->name,
                    'verified' => $invitation->jobOffer->company->isVerified(),
                    'average_rating' => $invitation->jobOffer->company->averageRating(),
                    'reviews_count' => $invitation->jobOffer->company->approvedReviews->count(),
                ],
            ]),
        ]);
    }

    /**
     * Accept: opens the conversation and reveals the candidate's name and e-mail to the company.
     */
    public function accept(Invitation $invitation): RedirectResponse
    {
        Gate::authorize('respond', $invitation);

        $conversation = $invitation->accept();

        Inertia::flash('toast', ['type' => 'success', 'message' => $invitation->isDirectMessage()
            ? 'Rozmowa otwarta. Napisz firmie swoją odpowiedź.'
            : 'Zaproszenie przyjęte. Możecie już rozmawiać.']);

        return Route::has('conversations.show')
            ? to_route('conversations.show', $conversation)
            : redirect('/conversations/'.$conversation->id);
    }

    /**
     * Decline: the company is informed, the candidate stays anonymous.
     */
    public function decline(Invitation $invitation): RedirectResponse
    {
        Gate::authorize('respond', $invitation);

        $invitation->decline();

        Inertia::flash('toast', ['type' => 'info', 'message' => $invitation->isDirectMessage()
            ? 'Pytanie zignorowane. Firma nie pozna Twoich danych.'
            : 'Zaproszenie odrzucone. Firma nie pozna Twoich danych.']);

        return to_route('candidate.invitations.index');
    }
}
