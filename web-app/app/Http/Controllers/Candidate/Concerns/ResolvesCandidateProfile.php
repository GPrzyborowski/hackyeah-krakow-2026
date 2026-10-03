<?php

namespace App\Http\Controllers\Candidate\Concerns;

use App\Models\CandidateProfile;
use Illuminate\Http\Request;

trait ResolvesCandidateProfile
{
    /**
     * The signed-in candidate's profile (created on registration, recreated defensively when missing).
     */
    protected function candidateProfile(Request $request): CandidateProfile
    {
        return $request->user()->candidateProfile()->firstOrCreate([], ['onboarding_step' => 1]);
    }
}
