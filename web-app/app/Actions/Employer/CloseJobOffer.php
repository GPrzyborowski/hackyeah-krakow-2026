<?php

namespace App\Actions\Employer;

use App\Enums\InvitationStatus;
use App\Enums\OfferStatus;
use App\Models\JobOffer;
use Illuminate\Support\Facades\DB;

/**
 * Closes an offer and withdraws invitations nobody answered yet.
 */
class CloseJobOffer
{
    public function handle(JobOffer $offer): JobOffer
    {
        DB::transaction(function () use ($offer): void {
            $offer->update(['status' => OfferStatus::Closed]);
            $offer->invitations()->where('status', InvitationStatus::Pending)->update(['status' => InvitationStatus::Withdrawn]);
        });

        return $offer;
    }
}
