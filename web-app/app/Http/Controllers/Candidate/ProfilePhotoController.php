<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\StoreProfilePhotoRequest;
use App\Services\Candidate\ProfileOnboarding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * The candidate's private photo, revealed only to a company whose invitation she accepts.
 */
class ProfilePhotoController extends Controller
{
    use ResolvesCandidateProfile;

    /**
     * @throws ValidationException
     */
    public function store(StoreProfilePhotoRequest $request, ProfileOnboarding $onboarding): RedirectResponse
    {
        $onboarding->storePhoto($this->candidateProfile($request), $request->file('photo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Zdjęcie zapisane.']);

        return back();
    }

    public function destroy(Request $request, ProfileOnboarding $onboarding): RedirectResponse
    {
        $onboarding->removePhoto($this->candidateProfile($request));

        Inertia::flash('toast', ['type' => 'info', 'message' => 'Zdjęcie usunięte.']);

        return back();
    }
}
