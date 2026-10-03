<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ModerationContext;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ModerationEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ModerationEventController extends Controller
{
    private const int LIST_LIMIT = 100;

    /**
     * Texts blocked by the moderator (newest first), filterable by `?context=` and `?company=`,
     * plus the companies with the most blocks (repeat offenders).
     */
    public function index(Request $request): Response
    {
        $context = ModerationContext::tryFrom((string) $request->query('context'));
        $companyId = $request->integer('company') ?: null;

        $filtered = fn (): Builder => ModerationEvent::query()
            ->when($context, fn (Builder $query) => $query->where('context', $context))
            ->when($companyId, fn (Builder $query) => $query->where('company_id', $companyId));

        $events = $filtered()
            ->with(['company:id,name', 'user:id,name'])
            ->latest('created_at')
            ->latest('id')
            ->limit(self::LIST_LIMIT)
            ->get();

        $offenders = ModerationEvent::query()
            ->whereNotNull('company_id')
            ->when($context, fn (Builder $query) => $query->where('context', $context))
            ->toBase()
            ->selectRaw('company_id, count(*) as total, max(created_at) as last_at')
            ->groupBy('company_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $companyNames = Company::query()
            ->whereIn('id', ModerationEvent::query()->whereNotNull('company_id')->select('company_id'))
            ->orderBy('name')
            ->pluck('name', 'id');

        return Inertia::render('admin/moderation/Index', [
            'filters' => [
                'context' => $context?->value,
                'company' => $companyId,
            ],
            'total' => $filtered()->count(),
            'contexts' => collect(ModerationContext::cases())->map(fn (ModerationContext $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ])->values(),
            'companies' => $companyNames->map(fn (string $name, int $id): array => ['id' => $id, 'name' => $name])->values(),
            'offenders' => $offenders->map(fn (object $row): array => [
                'company' => ['id' => (int) $row->company_id, 'name' => $companyNames[$row->company_id] ?? 'Firma usunięta'],
                'total' => (int) $row->total,
                'last_at' => $row->last_at ? Carbon::parse($row->last_at)->toIso8601String() : null,
            ])->values(),
            'events' => $events->map(fn (ModerationEvent $event): array => [
                'id' => $event->id,
                'context' => $event->context->value,
                'context_label' => $event->context->label(),
                'company' => $event->company ? ['id' => $event->company->id, 'name' => $event->company->name] : null,
                'author_name' => $event->user?->name,
                'excerpt' => $event->excerpt,
                'reason' => $event->reason,
                'moderator' => $event->moderator,
                'created_at' => $event->created_at?->toIso8601String(),
            ])->values(),
            'retention_days' => ModerationEvent::RETENTION_DAYS,
        ]);
    }
}
