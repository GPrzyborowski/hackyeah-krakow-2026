<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SkillSearchController extends Controller
{
    /**
     * Autocomplete for the offer tag inputs.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));

        $skills = Skill::query()
            ->suggestable()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.Str::slug($search).'%');
                });
            })
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name']);

        return response()->json(['data' => $skills]);
    }
}
