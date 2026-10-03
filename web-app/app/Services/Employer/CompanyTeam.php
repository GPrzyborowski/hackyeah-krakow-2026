<?php

namespace App\Services\Employer;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\DeviceToken;
use App\Models\User;
use App\Notifications\CompanyTeamInvitation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Recruiters of one company: listing, e-mail invitations, acceptance and removal.
 */
class CompanyTeam
{
    /**
     * @return Collection<int, User>
     */
    public function members(Company $company): Collection
    {
        return $company->members()->orderBy('name')->get(['id', 'name', 'email', 'company_id', 'created_at']);
    }

    /**
     * When each member joined: the acceptance of their invitation, or account creation for the founder.
     *
     * @param  Collection<int, User>  $members
     * @return array<int, string>
     */
    public function joinedAt(Company $company, Collection $members): array
    {
        $acceptedByEmail = $company->teamInvitations()
            ->whereNotNull('accepted_at')
            ->get(['email', 'accepted_at'])
            ->mapWithKeys(fn (CompanyInvitation $invitation): array => [Str::lower($invitation->email) => $invitation->accepted_at]);

        return $members->mapWithKeys(fn (User $member): array => [
            $member->id => ($acceptedByEmail[Str::lower($member->email)] ?? $member->created_at)->toIso8601String(),
        ])->all();
    }

    /**
     * Invitations not accepted yet, newest first (expired ones stay visible until revoked or re-sent).
     *
     * @return Collection<int, CompanyInvitation>
     */
    public function pendingInvitations(Company $company): Collection
    {
        return $company->teamInvitations()->pending()->with('invitedBy:id,name')->latest('id')->get();
    }

    public function isMember(Company $company, string $email): bool
    {
        return $company->members()->whereRaw('lower(email) = ?', [Str::lower($email)])->exists();
    }

    public function hasUsableInvitation(Company $company, string $email): bool
    {
        return $company->teamInvitations()->usable()->where('email', Str::lower($email))->exists();
    }

    /**
     * Creates the invitation (replacing stale ones for the same address) and e-mails the signed link.
     */
    public function invite(Company $company, User $inviter, string $email): CompanyInvitation
    {
        $email = Str::lower(trim($email));

        $invitation = DB::transaction(function () use ($company, $inviter, $email): CompanyInvitation {
            $company->teamInvitations()->pending()->where('email', $email)->delete();

            return $company->teamInvitations()->create([
                'email' => $email,
                'token' => CompanyInvitation::generateToken(),
                'invited_by_user_id' => $inviter->id,
                'expires_at' => now()->addDays(CompanyInvitation::VALID_DAYS),
            ]);
        });

        Notification::route('mail', $email)->notify(new CompanyTeamInvitation($invitation->load('company', 'invitedBy')));

        return $invitation;
    }

    public function revoke(CompanyInvitation $invitation): void
    {
        $invitation->delete();
    }

    /**
     * Detaches the recruiter from the company and revokes every API token (and the push devices tied to them).
     */
    public function removeMember(User $member): void
    {
        DB::transaction(function () use ($member): void {
            $member->forceFill(['company_id' => null])->save();
            $member->tokens()->delete();
            DeviceToken::where('user_id', $member->id)->delete();
        });
    }

    public function findInvitation(string $token): ?CompanyInvitation
    {
        return CompanyInvitation::with('company')->where('token', $token)->first();
    }

    public function findUserByEmail(string $email): ?User
    {
        return User::whereRaw('lower(email) = ?', [Str::lower($email)])->first();
    }

    /**
     * Why the invitation cannot be accepted by this visitor (null when it can).
     */
    public function problemWith(?CompanyInvitation $invitation, ?User $user): ?string
    {
        if ($invitation === null) {
            return 'Ten link do zaproszenia jest nieprawidłowy. Poproś osobę z firmy o nowe zaproszenie.';
        }

        if ($invitation->isAccepted()) {
            return 'To zaproszenie zostało już wykorzystane. Zaloguj się, aby przejść do panelu firmy.';
        }

        if ($invitation->isExpired()) {
            return 'To zaproszenie wygasło. Poproś osobę z firmy '.$invitation->company->name.' o wysłanie nowego.';
        }

        if ($user === null) {
            return null;
        }

        if (Str::lower($user->email) !== Str::lower($invitation->email)) {
            return 'To zaproszenie wysłano na inny adres e-mail. Wyloguj się i otwórz link ponownie albo zaloguj się na konto z tym adresem.';
        }

        if ($user->role !== UserRole::Employer) {
            return 'Ten adres e-mail należy do konta, które nie jest kontem pracodawcy. Poproś o zaproszenie na inny (np. służbowy) adres.';
        }

        if ($user->company_id === $invitation->company_id) {
            return 'Już należysz do zespołu firmy '.$invitation->company->name.'.';
        }

        if ($user->company_id !== null) {
            return 'Twoje konto należy już do innej firmy w MomJobs. Jedno konto może należeć tylko do jednej firmy.';
        }

        return null;
    }

    /**
     * Attaches an existing employer account without a company; opening the link also proves the address.
     */
    public function accept(CompanyInvitation $invitation, User $user): void
    {
        DB::transaction(function () use ($invitation, $user): void {
            $user->forceFill([
                'company_id' => $invitation->company_id,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
            $invitation->update(['accepted_at' => now()]);
        });
    }

    /**
     * Creates an employer account for the invited address; the link proved e-mail ownership, so it is verified.
     */
    public function registerAndAccept(CompanyInvitation $invitation, string $name, string $password): User
    {
        return DB::transaction(function () use ($invitation, $name, $password): User {
            $user = new User([
                'name' => $name,
                'email' => $invitation->email,
                'password' => $password,
            ]);

            $user->forceFill([
                'role' => UserRole::Employer,
                'company_id' => $invitation->company_id,
                'email_verified_at' => now(),
            ])->save();

            $invitation->update(['accepted_at' => now()]);

            return $user;
        });
    }
}
