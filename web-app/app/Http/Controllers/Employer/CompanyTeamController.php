<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Employer\Concerns\InteractsWithEmployerCompany;
use App\Http\Requests\Employer\InviteTeamMemberRequest;
use App\Models\CompanyInvitation;
use App\Models\User;
use App\Services\Employer\CompanyTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Recruiters of the company: members, pending e-mail invitations, inviting and removing.
 */
class CompanyTeamController extends Controller
{
    use InteractsWithEmployerCompany;

    public function __construct(private readonly CompanyTeam $team) {}

    public function index(Request $request): Response
    {
        $company = $this->currentCompany($request);
        Gate::authorize('manageTeam', $company);

        $members = $this->team->members($company);
        $joinedAt = $this->team->joinedAt($company, $members);

        return Inertia::render('employer/company/Team', [
            'company' => $company->only(['id', 'name']),
            'members' => $members->map(fn (User $member): array => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'joined_at' => $joinedAt[$member->id],
                'is_current_user' => $member->is($request->user()),
            ])->values(),
            'invitations' => $this->team->pendingInvitations($company)->map(fn (CompanyInvitation $invitation): array => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'invited_by' => $invitation->invitedBy?->name,
                'created_at' => $invitation->created_at->toIso8601String(),
                'expires_at' => $invitation->expires_at->toIso8601String(),
                'is_expired' => $invitation->isExpired(),
            ])->values(),
        ]);
    }

    public function storeInvitation(InviteTeamMemberRequest $request): RedirectResponse
    {
        $invitation = $this->team->invite($this->currentCompany($request), $request->user(), $request->validated('email'));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Zaproszenie wysłane na adres {$invitation->email}."]);

        return to_route('employer.company.team.index');
    }

    public function destroyInvitation(Request $request, CompanyInvitation $invitation): RedirectResponse
    {
        Gate::authorize('revokeInvitation', [$this->currentCompany($request), $invitation]);

        $this->team->revoke($invitation);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Zaproszenie anulowane.']);

        return to_route('employer.company.team.index');
    }

    public function destroyMember(Request $request, User $member): RedirectResponse
    {
        Gate::authorize('removeMember', [$this->currentCompany($request), $member]);

        $this->team->removeMember($member);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$member->name} nie należy już do zespołu."]);

        return to_route('employer.company.team.index');
    }
}
