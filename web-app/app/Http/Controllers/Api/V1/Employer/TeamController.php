<?php

namespace App\Http\Controllers\Api\V1\Employer;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Employer\Concerns\InteractsWithEmployerCompany;
use App\Http\Requests\Employer\InviteTeamMemberRequest;
use App\Http\Resources\Api\V1\CompanyInvitationResource;
use App\Http\Resources\Api\V1\CompanyMemberResource;
use App\Models\CompanyInvitation;
use App\Models\User;
use App\Services\Employer\CompanyTeam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * The company's recruiters and pending team invitations. Accepting an invitation is web-only (link in the e-mail).
 */
class TeamController extends Controller
{
    use InteractsWithEmployerCompany;

    public function __construct(private readonly CompanyTeam $team) {}

    public function index(Request $request): JsonResponse
    {
        $company = $this->currentCompany($request);
        Gate::authorize('manageTeam', $company);

        $members = $this->team->members($company);
        $joinedAt = $this->team->joinedAt($company, $members);

        return response()->json([
            'data' => [
                'members' => $members->map(fn (User $member): array => (new CompanyMemberResource($member, $joinedAt[$member->id]))->resolve($request))->values(),
                'invitations' => CompanyInvitationResource::collection($this->team->pendingInvitations($company))->resolve($request),
            ],
        ]);
    }

    /**
     * E-mails a signed link to join the team (throttle 10/min).
     */
    public function storeInvitation(InviteTeamMemberRequest $request): JsonResponse
    {
        $invitation = $this->team->invite($this->currentCompany($request), $request->user(), $request->validated('email'));

        return (new CompanyInvitationResource($invitation))->response()->setStatusCode(201);
    }

    public function destroyInvitation(Request $request, CompanyInvitation $invitation): Response
    {
        Gate::authorize('revokeInvitation', [$this->currentCompany($request), $invitation]);

        $this->team->revoke($invitation);

        return response()->noContent();
    }

    /**
     * Detaches the recruiter from the company and revokes all their API tokens.
     */
    public function destroyMember(Request $request, User $user): Response
    {
        Gate::authorize('removeMember', [$this->currentCompany($request), $user]);

        $this->team->removeMember($user);

        return response()->noContent();
    }
}
