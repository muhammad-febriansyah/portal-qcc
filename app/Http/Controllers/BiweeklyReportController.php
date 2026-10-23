<?php

namespace App\Http\Controllers;

use App\Enums\ReportStatus;
use App\Http\Requests\SaveBiweeklyReportRequest;
use App\Models\ActionItem;
use App\Models\BiweeklyReport;
use App\Models\EvidenceLink;
use App\Models\Project;
use App\Models\User;
use App\Services\AuditService;
use App\Services\ProjectNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BiweeklyReportController extends Controller
{
    private const EDITABLE = [ReportStatus::Draft, ReportStatus::RevisionRequired];

    public function __construct(
        private readonly AuditService $audit,
        private readonly ProjectNotifier $notifier,
    ) {}

    public function index(Request $request, Project $project): Response
    {
        Gate::authorize('view', $project);

        $user = $request->user();
        $canViewEvidence = $user->can('viewEvidence', $project);
        $reports = $project->biweeklyReports()
            ->with(['attendees:users.id,users.name', 'submitter:id,name', 'actions.pic:id,name'])
            ->when($canViewEvidence, fn ($query) => $query->with('evidenceLinks.evidence'))
            ->limit(100)
            ->get();

        return Inertia::render('projects/biweekly', [
            'project' => ['id' => $project->id, 'code' => sprintf('PRP-%04d', $project->id), 'title' => $project->title],
            'reports' => $reports->map(fn (BiweeklyReport $report): array => [
                'id' => $report->id,
                'period_no' => $report->period_no,
                'starts_on' => $report->starts_on->toDateString(),
                'ends_on' => $report->ends_on->toDateString(),
                'meeting_date' => $report->meeting_date?->toDateString(),
                'agenda' => $report->agenda,
                'summary' => $report->summary,
                'planned' => $report->planned,
                'actual' => $report->actual,
                'issues' => $report->issues,
                'decisions' => $report->decisions,
                'status' => $report->status->value,
                'status_label' => $report->status->label(),
                'submitted_by' => $report->submitter?->name,
                'submitted_at' => $report->submitted_at?->toIso8601String(),
                'review_note' => $report->review_note,
                'attendee_ids' => $report->attendees->pluck('id')->all(),
                'attendees' => $report->attendees->pluck('name')->all(),
                'evidence' => $canViewEvidence
                    ? $report->evidenceLinks->map(fn (EvidenceLink $link): array => [
                        'id' => $link->evidence->id,
                        'title' => $link->evidence->title,
                        'original_name' => $link->evidence->original_name,
                        'scan_status' => $link->evidence->scan_status,
                        'download_url' => $link->evidence->scan_status === 'PASSED'
                            ? route('projects.evidence.download', [$project, $link->evidence])
                            : null,
                    ])->all()
                    : [],
                'actions' => $report->actions->map(fn (ActionItem $action): array => [
                    'id' => $action->id,
                    'title' => $action->title,
                    'description' => $action->description,
                    'pic_id' => $action->pic_id,
                    'pic' => $action->pic?->name,
                    'due_date' => $action->due_date === null ? null : Carbon::parse($action->due_date)->toDateString(),
                    'status' => $action->status->value,
                ])->all(),
                'can_edit' => in_array($report->status, self::EDITABLE, true) && $user->can('manageReports', $project),
                'can_submit' => in_array($report->status, self::EDITABLE, true) && $user->can('manageReports', $project),
                'can_review' => $report->status === ReportStatus::Submitted && $user->can('reviewBiweekly', $project),
            ])->all(),
            'members' => $project->members()->wherePivot('status', 'ACTIVE')->wherePivotNull('left_at')->orderBy('users.name')->get(['users.id', 'users.name'])
                ->map(fn (User $member): array => ['value' => (string) $member->id, 'label' => $member->name])->all(),
            'next_period' => ((int) $project->biweeklyReports()->max('period_no')) + 1,
            'can_create' => $user->can('manageReports', $project),
        ]);
    }

    public function store(SaveBiweeklyReportRequest $request, Project $project): RedirectResponse
    {
        DB::transaction(function () use ($request, $project): void {
            Project::query()->whereKey($project->id)->lockForUpdate()->first();

            $report = BiweeklyReport::query()->create([
                ...$request->safe()->except(['attendee_ids', 'actions']),
                'project_id' => $project->id,
                'period_no' => ((int) BiweeklyReport::query()->where('project_id', $project->id)->max('period_no')) + 1,
                'status' => ReportStatus::Draft,
                'created_by' => $request->user()->id,
            ]);
            $report->attendees()->sync($request->validated('attendee_ids', []));
            $this->syncActions($request, $project, $report);

            $this->audit->record('biweekly.created', $report, after: ['period_no' => $report->period_no], subjectLabel: $this->label($project, $report));
        });

        return $this->done($project, 'Laporan bi-weekly disimpan sebagai draf.');
    }

    public function update(SaveBiweeklyReportRequest $request, Project $project, BiweeklyReport $report): RedirectResponse
    {
        DB::transaction(function () use ($request, $project, $report): void {
            $report = $this->lock($project, $report);
            $this->guardEditable($report);

            $report->fill($request->safe()->except(['attendee_ids', 'actions']))->save();
            $report->attendees()->sync($request->validated('attendee_ids', []));
            $this->syncActions($request, $project, $report);

            $this->audit->record('biweekly.updated', $report, subjectLabel: $this->label($project, $report));
        });

        return $this->done($project, 'Laporan bi-weekly diperbarui.');
    }

    public function submit(SaveBiweeklyReportRequest $request, Project $project, BiweeklyReport $report): RedirectResponse
    {
        DB::transaction(function () use ($request, $project, $report): void {
            $report = $this->lock($project, $report);
            $this->guardEditable($report);

            $report->fill($request->safe()->except(['attendee_ids', 'actions']));
            $report->attendees()->sync($request->validated('attendee_ids', []));
            $this->syncActions($request, $project, $report);

            $missing = [];

            foreach (['meeting_date' => 'Tanggal rapat', 'agenda' => 'Agenda', 'summary' => 'Ringkasan', 'planned' => 'Rencana', 'actual' => 'Aktual'] as $field => $label) {
                if (blank($report->{$field})) {
                    $missing[$field] = "{$label} wajib diisi sebelum laporan diajukan.";
                }
            }

            if ($report->attendees()->count() === 0) {
                $missing['attendee_ids'] = 'Pilih minimal satu peserta rapat.';
            }

            if ($missing !== []) {
                throw ValidationException::withMessages($missing);
            }

            $needsReview = (bool) config('qcc.biweekly.require_review');
            $report->forceFill([
                'status' => $needsReview ? ReportStatus::Submitted : ReportStatus::Approved,
                'submitted_by' => $request->user()->id,
                'submitted_at' => now(),
                'review_note' => null,
            ])->save();

            $this->audit->record('biweekly.submitted', $report, after: ['status' => $report->status->value], subjectLabel: $this->label($project, $report));

            if ($needsReview) {
                $this->notifier->notify(
                    $this->notifier->reviewers($project, 'biweekly'),
                    $project,
                    'Laporan bi-weekly baru',
                    "Laporan periode {$report->period_no} proyek {$project->title} menunggu review.",
                    route('projects.biweekly.index', $project, false),
                    'BIWEEKLY_SUBMITTED',
                    $request->user(),
                );
            }
        });

        return $this->done($project, 'Laporan bi-weekly diajukan.');
    }

    public function approve(Request $request, Project $project, BiweeklyReport $report): RedirectResponse
    {
        Gate::authorize('reviewBiweekly', $project);

        return $this->decide($request, $project, $report, ReportStatus::Approved, null);
    }

    public function revision(Request $request, Project $project, BiweeklyReport $report): RedirectResponse
    {
        Gate::authorize('reviewBiweekly', $project);
        $data = $request->validate(['reason' => ['required', 'string', 'max:5000']], ['reason.required' => 'Alasan revisi wajib diisi.']);

        return $this->decide($request, $project, $report, ReportStatus::RevisionRequired, $data['reason']);
    }

    public function destroy(Request $request, Project $project, BiweeklyReport $report): RedirectResponse
    {
        Gate::authorize('manageReports', $project);

        DB::transaction(function () use ($project, $report): void {
            $report = $this->lock($project, $report);

            if ($report->status !== ReportStatus::Draft) {
                throw ValidationException::withMessages(['report' => 'Hanya draf yang bisa dihapus.']);
            }

            $this->audit->record('biweekly.deleted', $report, subjectLabel: $this->label($project, $report));
            $report->delete();
        });

        return $this->done($project, 'Draf laporan dihapus.');
    }

    private function decide(Request $request, Project $project, BiweeklyReport $report, ReportStatus $status, ?string $reason): RedirectResponse
    {
        DB::transaction(function () use ($request, $project, $report, $status, $reason): void {
            $report = $this->lock($project, $report);

            if ($report->status !== ReportStatus::Submitted) {
                throw ValidationException::withMessages(['report' => 'Hanya laporan yang diajukan yang bisa diputuskan.']);
            }

            $report->forceFill(['status' => $status, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $reason])->save();

            $this->audit->record(
                $status === ReportStatus::Approved ? 'biweekly.approved' : 'biweekly.revision_requested',
                $report,
                after: ['status' => $status->value],
                reason: $reason,
                subjectLabel: $this->label($project, $report),
            );

            $this->notifier->notify(
                $report->submitter ? [$report->submitter] : $this->notifier->leaders($project),
                $project,
                $status === ReportStatus::Approved ? 'Laporan bi-weekly disetujui' : 'Laporan bi-weekly perlu revisi',
                $status === ReportStatus::Approved ? "Laporan periode {$report->period_no} disetujui." : "Laporan periode {$report->period_no}: {$reason}",
                route('projects.biweekly.index', $project, false),
                $status === ReportStatus::Approved ? 'BIWEEKLY_APPROVED' : 'BIWEEKLY_REVISION',
                $request->user(),
            );
        });

        return $this->done($project, $status === ReportStatus::Approved ? 'Laporan disetujui.' : 'Permintaan revisi dikirim.');
    }

    private function lock(Project $project, BiweeklyReport $report): BiweeklyReport
    {
        abort_unless($report->project_id === $project->id, 404);

        return BiweeklyReport::query()->lockForUpdate()->findOrFail($report->id);
    }

    private function guardEditable(BiweeklyReport $report): void
    {
        if (! in_array($report->status, self::EDITABLE, true)) {
            throw ValidationException::withMessages(['report' => 'Laporan yang sudah diajukan atau disetujui tidak bisa diubah.']);
        }
    }

    /** @throws ValidationException */
    private function syncActions(SaveBiweeklyReportRequest $request, Project $project, BiweeklyReport $report): void
    {
        if (! $request->exists('actions')) {
            return;
        }

        $actions = $request->validated('actions', []);
        $keptIds = [];

        foreach ($actions as $action) {
            $values = [
                'project_id' => $project->id,
                'source_type' => 'BIWEEKLY_REPORT',
                'source_id' => $report->id,
                'title' => $action['title'],
                'description' => $action['description'] ?? null,
                'pic_id' => $action['pic_id'],
                'due_date' => $action['due_date'],
                'created_by' => $request->user()->id,
            ];

            if (isset($action['id'])) {
                $existing = $report->actions()->whereKey($action['id'])->lockForUpdate()->first();
                if ($existing === null) {
                    throw ValidationException::withMessages(['actions' => 'Tindakan tidak valid untuk laporan ini.']);
                }

                $before = $existing->only(['title', 'description', 'pic_id', 'due_date']);
                $previousPicId = $existing->pic_id;
                $existing->fill(collect($values)->except(['project_id', 'source_type', 'source_id', 'created_by'])->all())->save();
                $keptIds[] = $existing->id;
                $this->audit->record('action_item.updated', $existing, before: $before, after: $existing->only(['title', 'description', 'pic_id', 'due_date']));
                if ($existing->pic_id !== $previousPicId) {
                    $this->notifier->notify(
                        $existing->pic ? [$existing->pic] : [], $project, 'Tindakan bi-weekly ditugaskan', "{$existing->title} ditugaskan kepadamu.",
                        route('projects.biweekly.index', $project, false), 'ACTION_ASSIGNED', $request->user(),
                    );
                }
            } else {
                $created = $report->actions()->create($values);
                $keptIds[] = $created->id;
                $this->audit->record('action_item.created', $created, after: $created->only(['title', 'description', 'pic_id', 'due_date']));
                $this->notifier->notify(
                    $created->pic ? [$created->pic] : [], $project, 'Tindakan bi-weekly ditugaskan', "{$created->title} ditugaskan kepadamu.",
                    route('projects.biweekly.index', $project, false), 'ACTION_ASSIGNED', $request->user(),
                );
            }
        }

        $removed = $report->actions()->whereNotIn('id', $keptIds)->get();
        foreach ($removed as $action) {
            $this->audit->record('action_item.deleted', $action, before: $action->only(['title', 'description', 'pic_id', 'due_date']));
            $action->delete();
        }
    }

    private function label(Project $project, BiweeklyReport $report): string
    {
        return 'Bi-weekly periode '.$report->period_no.' proposal PRP-'.sprintf('%04d', $project->id);
    }

    private function done(Project $project, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('projects.biweekly.index', $project);
    }
}
