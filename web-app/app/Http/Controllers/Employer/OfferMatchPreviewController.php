<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Employer\Concerns\InteractsWithEmployerCompany;
use App\Http\Requests\Employer\PreviewMatchesRequest;
use App\Models\Skill;
use App\Services\Matching\MatchScorer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class OfferMatchPreviewController extends Controller
{
    use InteractsWithEmployerCompany;

    /**
     * Live "Pasujące kandydatki" counters for the offer form.
     */
    public function __invoke(PreviewMatchesRequest $request, MatchScorer $scorer): JsonResponse
    {
        $counts = $scorer->previewCounts(
            $this->currentCompany($request),
            Carbon::parse($request->validated('start_date')),
            $this->skillIds($request->validated('required_skills')),
            $this->skillIds($request->validated('nice_to_have_skills')),
        );

        return response()->json($counts);
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

        return $slugs->map(fn (string $slug): int => (int) ($known[$slug] ?? 0))->values()->all();
    }
}
