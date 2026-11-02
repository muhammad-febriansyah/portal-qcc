<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QccBatch;
use App\Models\QccBatchSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BatchScheduleController extends Controller
{
    public function index(QccBatch $batch): Response
    {
        Gate::authorize('update', $batch);

        return Inertia::render('admin/batches/schedules', [
            'batch' => [
                'id' => $batch->id,
                'name' => $batch->name,
                'starts_on' => $batch->starts_on->toDateString(),
                'ends_on' => $batch->ends_on->toDateString(),
            ],
            'schedules' => QccBatchSchedule::query()
                ->where('batch_id', $batch->id)
                ->orderBy('schedule_type')
                ->orderBy('sequence_no')
                ->get(['id', 'schedule_type', 'sequence_no', 'scheduled_on'])
                ->map(fn (QccBatchSchedule $schedule): array => [
                    'id' => $schedule->id,
                    'schedule_type' => $schedule->schedule_type,
                    'sequence_no' => $schedule->sequence_no,
                    'scheduled_on' => $schedule->scheduled_on->toDateString(),
                ])->all(),
        ]);
    }

    public function store(Request $request, QccBatch $batch): RedirectResponse
    {
        Gate::authorize('update', $batch);
        $scheduleType = $request->input('schedule_type');
        $scheduleType = is_string($scheduleType) ? $scheduleType : '';

        $validated = $request->validate([
            'schedule_type' => ['required', Rule::in(['BIWEEKLY', 'MENTORING'])],
            'sequence_no' => ['required', 'integer', 'between:1,52', Rule::unique('qcc_batch_schedules', 'sequence_no')
                ->where('batch_id', $batch->id)->where('schedule_type', $scheduleType)],
            'scheduled_on' => ['required', 'date_format:Y-m-d'],
        ]);

        QccBatchSchedule::query()->create([
            ...$validated,
            'batch_id' => $batch->id,
            'created_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Jadwal batch berhasil ditambahkan.']);

        return back();
    }

    public function update(Request $request, QccBatch $batch, QccBatchSchedule $schedule): RedirectResponse
    {
        Gate::authorize('update', $batch);
        abort_unless($schedule->batch_id === $batch->id, 404);

        $validated = $request->validate(['scheduled_on' => ['required', 'date_format:Y-m-d']]);
        $schedule->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tanggal jadwal berhasil diperbarui.']);

        return back();
    }
}
