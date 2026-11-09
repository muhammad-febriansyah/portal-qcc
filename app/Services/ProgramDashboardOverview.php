<?php

namespace App\Services;

use App\Enums\BatchStatus;
use App\Enums\ProposalStatus;
use App\Enums\QccStep;
use App\Enums\ReportStatus;
use App\Enums\StepStatus;
use App\Enums\TeamMatchingStatus;
use App\Enums\ThemeCategory;
use App\Models\BiweeklyReport;
use App\Models\MentoringReport;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectStep;
use App\Models\QccBatch;
use App\Models\QccBatchSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Progress of the running QCC batch: its three program phases and the numbers behind each
 * phase. Which phases a person sees depends on their role (see User::visibleProgramPhases()).
 *
 * Phase 1 is "registration": members complete their profile and competencies and submit
 * proposals. It can always be read; phases 2 (selection) and 3 (execution) stay locked
 * until their start date.
 */
class ProgramDashboardOverview
{
    public const PHASES = ['registration', 'selection', 'execution'];

    /** Accounts that take part in QCC as participants. */
    public const PARTICIPANT_ROLES = ['SIMer / Member', 'Team Leader'];

    /** A team needs a leader and at least one member from another LOB/Div. */
    private const MIN_TEAM_MEMBERS = 2;

    /** Longest trend drawn, so a mistyped phase end cannot build a huge series. */
    private const MAX_TREND_DAYS = 180;

    /** How many of the most-held skills the registration phase lists. */
    private const TOP_COMPETENCIES = 10;

    /**
     * The program dashboard limited to the given phases. Null when no batch runs or none of
     * those phases can be read yet, so the page shows nothing rather than only locked phases.
     *
     * @param  list<string>  $phaseKeys
     * @param  int|null  $ownProjectsOf  limit the execution project list to this member's projects
     * @return array{
     *     batch: array{id: int, name: string, starts_on: string, ends_on: string},
     *     phases: list<array{key: string, number: int, label: string, starts_on: string|null, ends_on: string|null, state: string, viewable: bool}>,
     *     active_phase: string|null,
     *     registration: array<string, mixed>|null,
     *     selection: array<string, mixed>|null,
     *     execution: array<string, mixed>|null
     * }|null
     */
    public function get(array $phaseKeys = self::PHASES, ?QccBatch $batch = null, ?int $ownProjectsOf = null): ?array
    {
        $batch ??= $this->currentBatch();

        if ($batch === null || $phaseKeys === []) {
            return null;
        }

        $today = today(config('app.timezone'));
        $phases = array_values(array_filter(
            $this->phases($batch, $today),
            fn (array $phase): bool => in_array($phase['key'], $phaseKeys, true),
        ));

        if (! collect($phases)->contains('viewable', true)) {
            return null;
        }

        $phaseByKey = collect($phases)->keyBy('key');
        $registration = $phaseByKey->get('registration');
        $readable = fn (string $key): bool => (bool) ($phaseByKey->get($key)['viewable'] ?? false);

        return [
            'batch' => [
                'id' => $batch->id,
                'name' => $batch->name,
                'starts_on' => $batch->starts_on->toDateString(),
                'ends_on' => $batch->ends_on->toDateString(),
            ],
            'phases' => $phases,
            'active_phase' => collect($phases)->firstWhere('state', 'active')['key'] ?? null,
            'registration' => $registration === null ? null : $this->registration($batch, $registration, $today),
            'selection' => $readable('selection') ? $this->selection($batch) : null,
            'execution' => $readable('execution') ? $this->execution($batch, $today, $ownProjectsOf) : null,
        ];
    }

    /**
     * The active batch that started most recently.
     */
    public function currentBatch(): ?QccBatch
    {
        return QccBatch::query()
            ->where('status', BatchStatus::Active->value)
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * A phase is done after its end date, active within its dates and upcoming before its start.
     * A batch without phase dates runs registration over the whole batch and keeps the rest locked.
     *
     * @return list<array{key: string, number: int, label: string, starts_on: string|null, ends_on: string|null, state: string, viewable: bool}>
     */
    private function phases(QccBatch $batch, CarbonInterface $today): array
    {
        $definitions = [
            ['key' => 'registration', 'label' => 'Registrasi & Pengajuan Proposal', 'starts_on' => $batch->registration_starts_on ?? $batch->starts_on, 'ends_on' => $batch->registration_ends_on],
            ['key' => 'selection', 'label' => 'Seleksi Proposal & Pembentukan Tim', 'starts_on' => $batch->selection_starts_on, 'ends_on' => $batch->selection_ends_on],
            ['key' => 'execution', 'label' => 'Eksekusi & Monitoring Proyek', 'starts_on' => $batch->execution_starts_on, 'ends_on' => $batch->execution_ends_on],
        ];

        return array_map(function (array $phase, int $index) use ($today): array {
            /** @var Carbon|null $startsOn */
            $startsOn = $phase['starts_on'];
            /** @var Carbon|null $endsOn */
            $endsOn = $phase['ends_on'];

            $state = match (true) {
                $startsOn === null || $today->lt($startsOn) => 'upcoming',
                $endsOn !== null && $today->gt($endsOn) => 'done',
                default => 'active',
            };

            return [
                'key' => $phase['key'],
                'number' => $index + 1,
                'label' => $phase['label'],
                'starts_on' => $startsOn?->toDateString(),
                'ends_on' => $endsOn?->toDateString(),
                'state' => $state,
                // Registration figures build up before the phase officially opens, so it is always readable.
                'viewable' => $phase['key'] === 'registration' || $state !== 'upcoming',
            ];
        }, $definitions, array_keys($definitions));
    }

    /**
     * @param  array{starts_on: string|null, ends_on: string|null}  $phase
     * @return array<string, mixed>
     */
    private function registration(QccBatch $batch, array $phase, CarbonInterface $today): array
    {
        $registeredIds = $this->participants()->whereNotNull('profile_completed_at')->pluck('users.id');
        $registered = $registeredIds->count();
        $competencyCompleted = DB::table('member_competencies')
            ->whereIn('user_id', $registeredIds)
            ->distinct()
            ->count('user_id');

        $submitted = $this->submittedProposals($batch);
        $proposalsSubmitted = (clone $submitted)->count();
        $submitters = (clone $submitted)->distinct()->count('proposer_id');
        $target = $batch->participant_target;

        return [
            'participant_target' => $target,
            'participants' => $this->participants()->count(),
            'registered' => $registered,
            'competency_completed' => $competencyCompleted,
            'competency_completed_percent' => $this->percent($competencyCompleted, $registered),
            'proposals_submitted' => $proposalsSubmitted,
            'submitters' => $submitters,
            'submitters_percent' => $target ? $this->percent($submitters, $target) : null,
            'organizations_count' => Organization::query()->where('is_active', true)->count(),
            'submissions_by_organization' => $this->submissionsByOrganization($batch),
            'top_competencies' => $this->topCompetencies($registeredIds->all(), $registered),
            'themes' => $this->themes($batch, $proposalsSubmitted),
            'trend' => $this->trend($batch, $phase, $today),
        ];
    }

    /**
     * @return Builder<User>
     */
    private function participants(): Builder
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn (Builder $roles) => $roles->whereIn('name', self::PARTICIPANT_ROLES));
    }

    /**
     * Proposals of the batch that left draft, whatever their selection result.
     *
     * @return Builder<Project>
     */
    private function submittedProposals(QccBatch $batch): Builder
    {
        return Project::query()
            ->where('batch_id', $batch->id)
            ->whereNotNull('submitted_at')
            ->where('proposal_status', '!=', ProposalStatus::Draft->value);
    }

    /**
     * Submitted proposals counted by the proposer's LOB/Div, busiest first.
     *
     * @return list<array{name: string, count: int}>
     */
    private function submissionsByOrganization(QccBatch $batch): array
    {
        $counts = $this->submittedProposals($batch)
            ->join('users', 'users.id', '=', 'projects.proposer_id')
            ->whereNotNull('users.organization_id')
            ->groupBy('users.organization_id')
            ->selectRaw('users.organization_id as organization_id, count(*) as total')
            ->toBase()
            ->pluck('total', 'organization_id');

        return $this->organizationCounts($counts->all());
    }

    /**
     * Every active LOB/Div with its count, busiest first. A LOB/Div switched off later still
     * shows when it has proposals, so no proposal drops out of the chart.
     *
     * @param  array<int|string, int|string>  $counts
     * @return list<array{name: string, count: int}>
     */
    private function organizationCounts(array $counts): array
    {
        $withProposals = array_keys(array_filter($counts, fn ($count): bool => (int) $count > 0));

        return Organization::query()
            ->where(fn (Builder $query) => $query->where('is_active', true)->orWhereIn('id', $withProposals))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Organization $organization): array => ['name' => $organization->name, 'count' => (int) ($counts[$organization->id] ?? 0)])
            ->sortBy([['count', 'desc'], ['name', 'asc']])
            ->values()
            ->all();
    }

    /**
     * The skills most registered SIMers hold, with how many of them hold each one.
     *
     * @param  list<int>  $registeredIds
     * @return list<array{name: string, count: int, percent: int}>
     */
    private function topCompetencies(array $registeredIds, int $registered): array
    {
        return DB::table('member_competencies')
            ->join('competency_master', 'competency_master.id', '=', 'member_competencies.competency_id')
            ->whereIn('member_competencies.user_id', $registeredIds)
            ->where('competency_master.is_active', true)
            ->groupBy('competency_master.id', 'competency_master.code', 'competency_master.name')
            ->selectRaw('competency_master.code as code, competency_master.name as name, count(distinct member_competencies.user_id) as total')
            ->orderByDesc('total')
            ->orderBy('competency_master.code')
            ->limit(self::TOP_COMPETENCIES)
            ->get()
            ->map(fn (object $row): array => [
                'name' => (string) $row->name,
                'count' => (int) $row->total,
                'percent' => $this->percent((int) $row->total, $registered),
            ])
            ->all();
    }

    /**
     * Submitted proposals by theme category. Proposals sent before categories existed are listed apart.
     *
     * @return list<array{key: string, label: string, count: int, percent: int}>
     */
    private function themes(QccBatch $batch, int $total): array
    {
        $counts = $this->submittedProposals($batch)
            ->groupBy('theme_category')
            ->selectRaw('theme_category, count(*) as total')
            ->toBase()
            ->pluck('total', 'theme_category');

        $themes = array_map(fn (ThemeCategory $category): array => [
            'key' => $category->value,
            'label' => $category->label(),
            'count' => (int) ($counts[$category->value] ?? 0),
            'percent' => $this->percent((int) ($counts[$category->value] ?? 0), $total),
        ], ThemeCategory::cases());

        $uncategorized = (int) ($counts[''] ?? 0);

        if ($uncategorized > 0) {
            $themes[] = ['key' => 'UNCATEGORIZED', 'label' => 'Belum dikategorikan', 'count' => $uncategorized, 'percent' => $this->percent($uncategorized, $total)];
        }

        return $themes;
    }

    /**
     * Running totals per day, from the phase start to today (or the phase end once it is over).
     *
     * @param  array{starts_on: string|null, ends_on: string|null}  $phase
     * @return list<array{date: string, registered: int, submitted: int}>
     */
    private function trend(QccBatch $batch, array $phase, CarbonInterface $today): array
    {
        // Immutable dates throughout, so moving one day never changes another.
        $start = CarbonImmutable::parse($phase['starts_on'] ?? $batch->starts_on)->startOfDay();
        $end = $phase['ends_on'] !== null && $today->gt($phase['ends_on'])
            ? CarbonImmutable::parse($phase['ends_on'])->startOfDay()
            : CarbonImmutable::parse($today)->startOfDay();

        if ($end->lt($start)) {
            return [];
        }

        if ($start->diffInDays($end) > self::MAX_TREND_DAYS) {
            $start = $end->subDays(self::MAX_TREND_DAYS);
        }

        $registeredBefore = $this->participants()->where('profile_completed_at', '<', $start)->count();
        $registeredPerDay = $this->participants()
            ->whereBetween('profile_completed_at', [$start, $end->endOfDay()])
            ->groupByRaw('date(profile_completed_at)')
            ->selectRaw('date(profile_completed_at) as day, count(*) as total')
            ->toBase()
            ->pluck('total', 'day');

        $submittedBefore = $this->submittedProposals($batch)->where('submitted_at', '<', $start)->count();
        $submittedPerDay = $this->submittedProposals($batch)
            ->whereBetween('submitted_at', [$start, $end->endOfDay()])
            ->groupByRaw('date(submitted_at)')
            ->selectRaw('date(submitted_at) as day, count(*) as total')
            ->toBase()
            ->pluck('total', 'day');

        $series = [];
        $registered = $registeredBefore;
        $submitted = $submittedBefore;

        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $key = $day->toDateString();
            $registered += (int) ($registeredPerDay[$key] ?? 0);
            $submitted += (int) ($submittedPerDay[$key] ?? 0);
            $series[] = ['date' => $key, 'registered' => $registered, 'submitted' => $submitted];
        }

        return $series;
    }

    /**
     * Phase 2: selection results of the submitted proposals and how far their teams are formed.
     *
     * @return array<string, mixed>
     */
    private function selection(QccBatch $batch): array
    {
        $statusCounts = $this->submittedProposals($batch)
            ->groupBy('proposal_status')
            ->selectRaw('proposal_status, count(*) as total')
            ->toBase()
            ->pluck('total', 'proposal_status');
        $total = (int) $statusCounts->sum();
        $passed = (int) ($statusCounts[ProposalStatus::Approved->value] ?? 0);
        $failed = (int) ($statusCounts[ProposalStatus::Rejected->value] ?? 0);

        $approved = $this->submittedProposals($batch)
            ->where('proposal_status', ProposalStatus::Approved->value)
            ->with([
                'requirements:id,project_id,competency_id,minimum_level',
                'proposer:id,organization_id',
                'members' => fn ($members) => $members
                    ->wherePivot('status', 'ACTIVE')
                    ->wherePivotNull('left_at')
                    ->select('users.id'),
            ])
            ->get(['id', 'proposer_id']);

        $formed = $approved->filter(fn (Project $project): bool => $project->members->count() >= self::MIN_TEAM_MEMBERS);
        $matched = DB::table('team_matching_runs')
            ->whereIn('project_id', $approved->pluck('id'))
            ->where('status', TeamMatchingStatus::Ready->value)
            ->distinct()
            ->count('project_id');

        return [
            'total' => $total,
            'passed' => $passed,
            'passed_percent' => $this->percent($passed, $total),
            'failed' => $failed,
            'failed_percent' => $this->percent($failed, $total),
            'in_selection' => max($total - $passed - $failed, 0),
            'teams_formed' => $formed->count(),
            'teams_formed_percent' => $this->percent($formed->count(), $passed),
            'teams_pending' => max($passed - $formed->count(), 0),
            'teams_pending_percent' => $this->percent(max($passed - $formed->count(), 0), $passed),
            'funnel' => [
                ['key' => 'passed', 'label' => 'Proposal Lolos Seleksi', 'count' => $passed],
                ['key' => 'matched', 'label' => 'Matching Kandidat (AI)', 'count' => $matched],
                ['key' => 'formed', 'label' => 'Tim Terbentuk', 'count' => $formed->count()],
            ],
            'passed_by_organization' => $this->countByOrganization($approved),
            'matching' => $this->matchingBands($formed),
            'team_sizes' => $this->teamSizes($formed),
        ];
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @return list<array{name: string, count: int}>
     */
    private function countByOrganization(Collection $projects): array
    {
        $counts = $projects->countBy(fn (Project $project): int => (int) $project->proposer?->organization_id);

        return $this->organizationCounts($counts->all());
    }

    /**
     * How well each formed team covers the competencies its project asked for. A need counts as
     * covered when one active member holds that competency at the minimum level. A project
     * without needs counts as fully covered.
     *
     * @param  Collection<int, Project>  $projects
     * @return list<array{key: string, label: string, count: int, percent: int}>
     */
    private function matchingBands(Collection $projects): array
    {
        $levels = DB::table('member_competencies')
            ->whereIn('user_id', $projects->flatMap(fn (Project $project) => $project->members->pluck('id'))->unique())
            ->get(['user_id', 'competency_id', 'self_level'])
            ->groupBy('user_id');

        $bands = ['fit' => 0, 'partial' => 0, 'gap' => 0];

        foreach ($projects as $project) {
            $requirements = $project->requirements;
            $memberLevels = $project->members->flatMap(fn (User $member) => $levels->get($member->id, collect()));
            $covered = $requirements->filter(fn ($requirement): bool => $memberLevels->contains(
                fn ($held): bool => (int) $held->competency_id === (int) $requirement->competency_id
                    && (int) $held->self_level >= (int) ($requirement->minimum_level ?? 0),
            ))->count();
            $coverage = $requirements->isEmpty() ? 100 : $this->percent($covered, $requirements->count());

            $bands[match (true) {
                $coverage >= 80 => 'fit',
                $coverage >= 60 => 'partial',
                default => 'gap',
            }]++;
        }

        $total = $projects->count();

        return [
            ['key' => 'fit', 'label' => 'Sesuai (≥80%)', 'count' => $bands['fit'], 'percent' => $this->percent($bands['fit'], $total)],
            ['key' => 'partial', 'label' => 'Cukup Sesuai (60–79%)', 'count' => $bands['partial'], 'percent' => $this->percent($bands['partial'], $total)],
            ['key' => 'gap', 'label' => 'Perlu Penyesuaian (<60%)', 'count' => $bands['gap'], 'percent' => $this->percent($bands['gap'], $total)],
        ];
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @return list<array{size: int, label: string, count: int, percent: int}>
     */
    private function teamSizes(Collection $projects): array
    {
        $counts = $projects->countBy(fn (Project $project): int => $project->members->count());
        $sizes = range(self::MIN_TEAM_MEMBERS, max(self::MIN_TEAM_MEMBERS, (int) $counts->keys()->max(), 5));

        return array_map(fn (int $size): array => [
            'size' => $size,
            'label' => "{$size} Orang",
            'count' => (int) ($counts[$size] ?? 0),
            'percent' => $this->percent((int) ($counts[$size] ?? 0), $projects->count()),
        ], $sizes);
    }

    /**
     * Phase 3: how the approved projects of the batch progress through the eight QCC steps,
     * and whether their bi-weekly reports and mentoring sessions keep up.
     *
     * @return array<string, mixed>
     */
    private function execution(QccBatch $batch, CarbonInterface $today, ?int $ownProjectsOf): array
    {
        $projects = Project::query()
            ->where('batch_id', $batch->id)
            ->where('proposal_status', ProposalStatus::Approved->value)
            ->with([
                'steps' => fn ($steps) => $steps->orderBy('step_number'),
                'proposer:id,organization_id',
                'proposer.organization:id,name',
                'members' => fn ($members) => $members
                    ->wherePivot('status', 'ACTIVE')
                    ->wherePivotNull('left_at')
                    ->select('users.id', 'users.name'),
            ])
            ->orderBy('id')
            ->get(['id', 'title', 'proposer_id']);

        $projectIds = $projects->pluck('id');
        $dueReports = BiweeklyReport::query()
            ->whereIn('project_id', $projectIds)
            ->whereDate('ends_on', '<', $today->toDateString())
            ->get(['project_id', 'ends_on', 'submitted_at'])
            ->groupBy('project_id');
        $mentoringDue = QccBatchSchedule::query()
            ->where('batch_id', $batch->id)
            ->where('schedule_type', 'MENTORING')
            ->whereDate('scheduled_on', '<=', $today->toDateString())
            ->count();
        $mentoringHeld = MentoringReport::query()
            ->whereIn('project_id', $projectIds)
            ->where('status', '!=', ReportStatus::Draft->value)
            ->groupBy('project_id')
            ->selectRaw('project_id, count(*) as total')
            ->toBase()
            ->pluck('total', 'project_id');

        $rows = $projects->map(function (Project $project) use ($today, $dueReports, $mentoringDue, $mentoringHeld): array {
            $steps = collect(QccStep::cases())->map(fn (QccStep $step) => $project->steps->firstWhere('step_number', $step->value));
            $progress = $steps->map(fn (?ProjectStep $step): int => match (true) {
                $step === null => 0,
                $step->status === StepStatus::Approved => 100,
                default => (int) $step->progress_pct,
            });
            $current = $steps->first(fn (?ProjectStep $step): bool => $step === null || $step->status !== StepStatus::Approved);
            $overdue = $steps->contains(fn (?ProjectStep $step): bool => $step !== null
                && $step->status !== StepStatus::Approved
                && $step->target_date !== null
                && $step->target_date->lt($today));
            $reports = $dueReports->get($project->id, collect());
            $biweeklyOnTime = $reports->every(fn (BiweeklyReport $report): bool => $report->submitted_at !== null
                && $report->submitted_at->toDateString() <= $report->ends_on->toDateString());
            $leader = $project->members->first(fn (User $member): bool => $member->pivot->role_in_project === 'LEADER');

            return [
                'id' => $project->id,
                'progress' => (int) round($progress->avg()),
                'step_progress' => $progress->all(),
                'step_started' => $steps->map(fn (?ProjectStep $step): bool => $step !== null && $step->status !== StepStatus::NotStarted)->all(),
                'overdue' => $overdue,
                'biweekly_on_time' => $biweeklyOnTime,
                'mentoring_on_schedule' => (int) ($mentoringHeld[$project->id] ?? 0) >= $mentoringDue,
                'row' => [
                    'id' => $project->id,
                    'code' => sprintf('PRP-%04d', $project->id),
                    'title' => $project->title,
                    'organization' => $project->proposer?->organization?->name,
                    'leader' => $leader?->name,
                    'member_ids' => $project->members->pluck('id')->all(),
                    'current_step' => $current === null ? null : [
                        'number' => $current->step_number,
                        'label' => QccStep::from($current->step_number)->label(),
                    ],
                    'steps' => $steps->map(fn (?ProjectStep $step): string => match (true) {
                        $step?->status === StepStatus::Approved => 'done',
                        $step !== null && $step->status !== StepStatus::NotStarted => 'current',
                        default => 'todo',
                    })->all(),
                    'status' => $overdue ? 'overdue' : 'on_time',
                ],
            ];
        });

        $total = $rows->count();
        $count = fn (string $flag): int => $rows->where($flag, true)->count();
        $onTime = $total - $count('overdue');

        return [
            'total' => $total,
            'average_progress' => $total > 0 ? (int) round($rows->avg('progress')) : 0,
            'biweekly' => ['compliant' => $count('biweekly_on_time'), 'late' => $total - $count('biweekly_on_time'), 'percent' => $this->percent($count('biweekly_on_time'), $total)],
            'mentoring' => ['compliant' => $count('mentoring_on_schedule'), 'late' => $total - $count('mentoring_on_schedule'), 'percent' => $this->percent($count('mentoring_on_schedule'), $total)],
            'on_time' => ['count' => $onTime, 'overdue' => $total - $onTime, 'percent' => $this->percent($onTime, $total)],
            'steps' => array_map(fn (QccStep $step): array => [
                'number' => $step->value,
                'label' => $step->label(),
                'average_progress' => $total > 0 ? (int) round($rows->avg(fn (array $row): int => $row['step_progress'][$step->value - 1])) : 0,
                'projects_started' => $rows->filter(fn (array $row): bool => $row['step_started'][$step->value - 1])->count(),
            ], QccStep::cases()),
            'projects_scope' => $ownProjectsOf === null ? 'all' : 'own',
            'projects' => $rows
                ->pluck('row')
                ->filter(fn (array $row): bool => $ownProjectsOf === null || in_array($ownProjectsOf, $row['member_ids'], true))
                ->map(function (array $row): array {
                    unset($row['member_ids']);

                    return $row;
                })
                ->values()
                ->all(),
        ];
    }

    private function percent(int $part, int $whole): int
    {
        return $whole > 0 ? (int) round($part / $whole * 100) : 0;
    }
}
