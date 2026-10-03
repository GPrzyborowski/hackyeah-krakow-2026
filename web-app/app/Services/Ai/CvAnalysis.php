<?php

namespace App\Services\Ai;

final readonly class CvAnalysis
{
    /**
     * @param  list<string>  $skills
     * @param  list<array{title: string, score: int}>  $positions
     */
    public function __construct(
        public array $skills,
        public array $positions = [],
        public ?string $summary = null,
        public ?string $headline = null,
        public ?int $yearsOfExperience = null,
        public ?string $extractedText = null,
    ) {}
}
