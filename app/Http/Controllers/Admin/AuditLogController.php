<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AuditDescriber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', AuditLog::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'module' => ['nullable', Rule::in(['all', ...array_keys(AuditLog::MODULE_LABELS)])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', Rule::in([10, 25, 50])],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $module = $filters['module'] ?? 'all';
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $direction = $filters['direction'] ?? 'desc';
        $perPage = (int) ($filters['per_page'] ?? 10);

        $logs = AuditLog::query()
            ->with('actor:id,name,email')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('subject_label', 'like', "%{$search}%")
                        ->orWhere('event', 'like', "%{$search}%")
                        ->orWhereHas('actor', fn (Builder $actor): Builder => $actor
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->when($module !== 'all', fn (Builder $query): Builder => $query->where('event', 'like', "{$module}.%"))
            ->when($dateFrom, fn (Builder $query, string $date): Builder => $query->where('occurred_at', '>=', "{$date} 00:00:00"))
            ->when($dateTo, fn (Builder $query, string $date): Builder => $query->where('occurred_at', '<=', "{$date} 23:59:59"))
            ->orderBy('occurred_at', $direction)
            ->orderBy('id', $direction)
            ->paginate($perPage)
            ->withQueryString();

        $describer = AuditDescriber::forLogs(collect($logs->items()));

        return Inertia::render('admin/audit/index', [
            'logs' => $logs->through(fn (AuditLog $log): array => [
                'id' => $log->id,
                'occurred_at' => $log->occurred_at->toIso8601String(),
                'actor_name' => $log->actor?->name,
                'actor_email' => $log->actor?->email,
                'description' => $describer->describe($log),
                'event_label' => $log->eventLabel(),
                'module_label' => $log->moduleLabel(),
                'subject_label' => $log->subject_label,
                'trace_id' => $log->trace_id,
                'changes' => $describer->changes($log),
                'reason' => $log->reason,
            ]),
            'filters' => [
                'search' => $search,
                'module' => $module,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            'moduleOptions' => collect(AuditLog::MODULE_LABELS)
                ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
        ]);
    }
}
