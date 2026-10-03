<?php

namespace App\Actions\Employer;

use App\Enums\CandidateDecisionType;
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
 * Invites a matched candidate to an offer (the message is moderated by the form request beforehand).
 */
class InviteCandidate
{
    public function __construct(private readonly MatchScorer $scorer) {}

    /**
     * @throws ValidationException when the candidate already has an invitation to the offer
     */
    public function handle(JobOffer $offer, CandidateProfile $candidate, User $sender, string $message): Invitation
    {
        abort_unless($this->scorer->matchingCandidates($offer)->whereKey($candidate->id)->exists(), 404);

        if ($offer->invitations()->where('candidate_profile_id', $candidate->id)->exists()) {
            throw ValidationException::withMessages(['message' => 'Ta kandydatka ma już zaproszenie do tej oferty.']);
        }

        return DB::transaction(function () use ($offer, $candidate, $sender, $message): Invitation {
            $invitation = $offer->invitations()->create([
                'candidate_profile_id' => $candidate->id,
                'sent_by_user_id' => $sender->id,
                'message' => $message,
                'status' => InvitationStatus::Pending,
            ]);

            CandidateDecision::query()->updateOrCreate(
                ['job_offer_id' => $offer->id, 'candidate_profile_id' => $candidate->id],
                ['decision' => CandidateDecisionType::Invited],
            );

            return $invitation;
        });
    }
}
