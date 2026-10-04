<?php

namespace App\Http\Controllers\JobSharing;

use App\Enums\JoinLinkProblem;
use App\Http\Controllers\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Services\JobSharing\PairJoinLinks;
use App\Services\JobSharing\PairLifecycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public page behind the link a candidate sent a friend: the friend joins the pair, or signs in / creates an account first.
 */
class JoinController extends Controller
{
    use ResolvesCandidateProfile;

    public function __construct(private readonly PairJoinLinks $joinLinks) {}

    /**
     * States: join (signed-in candidate who may join), guest (sign in or register, then come back) and invalid.
     */
    public function show(Request $request, string $token): Response
    {
        $invitation = $this->joinLinks->find($token);
        $user = $request->user();
        $problem = $this->joinLinks->problemWith($invitation, $user);

        if ($user === null && $problem === null) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return Inertia::render('job-sharing/Join', [
            'state' => match (true) {
                $problem !== null => 'invalid',
                $user === null => 'guest',
                default => 'join',
            },
            'problem' => $problem?->message(),
            'token' => $token,
            'preview' => $invitation !== null ? $this->joinLinks->preview($invitation) : null,
        ]);
    }

    /**
     * The signed-in candidate joins the pair and lands on the pair page.
     */
    public function store(Request $request, string $token, PairLifecycle $lifecycle): RedirectResponse
    {
        $invitation = $this->joinLinks->find($token);

        if ($invitation === null) {
            throw ValidationException::withMessages(['join_link' => JoinLinkProblem::Invalid->message()]);
        }

        $profile = $this->candidateProfile($request);
        $pair = $lifecycle->joinByLink($profile, $invitation);

        Inertia::flash('toast', ['type' => 'success', 'message' => $profile->isPublished()
            ? 'Jesteście parą! Ustalcie podział dnia.'
            : 'Jesteście parą! Uzupełnij i opublikuj profil, żeby firma mogła zobaczyć Waszą parę.']);

        return to_route('job-sharing.pairs.show', $pair);
    }
}
