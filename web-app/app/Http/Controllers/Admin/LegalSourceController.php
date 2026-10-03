<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveLegalSourceRequest;
use App\Models\LegalSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class LegalSourceController extends Controller
{
    /**
     * Legal sources used by the assistant, grouped by act and ordered naturally by article number.
     */
    public function index(): Response
    {
        $groups = LegalSource::query()
            ->get()
            ->sortBy('article', SORT_NATURAL)
            ->groupBy('act')
            ->sortKeys(SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn (Collection $sources, string $act): array => [
                'act' => $act,
                'sources' => $sources->map(fn (LegalSource $source): array => [
                    'id' => $source->id,
                    'article' => $source->article,
                    'title' => $source->title,
                    'excerpt' => str($source->content)->squish()->limit(160)->toString(),
                    'keywords' => $source->keywords ?? [],
                ])->values(),
            ])
            ->values();

        return Inertia::render('admin/legal-sources/Index', [
            'groups' => $groups,
        ]);
    }

    public function create(): Response
    {
        return $this->renderForm(null);
    }

    public function store(SaveLegalSourceRequest $request): RedirectResponse
    {
        LegalSource::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Źródło prawne dodane. Asystent już z niego korzysta.']);

        return to_route('admin.legal-sources.index');
    }

    public function edit(LegalSource $legalSource): Response
    {
        return $this->renderForm($legalSource);
    }

    public function update(SaveLegalSourceRequest $request, LegalSource $legalSource): RedirectResponse
    {
        $legalSource->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Zmiany zapisane.']);

        return to_route('admin.legal-sources.index');
    }

    public function destroy(LegalSource $legalSource): RedirectResponse
    {
        $legalSource->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Źródło prawne usunięte.']);

        return to_route('admin.legal-sources.index');
    }

    private function renderForm(?LegalSource $legalSource): Response
    {
        return Inertia::render('admin/legal-sources/Form', [
            'source' => $legalSource ? [
                'id' => $legalSource->id,
                'act' => $legalSource->act,
                'article' => $legalSource->article,
                'title' => $legalSource->title,
                'content' => $legalSource->content,
                'keywords' => $legalSource->keywords ?? [],
            ] : null,
            'acts' => LegalSource::query()->distinct()->orderBy('act')->pluck('act'),
        ]);
    }
}
