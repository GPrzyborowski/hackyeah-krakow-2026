<?php

namespace Database\Seeders;

use App\Models\Invitation;
use App\Models\JobSharePair;
use App\Models\User;
use App\Notifications\InvitationAccepted;
use App\Notifications\InvitationReceived;
use App\Notifications\NewMessage;
use App\Notifications\PairInvitationAccepted;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Bell notifications for the demo accounts. Seeding runs without model events, so the observers
 * that normally notify never fire; the rows are written straight to the database (no mail is sent).
 */
class DemoNotificationsSeeder extends Seeder
{
    public function run(): void
    {
        $marta = User::firstWhere('email', 'marta@momjobs.test');
        $greenOfficeRecruiter = User::firstWhere('email', 'hr@zielonebiuro.test');

        if ($marta === null || $greenOfficeRecruiter === null) {
            return;
        }

        $studioInvitation = Invitation::query()
            ->where('candidate_profile_id', $marta->candidateProfile->id)
            ->whereHas('jobOffer.company', fn ($query) => $query->where('name', 'Kamienica Studio'))
            ->first();

        $acceptedInvitation = Invitation::query()
            ->where('candidate_profile_id', $marta->candidateProfile->id)
            ->whereHas('conversation')
            ->with('conversation.messages.author', 'jobOffer.company')
            ->first();

        $pair = JobSharePair::query()
            ->whereHas('members', fn ($query) => $query->whereKey($marta->candidateProfile->id))
            ->with('jobOffer', 'members.user')
            ->first();

        if ($studioInvitation !== null) {
            $this->store($marta, new InvitationReceived($studioInvitation), now()->subMinutes(20));
        }

        if ($pair !== null) {
            $partner = $pair->members->first(fn ($member): bool => $member->isNot($marta->candidateProfile));

            if ($partner !== null) {
                $this->store($marta, new PairInvitationAccepted($pair, $partner), now()->subDays(2), read: true);
            }
        }

        $conversation = $acceptedInvitation?->conversation;

        if ($conversation === null) {
            return;
        }

        $lastCompanyMessage = $conversation->messages->where('user_id', $greenOfficeRecruiter->id)->sortBy('created_at')->last();
        $lastCandidateMessage = $conversation->messages->where('user_id', $marta->id)->sortBy('created_at')->last();

        if ($lastCompanyMessage !== null) {
            $this->store($marta, new NewMessage($lastCompanyMessage, $acceptedInvitation->jobOffer->company->name), now()->subHour());
        }

        $this->store($greenOfficeRecruiter, new InvitationAccepted($conversation), now()->subHours(5));

        if ($lastCandidateMessage !== null) {
            $this->store($greenOfficeRecruiter, new NewMessage($lastCandidateMessage, $marta->name), now()->subHours(2), read: true);
        }
    }

    private function store(User $user, InvitationReceived|InvitationAccepted|NewMessage|PairInvitationAccepted $notification, CarbonInterface $createdAt, bool $read = false): void
    {
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => $notification::class,
            'data' => $notification->toArray($user),
            'read_at' => $read ? $createdAt->copy()->addMinutes(10) : null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
