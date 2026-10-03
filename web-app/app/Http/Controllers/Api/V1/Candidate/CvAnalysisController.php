<?php

namespace App\Http\Controllers\Api\V1\Candidate;

use App\Http\Controllers\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Http\Resources\CvAnalysisResource;
use App\Services\Matching\CvInsights;
use Illuminate\Http\Request;

class CvAnalysisController extends Controller
{
    use ResolvesCandidateProfile;

    /**
     * The candidate's private "Analiza CV" (same data as the web page).
     */
    public function __invoke(Request $request, CvInsights $cvInsights): CvAnalysisResource
    {
        return new CvAnalysisResource($cvInsights->analyze($this->candidateProfile($request)));
    }
}
