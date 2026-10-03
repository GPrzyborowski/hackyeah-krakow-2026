<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\StoreProfileSkillRequest;
use App\Models\Skill;
use App\Services\Candidate\ProfileOnboarding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileSkillController extends Controller
{
    use ResolvesCandidateProfile;

    /**
     * Add a tag typed by the candidate (existing dictionary skill or a new one); she typed it herself, so it is confirmed at once.
     */
    public function store(StoreProfileSkillRequest $request, ProfileOnboarding $onboarding): RedirectResponse
    {
        $onboarding->addManualSkill($this->candidateProfile($request), $request->validated('name'));

        return back();
    }

    /**
     * Remove a tag from the profile.
     */
    public function destroy(Request $request, Skill $skill): RedirectResponse
    {
        $this->candidateProfile($request)->skills()->detach($skill->id);

        return back();
    }
}
