<?php

namespace App\Http\Controllers\Employer;

use App\Concerns\PasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\CompanyInvitation;
use App\Services\Employer\CompanyTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public page behind the e-mailed team invitation link: a guest creates an employer account,
 * a signed-in employer without a company joins with one click.
 */
class CompanyInvitationAcceptanceController extends Controller
{
    use PasswordValidationRules;

    public function __construct(private readonly CompanyTeam $team) {}

    public function show(Request $request, string $token): Response
    {
        $invitation = $this->team->findInvitation($token);
        $user = $request->user();
        $problem = $this->team->problemWith($invitation, $user);

        if ($problem !== null || $invitation === null) {
            return $this->render('invalid', null, $problem);
        }

        if ($user !== null) {
            return $this->render('accept', $invitation);
        }

        if ($this->team->findUserByEmail($invitation->email) !== null) {
            $request->session()->put('url.intended', $request->fullUrl());

            return $this->render('login', $invitation);
        }

        return $this->render('register', $invitation);
    }

    /**
     * Guest path: the link proved ownership of the address, so the new account is verified right away.
     */
    public function register(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->usableInvitation($token);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => $this->passwordRules(),
        ]);

        if ($this->team->findUserByEmail($invitation->email) !== null) {
            throw ValidationException::withMessages(['name' => 'Konto z tym adresem e-mail już istnieje. Zaloguj się i otwórz link ponownie.']);
        }

        $user = $this->team->registerAndAccept($invitation, $validated['name'], $validated['password']);

        Auth::login($user);
        $request->session()->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => "Witaj w zespole firmy {$invitation->company->name}!"]);

        return to_route('employer.offers.index');
    }

    /**
     * Signed-in employer with the invited address and no company joins the team.
     */
    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->usableInvitation($token, $request);

        $this->team->accept($invitation, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Dołączono do zespołu firmy {$invitation->company->name}."]);

        return to_route('employer.offers.index');
    }

    private function usableInvitation(string $token, ?Request $request = null): CompanyInvitation
    {
        $invitation = $this->team->findInvitation($token);
        $problem = $this->team->problemWith($invitation, $request?->user());

        if ($problem !== null || $invitation === null) {
            throw ValidationException::withMessages(['invitation' => $problem ?? '']);
        }

        return $invitation;
    }

    private function render(string $state, ?CompanyInvitation $invitation, ?string $problem = null): Response
    {
        return Inertia::render('auth/AcceptCompanyInvitation', [
            'state' => $state,
            'problem' => $problem,
            'invitation' => $invitation === null ? null : [
                'token' => $invitation->token,
                'email' => $invitation->email,
                'company_name' => $invitation->company->name,
                'invited_by' => $invitation->invitedBy?->name,
                'expires_at' => $invitation->expires_at->toIso8601String(),
            ],
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }
}
