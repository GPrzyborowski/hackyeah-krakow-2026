<?php

namespace App\Services\Matching;

/**
 * Outcome of comparing one candidate profile with one job offer.
 */
final readonly class MatchResult
{
    /**
     * @param  list<string>  $matchedRequired
     * @param  list<string>  $missingRequired
     * @param  list<string>  $matchedNiceToHave
     * @param  list<string>  $missingNiceToHave
     */
    public function __construct(
        public int $score,
        public array $matchedRequired,
        public array $missingRequired,
        public array $matchedNiceToHave,
        public array $missingNiceToHave,
        public bool $startDateCompatible,
    ) {}

    /**
     * @return array{score: int, matched_required: list<string>, missing_required: list<string>, matched_nice_to_have: list<string>, missing_nice_to_have: list<string>, start_date_compatible: bool}
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'matched_required' => $this->matchedRequired,
            'missing_required' => $this->missingRequired,
            'matched_nice_to_have' => $this->matchedNiceToHave,
            'missing_nice_to_have' => $this->missingNiceToHave,
            'start_date_compatible' => $this->startDateCompatible,
        ];
    }
}
