<?php

namespace App\Services\Ai;

use App\Models\CandidateProfile;

interface CvAnalyzer
{
    /**
     * Read the candidate's CV (file and/or pasted text) and propose skills, positions and a summary.
     */
    public function analyze(CandidateProfile $profile): CvAnalysis;
}
