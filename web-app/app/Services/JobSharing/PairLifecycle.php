<?php

namespace App\Services\JobSharing;

use App\Enums\JobSharePairStatus;
use App\Models\CandidateProfile;
use App\Models\JobOffer;
use App\Models\JobSharePair;
use App\Models\JobSharePairInvitation;
use App\Notifications\PairInvitationAccepted;
use App\Notifications\PairInvitationReceived;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * State changes of a job-sharing pair shared by the web app and the mobile API: inviting a partner, joining,
 * planning the day split and sending the pair to the employer. Authorization (JobSharePairPolicy) happens before.
 */
class PairLifecycle
{
    public function __construct(
        private readonly PartnerFinder $finder,
        private readonly PairPresenter $presenter,
        private readonly ScheduleValidator $scheduleValidator,
        private readonly PairJoinLinks $joinLinks,
    ) {}

    /**
     * Invite another candidate to apply together; the invited partner is notified (mail + bell).
     *
     * @throws ValidationException
     */
    public function invite(CandidateProfile $profile, JobOffer $offer, int $partnerId): JobSharePair
    {
        if (! $profile->isPublished()) {
            throw ValidationException::withMessages(['partner_id' => 'Najpierw opublikuj swój profil, aby zaprosić kogoś do pary.']);
        }

        $waitingPair = $this->joinLinks->waitingPairFor($profile, $offer);

        if ($waitingPair === null && $this->finder->activePairFor($profile, $offer) !== null) {
            throw ValidationException::withMessages(['partner_id' => 'Masz już parę do tej oferty.']);
        }

        $partner = CandidateProfile::query()->findOrFail($partnerId);

        if (! $this->finder->isPossiblePartner($profile, $offer, $partner)) {
            throw ValidationException::withMessages(['partner_id' => 'Ta osoba nie może już dołączyć do pary w tej ofercie.']);
        }

        $pair = DB::transaction(function () use ($offer, $profile, $partner, $waitingPair): JobSharePair {
            if ($waitingPair !== null) {
                $waitingPair->members()->attach($partner->id, ['is_initiator' => false, 'accepted_at' => null]);

                return $waitingPair;
            }

            $pair = $offer->jobSharePairs()->create(['status' => JobSharePairStatus::Forming]);

            $pair->members()->attach([
                $profile->id => ['is_initiator' => true, 'accepted_at' => now()],
                $partner->id => ['is_initiator' => false, 'accepted_at' => null],
            ]);

            return $pair;
        });

        $partner->user->notify(new PairInvitationReceived($pair, $profile));

        return $pair;
    }

    /**
     * A link the candidate shares with a friend to join her pair for the offer. Starts the pair (forming, with her
     * as the only member) when she has none yet and reuses a link that still works instead of creating another one.
     *
     * @throws ValidationException
     */
    public function createJoinLink(CandidateProfile $profile, JobOffer $offer): JobSharePairInvitation
    {
        if (! $profile->isPublished()) {
            throw ValidationException::withMessages(['join_link' => 'Najpierw opublikuj swój profil, aby zaprosić kogoś do pary.']);
        }

        $waitingPair = $this->joinLinks->waitingPairFor($profile, $offer);

        if ($waitingPair === null && $this->finder->activePairFor($profile, $offer) !== null) {
            throw ValidationException::withMessages(['join_link' => 'Masz już parę do tej oferty.']);
        }

        return DB::transaction(function () use ($profile, $offer, $waitingPair): JobSharePairInvitation {
            $pair = $waitingPair;

            if ($pair === null) {
                $pair = $offer->jobSharePairs()->create(['status' => JobSharePairStatus::Forming]);
                $pair->members()->attach($profile->id, ['is_initiator' => true, 'accepted_at' => now()]);
            }

            $usableLink = $pair->joinLinks()->usable()->latest('id')->first();

            return $usableLink ?? $pair->joinLinks()->create([
                'token' => JobSharePairInvitation::generateToken(),
                'invited_by_candidate_profile_id' => $profile->id,
                'expires_at' => now()->addDays(JobSharePairInvitation::VALID_DAYS),
            ]);
        });
    }

    /**
     * A friend joins the pair through the link: no skill matching (the initiator chose her), but the pair must still
     * be forming with a free place and she must not have another pair for the offer. The link is used up, she becomes
     * open to job sharing and the initiator is notified.
     *
     * @throws ValidationException
     */
    public function joinByLink(CandidateProfile $profile, JobSharePairInvitation $invitation): JobSharePair
    {
        $pair = DB::transaction(function () use ($profile, $invitation): JobSharePair {
            $pair = JobSharePair::query()->lockForUpdate()->findOrFail($invitation->job_share_pair_id);
            $invitation->refresh()->setRelation('pair', $pair);

            $problem = $this->joinLinks->problemWith($invitation, $profile->user);

            if ($problem !== null) {
                throw ValidationException::withMessages(['join_link' => $problem->message()]);
            }

            $pair->members()->attach($profile->id, ['is_initiator' => false, 'accepted_at' => now()]);
            $pair->update(['status' => JobSharePairStatus::Formed]);
            $profile->update(['open_to_job_sharing' => true]);
            $invitation->update(['accepted_by_candidate_profile_id' => $profile->id, 'accepted_at' => now()]);

            return $pair;
        });

        $invitation->invitedBy->user->notify(new PairInvitationAccepted($pair, $profile));

        return $pair;
    }

    /**
     * The invited partner joins the pair; the initiator is notified.
     *
     * @throws ValidationException
     */
    public function accept(CandidateProfile $profile, JobSharePair $pair): void
    {
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
    }

    /**
     * Save a new proposal of the day split; both members have to accept it again.
     *
     * @param  list<array{candidate_profile_id: int, starts_at: string, ends_at: string}>  $blocks
     *
     * @throws ValidationException
     */
    public function saveSchedule(JobSharePair $pair, array $blocks): void
    {
        $members = $this->presenter->members($pair);

        $errors = $this->scheduleValidator->errors(
            Workday::forOffer($pair->jobOffer),
            array_values($members->map(fn (CandidateProfile $member): int => $member->id)->all()),
            $blocks,
        );

        if ($errors !== []) {
            throw ValidationException::withMessages(['schedule' => $errors[0]]);
        }

        DB::transaction(function () use ($pair, $members, $blocks): void {
            $pair->update(['proposed_schedule' => $blocks]);

            foreach ($members as $member) {
                $pair->members()->updateExistingPivot($member->id, ['schedule_confirmed_at' => null]);
            }
        });
    }

    /**
     * The member accepts the current proposal.
     *
     * @throws ValidationException
     */
    public function confirmSchedule(JobSharePair $pair, CandidateProfile $profile): void
    {
        if ($pair->proposed_schedule === null) {
            throw ValidationException::withMessages(['schedule' => 'Najpierw zapiszcie propozycję podziału dnia.']);
        }

        $pair->members()->updateExistingPivot($profile->id, ['schedule_confirmed_at' => now()]);
    }

    /**
     * Send the pair to the employer once both members accepted the split.
     *
     * @throws ValidationException
     */
    public function submit(JobSharePair $pair): void
    {
        $members = $this->presenter->members($pair);
        $everyoneConfirmed = $members->count() === JobSharePair::MAX_MEMBERS
            && $members->every(fn (CandidateProfile $member): bool => $this->presenter->hasAccepted($member) && $this->presenter->hasConfirmedSchedule($member));

        if ($pair->proposed_schedule === null || ! $everyoneConfirmed) {
            throw ValidationException::withMessages(['schedule' => 'Obie osoby muszą zaakceptować podział, zanim wyślecie go pracodawcy.']);
        }

        $pair->update(['status' => JobSharePairStatus::Submitted, 'submitted_at' => now()]);
    }
}
