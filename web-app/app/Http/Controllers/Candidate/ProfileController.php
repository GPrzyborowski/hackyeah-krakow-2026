<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\UpdatePreferencesRequest;
use App\Services\Candidate\ProfileOnboarding;
use App\Services\Candidate\ProfilePresenter;
use App\Services\Candidate\ReturnCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Standalone profile overview with edit-in-place sections, so a finished profile is not edited through the wizard.
 */
class ProfileController extends Controller
{
    use ResolvesCandidateProfile;

    /**
     * Profiles that never finished the wizard are sent back to it.
     */
    public function show(Request $request, ProfilePresenter $presenter, ReturnCalendar $returnCalendar): Response|RedirectResponse
    {
        $profile = $this->candidateProfile($request)->load(['user', 'skills']);

        if (! $profile->isPublished() && $profile->onboarding_step < ProfileOnboarding::LAST_STEP) {
            return to_route('candidate.onboarding.show');
        }

        return Inertia::render('candidate/Profile', [
            'fullName' => $profile->user->name,
            'profile' => $presenter->profile($profile),
            'skills' => $presenter->skills($profile),
            'calendar' => $returnCalendar->forProfile($profile),
            ...$presenter->options(),
        ]);
    }

    /**
     * Save work preferences and the return calendar from the profile page and stay on it.
     */
    public function updatePreferences(UpdatePreferencesRequest $request, ProfileOnboarding $onboarding): RedirectResponse
    {
        $onboarding->updatePreferences($this->candidateProfile($request), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Preferencje zapisane.']);

        return to_route('candidate.profile');
    }

    /**
     * Confirm the AI-proposed tags from the profile page so employers can see them.
     *
     * @throws ValidationException
     */
    public function confirmSkills(Request $request, ProfileOnboarding $onboarding): RedirectResponse
    {
        $onboarding->confirmSkills($this->candidateProfile($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Umiejętności zatwierdzone. Pracodawcy je widzą.']);

        return to_route('candidate.profile');
    }
}
