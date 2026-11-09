<?php

namespace App\Services;

use App\Enums\ActionStatus;
use App\Enums\ProposalStatus;
use App\Enums\QccStep;
use App\Enums\ReportStatus;
use App\Enums\RiskStatus;
use App\Enums\StepStatus;
use App\Models\ActionItem;
use App\Models\BiweeklyReport;
use App\Models\MemberCompetency;
use App\Models\MentoringReport;
use App\Models\Project;
use App\Models\ProjectRisk;
use App\Models\ProjectStep;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The simple dashboard of a SIMer or Team Leader: where they are in the QCC flow, what to do
 * next, their own projects, proposals and competencies. Leader tasks follow the role the person
 * has in each project, not their account role.
 */
class MemberDashboardOverview
{
    /** Steps due within this many days already show up as a task. */
    private const STEP_DUE_SOON_DAYS = 7;

    /** The dashboard pages and searches tasks itself, so all of them are sent, within reason. */
    private const TASK_LIMIT = 200;

    /**
     * @return array{
     *     journey: array{steps: list<array{key: string, label: string, state: string}>, next_action: array{label: string, description: string, href: string}|null},
     *     competencies: array{count: int, limit: int, href: string},
     *     projects: list<array<string, mixed>>,
     *     proposals: list<array<string, mixed>>,
     *     tasks: list<array<string, mixed>>,
     *     task_count: int
     * }
     */
    public function get(User $user): array
    {
        $today = today(config('app.timezone'));
        $competencyCount = $user->memberCompetencies()->count();

        $memberships = $this->activeProjects($user);
        $proposals = Project::query()
            ->where('proposer_id', $user->id)
            ->latest('updated_at')
            ->get(['id', 'title', 'proposal_status', 'updated_at']);

        $tasks = $this->tasks($user, $memberships, $proposals, $today);

        return [
            'journey' => $this->journey($user, $competencyCount, $memberships, $proposals),
            'competencies' => [
                'count' => $competencyCount,
                'limit' => MemberCompetency::MAX_PER_USER,
                'href' => route('my-competencies.index'),
            ],
            'projects' => array_values($memberships->map(fn (Project $project): array => $this->projectCard($project))->all()),
            'proposals' => array_values($proposals->take(5)->map(fn (Project $project): array => [
                'id' => $project->id,
                'code' => sprintf('PRP-%04d', $project->id),
                'title' => $project->title,
                'status' => $project->proposal_status->value,
                'status_label' => $project->proposal_status->label(),
                'updated_at' => $project->updated_at?->toIso8601String(),
                'action_label' => $this->isEditable($project) ? ($project->proposal_status === ProposalStatus::RevisionRequired ? 'Perbaiki' : 'Lanjutkan') : 'Lihat',
                'href' => $this->isEditable($project) ? route('proposals.edit', $project) : route('proposals.show', $project),
            ])->all()),
            'tasks' => array_slice($tasks, 0, self::TASK_LIMIT),
            'task_count' => count($tasks),
        ];
    }

    /**
     * Approved projects the person works on now, with their role and steps.
     *
     * @return Collection<int, Project>
     */
    private function activeProjects(User $user): Collection
    {
        return Project::query()
            ->join('project_members', 'project_members.project_id', '=', 'projects.id')
            ->where('project_members.user_id', $user->id)
            ->where('project_members.status', 'ACTIVE')
            ->whereNull('project_members.left_at')
            ->where('projects.proposal_status', ProposalStatus::Approved->value)
            ->with(['steps' => fn ($steps) => $steps->orderBy('step_number')])
            ->orderByDesc('projects.approved_at')
            ->get(['projects.id', 'projects.title', 'projects.approved_at', 'project_members.role_in_project']);
    }

    /**
     * Isi kompetensi > Ajukan Proposal > Seleksi Proposal > Proposal Lolos/Tidak Lolos. The last
     * step names the outcome once there is one: an approved proposal (or a project the person
     * already works on) wins over a rejected one, and a proposal still in selection keeps the
     * person at the selection step.
     *
     * @param  Collection<int, Project>  $memberships
     * @param  Collection<int, Project>  $proposals
     * @return array{steps: list<array{key: string, label: string, state: string}>, next_action: array{label: string, description: string, href: string}|null}
     */
    private function journey(User $user, int $competencyCount, Collection $memberships, Collection $proposals): array
    {
        $hasStatus = fn (ProposalStatus ...$statuses): bool => $proposals->contains(fn (Project $project): bool => in_array($project->proposal_status, $statuses, true));
        $hasCompetency = $competencyCount > 0;
        $passed = $memberships->isNotEmpty() || $hasStatus(ProposalStatus::Approved);
        $inSelection = ! $passed && $hasStatus(ProposalStatus::Submitted, ProposalStatus::UnderReview, ProposalStatus::RevisionRequired);
        $failed = ! $passed && ! $inSelection && $hasStatus(ProposalStatus::Rejected);

        $done = [
            'competencies' => $hasCompetency,
            'proposal' => $passed || $inSelection || $failed,
            'selection' => $passed || $failed,
            'result' => $passed,
        ];
        $labels = [
            'competencies' => 'Isi kompetensi',
            'proposal' => 'Ajukan Proposal',
            'selection' => 'Seleksi Proposal',
            'result' => match (true) {
                $passed => 'Proposal Lolos',
                $failed => 'Proposal Tidak Lolos',
                default => 'Proposal Lolos/Tidak Lolos',
            },
        ];
        $current = collect(array_keys($done))->first(fn (string $key): bool => ! $done[$key]);

        $steps = array_map(fn (string $key): array => [
            'key' => $key,
            'label' => $labels[$key],
            'state' => match (true) {
                $done[$key] => 'done',
                $key === 'result' && $failed => 'rejected',
                $key === $current => 'current',
                default => 'todo',
            },
        ], array_keys($labels));

        return ['steps' => $steps, 'next_action' => $this->nextAction($user, $hasCompetency, $memberships, $proposals)];
    }

    /**
     * The single most useful thing to do now, most urgent first.
     *
     * @param  Collection<int, Project>  $memberships
     * @param  Collection<int, Project>  $proposals
     * @return array{label: string, description: string, href: string}|null
     */
    private function nextAction(User $user, bool $hasCompetency, Collection $memberships, Collection $proposals): ?array
    {
        $firstWithStatus = fn (ProposalStatus ...$statuses): ?Project => $proposals->first(fn (Project $project): bool => in_array($project->proposal_status, $statuses, true));

        if (! $hasCompetency) {
            return ['label' => 'Isi kompetensi', 'description' => 'Masuk Talent Pool supaya Owner QCC bisa memilih Anda saat menyusun tim.', 'href' => route('my-competencies.index')];
        }

        if ($revision = $firstWithStatus(ProposalStatus::RevisionRequired)) {
            return ['label' => 'Perbaiki revisi proposal', 'description' => "Owner QCC meminta revisi untuk \"{$revision->title}\".", 'href' => route('proposals.edit', $revision)];
        }

        if ($project = $memberships->first()) {
            $step = $this->currentStep($project);

            return [
                'label' => 'Buka proyek',
                'description' => $step ? "Lanjutkan Step {$step->step_number} · ".QccStep::from($step->step_number)->label().'.' : 'Semua step QCC sudah disetujui.',
                'href' => route('projects.steps.show', ['project' => $project->id, 'step' => $step?->step_number]),
            ];
        }

        if ($draft = $firstWithStatus(ProposalStatus::Draft)) {
            return ['label' => 'Lanjutkan draft proposal', 'description' => "Lengkapi dan ajukan \"{$draft->title}\".", 'href' => route('proposals.edit', $draft)];
        }

        if ($waiting = $firstWithStatus(ProposalStatus::Submitted, ProposalStatus::UnderReview)) {
            return ['label' => 'Lihat status proposal', 'description' => 'Owner QCC sedang menyeleksi proposal dan menyusun tim.', 'href' => route('proposals.show', $waiting)];
        }

        if ($user->can('projects.create')) {
            return ['label' => 'Buat proposal QCC', 'description' => 'Ajukan ide perbaikan Anda untuk QCC Batch yang sedang berjalan.', 'href' => route('proposals.create')];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function projectCard(Project $project): array
    {
        $step = $this->currentStep($project);

        return [
            'id' => $project->id,
            'title' => $project->title,
            'role' => $project->getAttribute('role_in_project') === 'LEADER' ? 'Leader' : 'Anggota',
            'steps_total' => $project->steps->count(),
            'steps_completed' => $project->steps->where('status', StepStatus::Approved)->count(),
            'current_step' => $step === null ? null : [
                'number' => $step->step_number,
                'label' => QccStep::from($step->step_number)->label(),
                'status_label' => $step->status->label(),
                'target_date' => $step->target_date?->toDateString(),
                'progress' => (int) $step->progress_pct,
            ],
            'href' => route('projects.steps.show', ['project' => $project->id, 'step' => $step?->step_number]),
        ];
    }

    private function currentStep(Project $project): ?ProjectStep
    {
        return $project->steps->first(fn (ProjectStep $step): bool => $step->status !== StepStatus::Approved);
    }

    private function isEditable(Project $project): bool
    {
        return in_array($project->proposal_status, [ProposalStatus::Draft, ProposalStatus::RevisionRequired], true);
    }

    /**
     * Only work that waits on this person. Leader duties come from projects they lead.
     *
     * @param  Collection<int, Project>  $memberships
     * @param  Collection<int, Project>  $proposals
     * @return list<array{key: string, title: string, project: string, detail: string, href: string, date: string|null, overdue: bool}>
     */
    private function tasks(User $user, Collection $memberships, Collection $proposals, CarbonInterface $today): array
    {
        $task = fn (string $key, string $title, string $project, string $detail, string $href, ?CarbonInterface $date): array => [
            'key' => $key,
            'title' => $title,
            'project' => $project,
            'detail' => $detail,
            'href' => $href,
            'date' => $date?->toDateString(),
            'overdue' => $date !== null && $date->lt($today),
        ];
        $items = [];

        foreach ($proposals->where('proposal_status', ProposalStatus::RevisionRequired) as $proposal) {
            $items[] = $task('revision-'.$proposal->id, 'Perbaiki revisi proposal', $proposal->title, 'Perbaiki lalu ajukan kembali.', route('proposals.edit', $proposal), null);
        }

        $projectIds = $memberships->pluck('id');

        ActionItem::query()->with('project:id,title')
            ->whereIn('project_id', $projectIds)
            ->where('pic_id', $user->id)
            ->whereIn('status', [ActionStatus::Open->value, ActionStatus::InProgress->value, ActionStatus::Blocked->value])
            ->orderBy('due_date')->get()
            ->each(function (ActionItem $action) use (&$items, $task): void {
                $items[] = $task('action-'.$action->id, $action->title, $action->project->title, 'Action item · '.$action->status->label(), route('projects.risks.index', $action->project_id), $action->due_date);
            });

        ProjectRisk::query()->with('project:id,title')
            ->whereIn('project_id', $projectIds)
            ->where('pic_id', $user->id)
            ->whereIn('status', [RiskStatus::Open->value, RiskStatus::InProgress->value])
            ->orderBy('due_date')->get()
            ->each(function (ProjectRisk $risk) use (&$items, $task): void {
                $items[] = $task('risk-'.$risk->id, (string) $risk->getAttribute('title'), $risk->project->title, 'Risiko yang Anda tangani', route('projects.risks.index', $risk->project_id), $risk->due_date);
            });

        $led = $memberships->filter(fn (Project $project): bool => $project->getAttribute('role_in_project') === 'LEADER');

        foreach ($led as $project) {
            foreach ($project->steps->where('status', StepStatus::RevisionRequired) as $step) {
                $items[] = $task('step-revision-'.$step->id, "Perbaiki Step {$step->step_number}", $project->title, 'Step perlu direvisi lalu diajukan kembali.', route('projects.steps.show', ['project' => $project->id, 'step' => $step->step_number]), $step->target_date);
            }

            $step = $this->currentStep($project);

            if ($step !== null
                && in_array($step->status, [StepStatus::NotStarted, StepStatus::InProgress], true)
                && $step->target_date !== null
                && $step->target_date->lte($today->copy()->addDays(self::STEP_DUE_SOON_DAYS))) {
                $items[] = $task('step-due-'.$step->id, "Ajukan Step {$step->step_number}", $project->title, QccStep::from($step->step_number)->label(), route('projects.steps.show', ['project' => $project->id, 'step' => $step->step_number]), $step->target_date);
            }
        }

        $ledIds = $led->pluck('id');

        BiweeklyReport::query()->with('project:id,title')
            ->whereIn('project_id', $ledIds)
            ->whereNull('submitted_at')
            ->whereDate('ends_on', '<', $today->toDateString())
            ->whereIn('status', [ReportStatus::Draft->value, ReportStatus::RevisionRequired->value])
            ->orderBy('ends_on')->get()
            ->each(function (BiweeklyReport $report) use (&$items, $task): void {
                $items[] = $task('biweekly-'.$report->id, "Kirim laporan bi-weekly ke-{$report->period_no}", $report->project->title, 'Periode sudah berakhir.', route('projects.biweekly.index', $report->project_id), $report->ends_on);
            });

        MentoringReport::query()->with('project:id,title')
            ->whereIn('project_id', $ledIds)
            ->where('status', ReportStatus::Submitted->value)
            ->whereNull('acknowledged_at')
            ->whereHas('assignment', fn (Builder $assignment): Builder => $assignment->whereColumn('project_mentors.mentor_id', 'mentoring_reports.created_by'))
            ->orderBy('session_at')->get()
            ->each(function (MentoringReport $report) use (&$items, $task): void {
                $items[] = $task('mentoring-'.$report->id, 'Tanggapi catatan mentoring', $report->project->title, $report->topic, route('projects.mentoring.index', $report->project_id), Carbon::parse($report->session_at));
            });

        // Undated tasks first (revisions), then the earliest deadline.
        usort($items, fn (array $left, array $right): int => [(int) ($left['date'] !== null), $left['date']] <=> [(int) ($right['date'] !== null), $right['date']]);

        return $items;
    }
}
