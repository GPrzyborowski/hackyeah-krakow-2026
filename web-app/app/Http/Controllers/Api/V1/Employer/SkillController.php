<?php

namespace App\Http\Controllers\Api\V1\Employer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EmployerSkillResource;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class SkillController extends Controller
{
    /**
     * Autocomplete for the offer skill inputs (up to 10 suggestions, same matching as the web panel).
     */
    public function __invoke(Request $request): AnonymousResourceCollection
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

        return EmployerSkillResource::collection($skills);
    }
}
