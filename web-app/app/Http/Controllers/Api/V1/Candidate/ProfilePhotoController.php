<?php

namespace App\Http\Controllers\Api\V1\Candidate;

use App\Http\Controllers\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\StoreProfilePhotoRequest;
use App\Http\Resources\Api\V1\CandidateProfileResource;
use App\Services\Candidate\ProfileOnboarding;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Upload or remove the candidate's private photo (shown only to a company whose invitation she accepted).
 */
class ProfilePhotoController extends Controller
{
    use ResolvesCandidateProfile;

    /**
     * @throws ValidationException
     */
    public function store(StoreProfilePhotoRequest $request, ProfileOnboarding $onboarding): CandidateProfileResource
    {
        $profile = $this->candidateProfile($request);
        $onboarding->storePhoto($profile, $request->file('photo'));

        return new CandidateProfileResource($profile->refresh());
    }

    public function destroy(Request $request, ProfileOnboarding $onboarding): CandidateProfileResource
    {
        $profile = $this->candidateProfile($request);
        $onboarding->removePhoto($profile);

        return new CandidateProfileResource($profile->refresh());
    }
}
