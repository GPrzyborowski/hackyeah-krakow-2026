<?php

namespace App\Services\Ai;

use App\Models\CandidateProfile;
use App\Models\Skill;
use Illuminate\Support\Str;

/**
 * Offline fallback: finds dictionary skills (names and synonyms) mentioned in the pasted CV text.
 */
class KeywordCvAnalyzer implements CvAnalyzer
{
    public function analyze(CandidateProfile $profile): CvAnalysis
    {
        $text = Str::lower((string) $profile->cv_text);

        if ($text === '') {
            return new CvAnalysis(skills: []);
        }

        $skills = array_values(Skill::query()->get()
            ->filter(function (Skill $skill) use ($text): bool {
                return collect([$skill->name, ...($skill->synonyms ?? [])])
                    ->contains(fn (string $term): bool => Str::contains($text, Str::lower($term)));
            })
            ->map(fn (Skill $skill): string => $skill->name)
            ->all());

        return new CvAnalysis(skills: $skills);
    }
}
