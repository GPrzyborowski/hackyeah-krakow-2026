<?php

namespace App\Actions\Employer;

use App\Enums\CandidateDecisionType;
use App\Enums\InvitationKind;
use App\Enums\InvitationStatus;
use App\Models\CandidateDecision;
use App\Models\CandidateProfile;
use App\Models\Invitation;
use App\Models\JobOffer;
use App\Models\User;
use App\Services\Matching\MatchScorer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Invites a matched candidate to an offer, or sends her a direct question (the message is moderated by the form request beforehand).
 * A direct message is an invitation variant: the candidate stays anonymous until she answers (accepts) it,
 * and it is recorded as the "invited" decision so she leaves the swipe queue, just like after an invitation.
 */
class InviteCandidate
{
    public function __construct(private readonly MatchScorer $scorer) {}

    /**
     * @throws ValidationException when the candidate already has an invitation to the offer or does not accept direct messages
     */
    public function handle(JobOffer $offer, CandidateProfile $candidate, User $sender, string $message, InvitationKind $kind = InvitationKind::Invitation): Invitation
    {
        abort_unless($this->scorer->matchingCandidates($offer)->whereKey($candidate->id)->exists(), 404);

        if ($kind === InvitationKind::DirectMessage && ! $candidate->allow_direct_messages) {
            throw ValidationException::withMessages(['message' => 'Ta kandydatka nie przyjmuje wiadomości bez zaproszenia. Możesz ją zaprosić do rozmowy.']);
        }

        if ($offer->invitations()->where('candidate_profile_id', $candidate->id)->exists()) {
            throw ValidationException::withMessages(['message' => 'Ta kandydatka ma już zaproszenie do tej oferty.']);
        }

        return DB::transaction(function () use ($offer, $candidate, $sender, $message, $kind): Invitation {
            $invitation = $offer->invitations()->create([
                'candidate_profile_id' => $candidate->id,
                'sent_by_user_id' => $sender->id,
                'message' => $message,
                'status' => InvitationStatus::Pending,
                'kind' => $kind,
            ]);

            CandidateDecision::query()->updateOrCreate(
                ['job_offer_id' => $offer->id, 'candidate_profile_id' => $candidate->id],
                ['decision' => CandidateDecisionType::Invited],
            );

            return $invitation;
        });
    }
}
