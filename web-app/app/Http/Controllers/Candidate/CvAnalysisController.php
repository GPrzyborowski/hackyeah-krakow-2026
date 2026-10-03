<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Http\Resources\CvAnalysisResource;
use App\Services\Matching\CvInsights;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CvAnalysisController extends Controller
{
    use ResolvesCandidateProfile;

    /**
     * Private "Analiza CV": strengths, suggested positions, missing skills and the best matching offers.
     */
    public function __invoke(Request $request, CvInsights $cvInsights): Response|RedirectResponse
    {
        $profile = $this->candidateProfile($request);

        if (! $profile->isPublished()) {
            return to_route('candidate.onboarding.show');
        }

        return Inertia::render('candidate/CvAnalysis', [
            'analysis' => (new CvAnalysisResource($cvInsights->analyze($profile)))->resolve($request),
            'cvFileName' => $profile->cv_original_name,
            'headline' => $profile->headline,
        ]);
    }
}
