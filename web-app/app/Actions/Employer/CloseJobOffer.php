<?php

namespace App\Actions\Employer;

use App\Enums\InvitationStatus;
use App\Enums\JobSharePairStatus;
use App\Enums\OfferStatus;
use App\Models\JobOffer;
use Illuminate\Support\Facades\DB;

/**
 * Closes an offer, withdraws invitations nobody answered yet and cancels job-sharing pairs still in progress.
 * Hired pairs and pairs already decided stay as they are.
 */
class CloseJobOffer
{
    /**
     * Pair statuses that end with the offer.
     */
    public const array CANCELLED_PAIR_STATUSES = [
        JobSharePairStatus::Forming,
        JobSharePairStatus::Formed,
        JobSharePairStatus::Submitted,
        JobSharePairStatus::Invited,
    ];

    public function handle(JobOffer $offer): JobOffer
    {
        DB::transaction(function () use ($offer): void {
            $offer->update(['status' => OfferStatus::Closed]);
            $offer->invitations()->whereIn('status', InvitationStatus::UNANSWERED)->update(['status' => InvitationStatus::Withdrawn]);
            $offer->jobSharePairs()->whereIn('status', self::CANCELLED_PAIR_STATUSES)->update(['status' => JobSharePairStatus::Cancelled]);
        });

        return $offer;
    }
}
