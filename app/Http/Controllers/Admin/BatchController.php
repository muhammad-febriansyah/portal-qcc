<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BatchStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBatchRequest;
use App\Http\Requests\UpdateBatchRequest;
use App\Models\QccBatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BatchController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', QccBatch::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['all', ...array_column(BatchStatus::cases(), 'value')])],
            'sort' => ['nullable', Rule::in(['code', 'name', 'starts_on', 'ends_on', 'status', 'updated_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', Rule::in([10, 25, 50])],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? 'all';
        $sort = $filters['sort'] ?? 'starts_on';
        $direction = $filters['direction'] ?? 'desc';
        $perPage = (int) ($filters['per_page'] ?? 10);

        $batches = QccBatch::query()
            ->select(['id', 'code', 'name', 'starts_on', 'ends_on', 'status', 'updated_at'])
            ->addSelect([
                'is_used' => DB::table('projects')
                    ->selectRaw('1')
                    ->whereColumn('projects.batch_id', 'qcc_batches.id')
                    ->limit(1),
            ])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($status !== 'all', fn (Builder $query): Builder => $query->where('status', $status))
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('admin/batches/index', [
            'batches' => $batches->through(fn (QccBatch $batch): array => [
                'id' => $batch->id,
                'code' => $batch->code,
                'name' => $batch->name,
                'starts_on' => $batch->starts_on->format('Y-m-d'),
                'ends_on' => $batch->ends_on->format('Y-m-d'),
                'status' => $batch->status->value,
                'updated_at' => $batch->updated_at?->toIso8601String(),
                'is_used' => (bool) $batch->getAttribute('is_used'),
            ]),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            'statusOptions' => BatchStatus::options(),
        ]);
    }

    public function store(StoreBatchRequest $request): RedirectResponse
    {
        QccBatch::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Batch berhasil ditambahkan.']);

        return back();
    }

    public function update(UpdateBatchRequest $request, QccBatch $batch): RedirectResponse
    {
        $batch->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Batch berhasil diperbarui.']);

        return back();
    }

    public function destroy(QccBatch $batch): RedirectResponse
    {
        Gate::authorize('delete', $batch);

        if (DB::table('projects')->where('batch_id', $batch->id)->exists()) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => 'Batch sudah dipakai proyek dan tidak dapat dihapus. Ubah statusnya menjadi Ditutup.',
            ]);

            return back();
        }

        $batch->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Batch berhasil dihapus.']);

        return back();
    }
}
