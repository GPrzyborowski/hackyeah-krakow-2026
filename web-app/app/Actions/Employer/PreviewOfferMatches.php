<?php

namespace App\Actions\Employer;

use App\Models\Company;
use App\Models\Skill;
use App\Services\Matching\MatchScorer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Live "Pasujące kandydatki" counters for an offer that is still being edited.
 */
class PreviewOfferMatches
{
    public function __construct(private readonly MatchScorer $scorer) {}

    /**
     * @param  list<string>  $requiredSkillNames
     * @param  list<string>  $niceToHaveSkillNames
     * @return array{with_required: int, with_nice_to_have: int}
     */
    public function handle(Company $company, string $startDate, array $requiredSkillNames, array $niceToHaveSkillNames): array
    {
        return $this->scorer->previewCounts(
            $company,
            Carbon::parse($startDate),
            $this->skillIds($requiredSkillNames),
            $this->skillIds($niceToHaveSkillNames),
        );
    }

    /**
     * Resolve tag names to skill ids; unknown names map to id 0 so a required unknown skill matches nobody.
     *
     * @param  list<string>  $names
     * @return list<int>
     */
    private function skillIds(array $names): array
    {
        $slugs = collect($names)->map(fn (string $name): string => Str::slug($name))->filter()->unique();
        $known = Skill::query()->whereIn('slug', $slugs)->pluck('id', 'slug');

        return array_values($slugs->map(fn (string $slug): int => (int) ($known[$slug] ?? 0))->all());
    }
}
