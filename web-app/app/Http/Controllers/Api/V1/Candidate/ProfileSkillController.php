<?php

namespace App\Http\Controllers\Api\V1\Candidate;

use App\Http\Controllers\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\StoreProfileSkillRequest;
use App\Http\Resources\Api\V1\CandidateProfileResource;
use App\Models\Skill;
use App\Services\Candidate\ProfileOnboarding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileSkillController extends Controller
{
    use ResolvesCandidateProfile;

    /**
     * Add a tag typed by the candidate; it is confirmed at once.
     */
    public function store(StoreProfileSkillRequest $request, ProfileOnboarding $onboarding): JsonResponse
    {
        $profile = $this->candidateProfile($request);
        $onboarding->addManualSkill($profile, $request->validated('name'));

        return (new CandidateProfileResource($profile->refresh()))->response()->setStatusCode(201);
    }

    /**
     * Remove a tag from the profile.
     */
    public function destroy(Request $request, Skill $skill): CandidateProfileResource
    {
        $profile = $this->candidateProfile($request);
        $profile->skills()->detach($skill->id);

        return new CandidateProfileResource($profile->refresh());
    }
}
