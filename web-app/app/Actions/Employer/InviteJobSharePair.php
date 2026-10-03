<?php

namespace App\Actions\Employer;

use App\Enums\CandidateDecisionType;
use App\Enums\InvitationStatus;
use App\Enums\JobSharePairStatus;
use App\Models\CandidateDecision;
use App\Models\Invitation;
use App\Models\JobSharePair;
use App\Models\User;
use App\Services\JobSharing\PairPresenter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Invites both members of a job-sharing pair; each of them answers her own invitation.
 */
class InviteJobSharePair
{
    public function __construct(private readonly PairPresenter $presenter) {}

    /**
     * @return Collection<int, Invitation>
     *
     * @throws ValidationException when any member already has an invitation to the offer
     */
    public function handle(JobSharePair $pair, User $sender, string $message): Collection
    {
        $offer = $pair->jobOffer;
        $members = $this->presenter->members($pair);

        if ($offer->invitations()->whereIn('candidate_profile_id', $members->modelKeys())->exists()) {
            throw ValidationException::withMessages(['message' => 'Jedna z osób z pary ma już zaproszenie do tej oferty.']);
        }

        return DB::transaction(function () use ($pair, $offer, $members, $sender, $message): Collection {
            $invitations = new Collection;

            foreach ($members as $member) {
                $invitations->push($offer->invitations()->create([
                    'candidate_profile_id' => $member->id,
                    'job_share_pair_id' => $pair->id,
                    'sent_by_user_id' => $sender->id,
                    'message' => $message,
                    'status' => InvitationStatus::Pending,
                ]));

                CandidateDecision::query()->updateOrCreate(
                    ['job_offer_id' => $offer->id, 'candidate_profile_id' => $member->id],
                    ['decision' => CandidateDecisionType::Invited],
                );
            }

            $pair->update(['status' => JobSharePairStatus::Invited]);

            return $invitations;
        });
    }
}
