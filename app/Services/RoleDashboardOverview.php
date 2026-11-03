<?php

namespace App\Services;

use App\Enums\ActionStatus;
use App\Enums\ProposalStatus;
use App\Enums\QccStep;
use App\Enums\ReportStatus;
use App\Enums\StepStatus;
use App\Models\ActionItem;
use App\Models\BiweeklyReport;
use App\Models\MentoringReport;
use App\Models\Project;
use App\Models\ProjectStep;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class RoleDashboardOverview
{
    /**
     * @return array{
     *     summary: array{pending_tasks: int, proposals: int, active_projects: int, average_progress: int|null, biweekly_on_time: int, biweekly_late: int, biweekly_overdue: int, mentoring_sessions: int, mentoring_feedback_pending: int},
     *     monitoring: array{biweekly_reports: list<array<string, mixed>>, step_statuses: list<array{status: string, label: string, count: int}>, steps: list<array<string, mixed>>, mentoring_reports: list<array<string, mixed>>},
     *     status_counts: list<array{status: string, label: string, count: int}>, projects: list<array<string, mixed>>, tasks: list<array<string, mixed>>
     * }
     */
    public function get(User $user, ?int $batchId = null, ?int $periodDays = null): array
    {
        $today = today(config('app.timezone'));
        $periodStart = $periodDays === null
            ? null
            : $today->copy()->subDays(max(1, $periodDays) - 1)->toDateString();
        $accessibleProjects = fn (): Builder => Project::query()
            ->visibleTo($user)
            ->when($batchId !== null, fn (Builder $query): Builder => $query->where('batch_id', $batchId));
        $visibleProjects = fn (): Builder => $accessibleProjects()
            ->when($periodStart !== null, fn (Builder $query): Builder => $query
                ->whereDate('projects.created_at', '>=', $periodStart)
                ->whereDate('projects.created_at', '<=', $today->toDateString()));
        $activeProjects = fn (): Builder => $visibleProjects()->where('proposal_status', ProposalStatus::Approved->value);
        $tasks = $this->pendingTasks($user, $accessibleProjects, $batchId);
        $averageProgress = ProjectStep::query()
            ->whereIn('project_id', $activeProjects()->select('projects.id'))
            ->avg('progress_pct');
        $reportProjectIds = $accessibleProjects()
            ->where('proposal_status', ProposalStatus::Approved->value)
            ->select('projects.id');
        $submittedReports = BiweeklyReport::query()->whereIn('project_id', $reportProjectIds)->whereNotNull('submitted_at');
        if ($periodStart !== null) {
            $submittedReports->whereDate('ends_on', '>=', $periodStart)->whereDate('ends_on', '<=', $today->toDateString());
        }
        $overdueReports = BiweeklyReport::query()
            ->whereIn('project_id', $reportProjectIds)
            ->whereNull('submitted_at')
            ->whereDate('ends_on', '<', $today->toDateString())
            ->whereIn('status', [ReportStatus::Draft->value, ReportStatus::RevisionRequired->value]);
        if ($periodStart !== null) {
            $overdueReports->whereDate('ends_on', '>=', $periodStart);
        }
        $mentoringReports = MentoringReport::query()
            ->whereIn('project_id', $reportProjectIds)
            ->when($periodStart !== null, fn (Builder $query): Builder => $query
                ->whereDate('session_at', '>=', $periodStart)
                ->whereDate('session_at', '<=', $today->toDateString()));
        $activeProjectIds = $activeProjects()->select('projects.id');
        $stepStatusCounts = ProjectStep::query()
            ->whereIn('project_id', $activeProjectIds)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $recentBiweeklyReports = BiweeklyReport::query()
            ->with('project:id,title')
            ->whereIn('project_id', $reportProjectIds)
            ->when($periodStart !== null, fn (Builder $query): Builder => $query
                ->whereDate('ends_on', '>=', $periodStart)
                ->whereDate('ends_on', '<=', $today->toDateString()))
            ->orderByDesc('ends_on')
            ->orderByDesc('period_no')
            ->limit(5)
            ->get();
        $recentSteps = ProjectStep::query()
            ->with('project:id,title')
            ->whereIn('project_id', $activeProjectIds)
            ->latest('updated_at')
            ->limit(5)
            ->get();
        $recentMentoringReports = (clone $mentoringReports)
            ->with('project:id,title')
            ->latest('session_at')
            ->limit(5)
            ->get();
        $projectCounts = $visibleProjects()->selectRaw('proposal_status, COUNT(*) as total')
            ->groupBy('proposal_status')
            ->pluck('total', 'proposal_status');

        $projects = array_values($visibleProjects()
            ->select(['id', 'title', 'proposal_status', 'updated_at'])
            ->withCount(['steps as steps_total'])
            ->withCount(['steps as steps_completed' => fn (Builder $steps): Builder => $steps->where('status', StepStatus::Approved->value)])
            ->withAvg('steps', 'progress_pct')
            ->latest('updated_at')
            ->limit(5)
            ->get()
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'title' => $project->title,
                'status' => $project->proposal_status->value,
                'status_label' => $project->proposal_status->label(),
                'steps_total' => $project->steps_total,
                'steps_completed' => $project->steps_completed,
                'progress' => $project->steps_avg_progress_pct === null ? null : (int) round((float) $project->steps_avg_progress_pct),
                'href' => route($project->proposal_status === ProposalStatus::Approved ? 'projects.steps.show' : 'proposals.show', ['project' => $project->id]),
            ])
            ->values()
            ->all());

        return [
            'summary' => [
                'pending_tasks' => $tasks['count'],
                'proposals' => $visibleProjects()->whereIn('proposal_status', [ProposalStatus::Draft->value, ProposalStatus::Submitted->value, ProposalStatus::UnderReview->value, ProposalStatus::RevisionRequired->value])->count(),
                'active_projects' => $activeProjects()->count(),
                'average_progress' => $averageProgress === null ? null : (int) round((float) $averageProgress),
                'biweekly_on_time' => (clone $submittedReports)->whereRaw('DATE(submitted_at) <= ends_on')->count(),
                'biweekly_late' => (clone $submittedReports)->whereRaw('DATE(submitted_at) > ends_on')->count(),
                'biweekly_overdue' => $overdueReports->count(),
                'mentoring_sessions' => (clone $mentoringReports)->count(),
                'mentoring_feedback_pending' => (clone $mentoringReports)->where('status', ReportStatus::Submitted->value)->whereNull('acknowledged_at')->count(),
            ],
            'monitoring' => [
                'biweekly_reports' => $recentBiweeklyReports->map(fn (BiweeklyReport $report): array => [
                    'id' => $report->id,
                    'project' => $report->project->title,
                    'period_no' => $report->period_no,
                    'ends_on' => $report->ends_on->toDateString(),
                    'status' => $report->status->value,
                    'status_label' => $report->status->label(),
                    'overdue' => $report->submitted_at === null && $report->ends_on->lt($today),
                    'href' => route('projects.biweekly.index', $report->project_id),
                ])->all(),
                'step_statuses' => array_map(fn (StepStatus $status): array => [
                    'status' => $status->value,
                    'label' => $status->label(),
                    'count' => (int) ($stepStatusCounts[$status->value] ?? 0),
                ], StepStatus::cases()),
                'steps' => $recentSteps->map(fn (ProjectStep $step): array => [
                    'project' => $step->project->title,
                    'step_number' => $step->step_number,
                    'step_label' => QccStep::from($step->step_number)->label(),
                    'status' => $step->status->value,
                    'status_label' => $step->status->label(),
                    'progress' => $step->progress_pct,
                    'target_date' => $step->target_date?->toDateString(),
                    'href' => route('projects.steps.show', ['project' => $step->project_id, 'step' => $step->step_number]),
                ])->all(),
                'mentoring_reports' => $recentMentoringReports->map(fn (MentoringReport $report): array => [
                    'project' => $report->project->title,
                    'topic' => $report->topic,
                    'session_at' => $report->session_at->toDateString(),
                    'status' => $report->status->value,
                    'status_label' => $report->status->label(),
                    'feedback_pending' => $report->status === ReportStatus::Submitted && $report->acknowledged_at === null,
                    'href' => route('projects.mentoring.index', $report->project_id),
                ])->all(),
            ],
            'status_counts' => array_map(fn (ProposalStatus $status): array => [
                'status' => $status->value,
                'label' => $status->label(),
                'count' => (int) ($projectCounts[$status->value] ?? 0),
            ], ProposalStatus::cases()),
            'projects' => $projects,
            'tasks' => $tasks['items'],
        ];
    }

    /**
     * @param  callable(): Builder<Project>  $visibleProjects
     * @return array{count: int, items: list<array<string, mixed>>}
     */
    private function pendingTasks(User $user, callable $visibleProjects, ?int $batchId = null): array
    {
        $items = [];
        $count = 0;
        $visibleProjectIds = $visibleProjects()->select('projects.id');

        if ($user->can('projects.review')) {
            $proposals = $visibleProjects()
                ->whereIn('proposal_status', [ProposalStatus::Submitted->value, ProposalStatus::UnderReview->value]);
            $count += (clone $proposals)->count();
            $items = [...$items, ...(clone $proposals)->select(['id', 'title', 'submitted_at'])->latest('submitted_at')->limit(5)->get()->map(fn (Project $project): array => [
                'key' => 'proposal-'.$project->id,
                'title' => 'Review proposal',
                'project' => $project->title,
                'detail' => 'Proposal menunggu keputusan review.',
                'href' => route('proposals.show', $project),
                'date' => $project->submitted_at?->toDateString(),
            ])->all()];
        }

        if ($user->can('projects.viewAny') && $user->can('projects.review')) {
            $teamPendingProposals = Project::query()
                ->when($batchId !== null, fn (Builder $query): Builder => $query->where('batch_id', $batchId))
                ->where('proposal_status', ProposalStatus::Draft->value)
                ->whereHas('proposer.roles', fn (Builder $roles): Builder => $roles->where('name', 'SIMer / Member'))
                ->whereDoesntHave('proposer.roles', fn (Builder $roles): Builder => $roles->where('name', 'Team Leader'))
                ->whereNotExists(fn ($query) => $query->selectRaw('1')
                    ->from('team_matching_runs')
                    ->whereColumn('team_matching_runs.project_id', 'projects.id')
                    ->whereNotNull('team_matching_runs.confirmed_at'));
            $count += (clone $teamPendingProposals)->count();
            $items = [...$items, ...(clone $teamPendingProposals)->select(['id', 'title', 'updated_at'])->latest('updated_at')->limit(5)->get()->map(fn (Project $project): array => [
                'key' => 'team-composition-'.$project->id,
                'title' => 'Proposal menunggu penyusunan tim',
                'project' => $project->title,
                'detail' => 'Susun dan konfirmasi tim sebelum proposal diajukan.',
                'href' => route('proposals.candidates', $project),
                'date' => $project->updated_at?->toDateString(),
            ])->all()];
        }

        if ($user->can('projects.approve')) {
            $steps = ProjectStep::query()
                ->with('project:id,title')
                ->whereIn('project_id', $visibleProjectIds)
                ->whereIn('status', [StepStatus::Submitted->value, StepStatus::UnderReview->value]);
            $count += (clone $steps)->count();
            $items = [...$items, ...(clone $steps)->latest('submitted_at')->limit(5)->get()->map(fn (ProjectStep $step): array => [
                'key' => 'step-'.$step->id,
                'title' => 'Review Step '.$step->step_number,
                'project' => $step->project->title,
                'detail' => 'Langkah QCC menunggu review.',
                'href' => route('projects.steps.show', ['project' => $step->project_id, 'step' => $step->step_number]),
                'date' => $step->submitted_at?->toDateString(),
            ])->all()];
        }

        $actions = ActionItem::query()
            ->with('project:id,title')
            ->whereBelongsTo($user, 'pic')
            ->whereIn('project_id', $visibleProjectIds)
            ->whereIn('status', [ActionStatus::Open->value, ActionStatus::InProgress->value, ActionStatus::Blocked->value]);
        $count += (clone $actions)->count();
        $items = [...$items, ...(clone $actions)->orderBy('due_date')->limit(5)->get()->map(fn (ActionItem $action): array => [
            'key' => 'action-'.$action->id,
            'title' => $action->title,
            'project' => $action->project->title,
            'detail' => $action->status->label(),
            'href' => route('projects.risks.index', $action->project_id),
            'date' => $action->due_date?->toDateString(),
        ])->all()];

        if ($user->can('projects.update')) {
            $revisions = $visibleProjects()
                ->where('proposer_id', $user->id)
                ->where('proposal_status', ProposalStatus::RevisionRequired->value);
            $count += (clone $revisions)->count();
            $items = [...$items, ...(clone $revisions)->select(['id', 'title', 'updated_at'])->latest('updated_at')->limit(5)->get()->map(fn (Project $project): array => [
                'key' => 'revision-'.$project->id,
                'title' => 'Revisi proposal',
                'project' => $project->title,
                'detail' => 'Proposal perlu diperbaiki dan diajukan kembali.',
                'href' => route('proposals.show', $project),
                'date' => $project->updated_at?->toDateString(),
            ])->all()];
        }

        usort($items, fn (array $left, array $right): int => (string) ($left['date'] ?? '') <=> (string) ($right['date'] ?? ''));

        return [
            'count' => $count,
            'items' => array_slice($items, 0, 8),
        ];
    }
}
