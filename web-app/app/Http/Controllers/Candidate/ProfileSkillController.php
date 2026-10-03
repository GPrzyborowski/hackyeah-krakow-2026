<?php

namespace App\Http\Controllers\Candidate;

use App\Enums\SkillSource;
use App\Http\Controllers\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\StoreProfileSkillRequest;
use App\Models\Skill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileSkillController extends Controller
{
    use ResolvesCandidateProfile;

    /**
     * Add a tag typed by the candidate (existing dictionary skill or a new one); she typed it herself, so it is confirmed at once.
     */
    public function store(StoreProfileSkillRequest $request): RedirectResponse
    {
        $profile = $this->candidateProfile($request);
        $skill = Skill::findOrCreateByName($request->validated('name'));

        if (! $profile->skills()->whereKey($skill->id)->exists()) {
            $profile->skills()->attach($skill->id, ['source' => SkillSource::Manual->value, 'confirmed_at' => now()]);
        }

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
