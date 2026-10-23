<?php

namespace App\Http\Controllers;

use App\Actions\Proposals\InitializeProjectSteps;
use App\Enums\ActionStatus;
use App\Enums\ProposalStatus;
use App\Enums\QccStep;
use App\Enums\RiskLevel;
use App\Enums\RiskStatus;
use App\Enums\RiskType;
use App\Enums\ScoringStatus;
use App\Enums\StepStatus;
use App\Models\ActionItem;
use App\Models\BiweeklyReport;
use App\Models\MentoringReport;
use App\Models\Project;
use App\Models\ProjectRisk;
use App\Models\ProjectStep;
use App\Models\ScoringRun;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CsvExportService;
use App\Services\ProposalListingQuery;
use App\Services\ScoringListingQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataExportController extends Controller
{
    public function __construct(private readonly CsvExportService $csv) {}

    public function proposals(Request $request, ProposalListingQuery $listing, AuditService $audit): StreamedResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['all', ...array_column(ProposalStatus::cases(), 'value')])],
            'batch' => ['nullable', 'string', 'regex:/^(all|\d+)$/'],
            'sort' => ['nullable', Rule::in(['code', 'title', 'proposal_status', 'updated_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('projects.view'), 403);
        $query = $listing->build($user, $filters);
        $rows = $query->get()->map(fn (Project $project): array => [
            sprintf('PRP-%04d', $project->id), $project->title, $project->batch?->name,
            $project->proposer?->name, $project->proposer?->organization?->name,
            $project->members_count, $project->proposal_status->label(), $project->updated_at?->toDateTimeString(),
        ]);

        return $this->csv->download('daftar_proposal', 'daftar-proposal.csv', [
            'Kode', 'Judul Proposal', 'Batch', 'Pengusul', 'Organisasi', 'Jumlah Anggota', 'Status', 'Diperbarui',
        ], $rows, $rows->count(), $filters, $audit);
    }

    public function projects(Request $request, AuditService $audit): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('projects.view'), 403);
        $query = $this->projectDirectoryQuery($user);
        $rows = $query->get()->map(fn (Project $project): array => [
            sprintf('PRP-%04d', $project->id), $project->title, $project->batch?->name,
            $project->proposer?->name, $project->proposal_status->label(), $project->approved_steps_count,
        ]);

        return $this->csv->download('proyek_qcc', 'daftar-proyek-qcc.csv', [
            'Kode', 'Nama Proyek', 'Batch', 'Pengusul', 'Status Proposal', 'Step Disetujui',
        ], $rows, $rows->count(), [], $audit);
    }

    public function projectStepsDirectory(Request $request, AuditService $audit): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('projects.view'), 403);
        $projects = $this->approvedVisibleProjects($user)->with('steps')->get();
        $rows = $projects->flatMap(fn (Project $project): array => array_map(function (QccStep $definition) use ($project): array {
            $step = $project->steps->firstWhere('step_number', $definition->value);

            return [
                sprintf('PRP-%04d', $project->id), $project->title, $definition->value, $definition->label(),
                $step?->status->label() ?? StepStatus::NotStarted->label(), $step->progress_pct,
                $step?->target_date?->toDateString(),
            ];
        }, QccStep::cases()))->values();

        return $this->csv->download('progress_8_step', 'progress-8-step-qcc.csv', [
            'Kode Proyek', 'Nama Proyek', 'Step', 'Nama Step', 'Status', 'Progres (%)', 'Target Selesai',
        ], $rows, $rows->count(), [], $audit);
    }

    public function projectSteps(Request $request, Project $project, AuditService $audit): StreamedResponse
    {
        Gate::authorize('view', $project);
        abort_unless($project->proposal_status === ProposalStatus::Approved, 404);
        app(InitializeProjectSteps::class)->handle($project);
        $steps = $project->steps()->get();
        $rows = $steps->map(fn (ProjectStep $step): array => [
            sprintf('PRP-%04d', $project->id), $project->title, $step->step_number,
            $step->definition()->label(), $step->status->label(), $step->progress_pct,
            $step->target_date?->toDateString(), $step->submitted_at?->toDateTimeString(),
            $step->approved_at?->toDateTimeString(),
        ]);

        return $this->csv->download('progress_8_step', sprintf('progress-8-step-prp-%04d.csv', $project->id), [
            'Kode Proyek', 'Nama Proyek', 'Step', 'Nama Step', 'Status', 'Progres (%)', 'Target Selesai', 'Diajukan', 'Disetujui',
        ], $rows, $rows->count(), ['project_id' => $project->id], $audit);
    }

    public function biweeklyDirectory(Request $request, AuditService $audit): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('projects.view'), 403);
        $query = BiweeklyReport::query()
            ->with(['project:id,title,batch_id,proposer_id', 'attendees:id,name', 'actions.pic:id,name'])
            ->whereIn('project_id', $this->approvedVisibleProjects($user)->select('id'))
            ->orderBy('project_id')->orderByDesc('period_no');
        $reports = $query->get();
        $rows = $this->reportRows($reports);

        return $this->csv->download('laporan_biweekly', 'laporan-biweekly.csv', $this->reportHeaders(), $rows, count($rows), [], $audit);
    }

    public function biweekly(Request $request, Project $project, AuditService $audit): StreamedResponse
    {
        Gate::authorize('view', $project);
        $reports = $project->biweeklyReports()->with(['attendees:id,name', 'actions.pic:id,name'])->limit(100)->get();
        $rows = $this->reportRows($reports, $project);

        return $this->csv->download('laporan_biweekly', sprintf('laporan-biweekly-prp-%04d.csv', $project->id), $this->reportHeaders(), $rows, count($rows), ['project_id' => $project->id], $audit);
    }

    public function mentoringDirectory(Request $request, AuditService $audit): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('projects.view'), 403);
        $reports = MentoringReport::query()
            ->with(['project:id,title,batch_id,proposer_id', 'assignment.mentor:id,name', 'actions.pic:id,name'])
            ->whereIn('project_id', $this->approvedVisibleProjects($user)->select('id'))
            ->orderBy('project_id')->orderByDesc('session_at')->get();
        $rows = $this->reportRows($reports);

        return $this->csv->download('laporan_mentoring', 'laporan-mentoring.csv', $this->reportHeaders(), $rows, count($rows), [], $audit);
    }

    public function mentoring(Request $request, Project $project, AuditService $audit): StreamedResponse
    {
        Gate::authorize('view', $project);
        $reports = MentoringReport::query()->where('project_id', $project->id)
            ->with(['assignment.mentor:id,name', 'actions.pic:id,name'])
            ->orderByDesc('session_at')->limit(100)->get();
        $rows = $this->reportRows($reports, $project);

        return $this->csv->download('laporan_mentoring', sprintf('laporan-mentoring-prp-%04d.csv', $project->id), $this->reportHeaders(), $rows, count($rows), ['project_id' => $project->id], $audit);
    }

    public function risksDirectory(Request $request, AuditService $audit): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('projects.view'), 403);
        $projects = $this->approvedVisibleProjects($user)->select('id');
        $risks = ProjectRisk::query()->with(['project:id,title', 'pic:id,name'])->whereIn('project_id', $projects)->get();
        $actions = ActionItem::query()->with(['project:id,title', 'pic:id,name'])->whereIn('project_id', $projects)->get();
        $rows = $this->riskRows($risks, $actions);

        return $this->csv->download('risiko_dan_tindakan', 'risiko-isu-dan-tindakan.csv', $this->riskHeaders(), $rows, count($rows), [], $audit);
    }

    public function risks(Request $request, Project $project, AuditService $audit): StreamedResponse
    {
        Gate::authorize('view', $project);
        $filters = $request->validate([
            'tab' => ['nullable', Rule::in(['risks', 'actions'])],
            'type' => ['nullable', Rule::in(['all', ...array_column(RiskType::cases(), 'value')])],
            'status' => ['nullable', 'string', 'max:16'],
            'priority' => ['nullable', Rule::in(['all', ...array_column(RiskLevel::cases(), 'value')])],
            'pic' => ['nullable', 'string', 'regex:/^(all|\d+)$/'],
            'overdue' => ['nullable', 'boolean'],
        ]);
        $state = [
            'tab' => $filters['tab'] ?? 'risks', 'type' => $filters['type'] ?? 'all',
            'status' => $filters['status'] ?? 'all', 'priority' => $filters['priority'] ?? 'all',
            'pic' => $filters['pic'] ?? 'all', 'overdue' => (bool) ($filters['overdue'] ?? false),
        ];
        $today = now()->toDateString();
        $risks = $project->risks()->with(['project:id,title', 'pic:id,name'])
            ->when($state['type'] !== 'all', fn (Builder $query): Builder => $query->where('type', $state['type']))
            ->when($state['tab'] === 'risks' && $state['status'] !== 'all', fn (Builder $query): Builder => $query->where('status', $state['status']))
            ->when($state['priority'] !== 'all', fn (Builder $query): Builder => $query->where('priority', $state['priority']))
            ->when($state['tab'] === 'risks' && $state['pic'] !== 'all', fn (Builder $query): Builder => $query->where('pic_id', (int) $state['pic']))
            ->when($state['tab'] === 'risks' && $state['overdue'], fn (Builder $query): Builder => $query->where('due_date', '<', $today)->whereNotIn('status', [RiskStatus::Resolved->value, RiskStatus::Closed->value]))
            ->orderBy('due_date')->orderBy('id')->limit(200)->get();
        $actions = $project->actionItems()->with(['project:id,title', 'pic:id,name'])
            ->when($state['tab'] === 'actions' && $state['status'] !== 'all', fn (Builder $query): Builder => $query->where('status', $state['status']))
            ->when($state['tab'] === 'actions' && $state['pic'] !== 'all', fn (Builder $query): Builder => $query->where('pic_id', (int) $state['pic']))
            ->when($state['tab'] === 'actions' && $state['overdue'], fn (Builder $query): Builder => $query->where('due_date', '<', $today)->whereNotIn('status', [ActionStatus::Completed->value, ActionStatus::Cancelled->value]))
            ->orderBy('due_date')->orderBy('id')->limit(200)->get();
        $rows = $state['tab'] === 'risks'
            ? $this->riskRows($risks, [])
            : $this->riskRows([], $actions);

        return $this->csv->download('risiko_dan_tindakan', sprintf('risiko-isu-prp-%04d.csv', $project->id), $this->riskHeaders(), $rows, count($rows), $state, $audit);
    }

    public function scoring(Request $request, ScoringListingQuery $listing, AuditService $audit): StreamedResponse
    {
        Gate::authorize('viewAny', ScoringRun::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_map(static fn (ScoringStatus $status): string => $status->value, ScoringStatus::cases()))],
            'sort' => ['nullable', Rule::in(['project_title', 'status', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
        $query = $listing->build($request->user(), $filters);
        $runs = $query->get();
        $rows = $runs->map(fn (ScoringRun $run): array => [
            $run->project_title ?? "Proyek #{$run->project_id}", $run->rubric_name ?? 'Rubrik',
            $run->status->label(), $run->result?->ai_weighted_score,
            $run->result?->reviewer_weighted_score, $run->result?->final_score,
            $run->result?->reviewer?->name, Carbon::parse($run->created_at)->toDateTimeString(),
        ]);

        return $this->csv->download('hasil_scoring', 'hasil-scoring.csv', [
            'Proyek', 'Rubrik', 'Status Scoring', 'Skor AI', 'Skor Reviewer', 'Skor Final', 'Reviewer', 'Dibuat',
        ], $rows, $rows->count(), $filters, $audit);
    }

    /** @return Builder<Project> */
    private function projectDirectoryQuery(User $user): Builder
    {
        return Project::query()
            ->select(['id', 'batch_id', 'proposer_id', 'title', 'proposal_status', 'updated_at'])
            ->visibleTo($user)
            ->with(['batch:id,name', 'proposer:id,name'])
            ->withCount(['steps as approved_steps_count' => fn (Builder $query): Builder => $query->where('status', StepStatus::Approved->value)])
            ->orderByDesc('updated_at')->orderByDesc('id');
    }

    /** @return Builder<Project> */
    private function approvedVisibleProjects(User $user): Builder
    {
        return Project::query()->visibleTo($user)->where('proposal_status', ProposalStatus::Approved->value);
    }

    /** @param iterable<BiweeklyReport|MentoringReport> $reports
     * @return list<array<int, mixed>>
     */
    private function reportRows(iterable $reports, ?Project $project = null): array
    {
        $rows = [];

        foreach ($reports as $report) {
            $reportActions = $report->actions;
            $projectId = $project->id ?? $report->project_id;
            $projectTitle = $project->title ?? $report->project->title;
            $base = $report instanceof BiweeklyReport
                ? [sprintf('PRP-%04d', $projectId), $projectTitle,
                    'Bi-weekly', $report->period_no, $report->starts_on->toDateString(), $report->ends_on->toDateString(),
                    $report->meeting_date?->toDateString(), $report->agenda, $report->summary, $report->planned,
                    $report->actual, $report->issues, $report->decisions,
                    $report->attendees->pluck('name')->implode(', '), $report->status->label()]
                : [sprintf('PRP-%04d', $projectId), $projectTitle,
                    'Mentoring', Carbon::parse($report->session_at)->toDateTimeString(), null, null, null,
                    $report->topic, $report->findings, $report->suggestions, $report->next_steps, null,
                    $report->team_response, $report->assignment?->mentor?->name, $report->status->label()];

            if ($reportActions->isEmpty()) {
                $rows[] = [...$base, null, null, null, null];

                continue;
            }

            foreach ($reportActions as $action) {
                $rows[] = [...$base, $action->title, $action->pic?->name, $action->due_date?->toDateString(), $action->status->label()];
            }
        }

        return $rows;
    }

    /** @return list<string> */
    private function reportHeaders(): array
    {
        return ['Kode Proyek', 'Nama Proyek', 'Jenis Laporan', 'Periode / Sesi', 'Awal Periode', 'Akhir Periode', 'Tanggal Rapat',
            'Agenda / Topik', 'Ringkasan / Temuan', 'Rencana / Saran', 'Realisasi / Langkah Berikutnya', 'Isu', 'Keputusan / Tanggapan',
            'Peserta / Mentor', 'Status Laporan', 'Action Item', 'PIC', 'Tenggat', 'Status Action Item'];
    }

    /** @param iterable<ProjectRisk> $risks
     * @param  iterable<ActionItem>  $actions
     * @return list<array<int, mixed>>
     */
    private function riskRows(iterable $risks, iterable $actions): array
    {
        $rows = [];

        foreach ($risks as $risk) {
            $rows[] = [sprintf('PRP-%04d', $risk->project_id), $risk->project?->title, $risk->type->label(), $risk->title,
                $risk->description, $risk->impact, $risk->likelihood->label(), $risk->priority->label(), $risk->mitigation,
                $risk->pic?->name, $risk->due_date?->toDateString(), $risk->status->label(), null, null, null, null];
        }

        foreach ($actions as $action) {
            $rows[] = [sprintf('PRP-%04d', $action->project_id), $action->project?->title, 'Tindakan', null, null, null, null, null, null,
                $action->pic?->name, $action->due_date?->toDateString(), null, $action->title, $action->description, $action->status->label(), $action->completed_at?->toDateTimeString()];
        }

        return $rows;
    }

    /** @return list<string> */
    private function riskHeaders(): array
    {
        return ['Kode Proyek', 'Nama Proyek', 'Jenis', 'Risiko / Isu', 'Deskripsi', 'Dampak', 'Kemungkinan', 'Prioritas', 'Mitigasi',
            'PIC', 'Tenggat', 'Status', 'Action Item', 'Deskripsi Tindakan', 'Status Tindakan', 'Selesai Pada'];
    }
}
