<?php

namespace App\Http\Controllers;

use App\Enums\ActionStatus;
use App\Enums\RiskLevel;
use App\Enums\RiskStatus;
use App\Enums\RiskType;
use App\Http\Requests\SaveActionItemRequest;
use App\Http\Requests\SaveProjectRiskRequest;
use App\Models\ActionItem;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ProjectRisk;
use App\Models\User;
use App\Services\AuditService;
use App\Services\ProjectNotifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectRiskBoardController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly ProjectNotifier $notifier,
    ) {}

    public function index(Request $request, Project $project): Response
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
            'tab' => $filters['tab'] ?? 'risks',
            'type' => $filters['type'] ?? 'all',
            'status' => $filters['status'] ?? 'all',
            'priority' => $filters['priority'] ?? 'all',
            'pic' => $filters['pic'] ?? 'all',
            'overdue' => (bool) ($filters['overdue'] ?? false),
        ];
        $today = now()->toDateString();

        $risks = $project->risks()->with('pic:id,name')
            ->when($state['type'] !== 'all', fn (Builder $q): Builder => $q->where('type', $state['type']))
            ->when($state['tab'] === 'risks' && $state['status'] !== 'all', fn (Builder $q): Builder => $q->where('status', $state['status']))
            ->when($state['priority'] !== 'all', fn (Builder $q): Builder => $q->where('priority', $state['priority']))
            ->when($state['tab'] === 'risks' && $state['pic'] !== 'all', fn (Builder $q): Builder => $q->where('pic_id', (int) $state['pic']))
            ->when($state['tab'] === 'risks' && $state['overdue'], fn (Builder $q): Builder => $q->where('due_date', '<', $today)->whereNotIn('status', [RiskStatus::Resolved->value, RiskStatus::Closed->value]))
            ->orderByRaw("CASE priority WHEN 'CRITICAL' THEN 0 WHEN 'HIGH' THEN 1 WHEN 'MEDIUM' THEN 2 ELSE 3 END")
            ->orderBy('due_date')->orderBy('id')->limit(200)->get();

        $actions = $project->actionItems()->with('pic:id,name')
            ->when($state['tab'] === 'actions' && $state['status'] !== 'all', fn (Builder $q): Builder => $q->where('status', $state['status']))
            ->when($state['tab'] === 'actions' && $state['pic'] !== 'all', fn (Builder $q): Builder => $q->where('pic_id', (int) $state['pic']))
            ->when($state['tab'] === 'actions' && $state['overdue'], fn (Builder $q): Builder => $q->where('due_date', '<', $today)->whereNotIn('status', [ActionStatus::Completed->value, ActionStatus::Cancelled->value]))
            ->orderBy('due_date')->orderBy('id')->limit(200)->get();

        $riskHistory = AuditLog::query()
            ->with('actor:id,name')
            ->where('subject_type', (new ProjectRisk)->getMorphClass())
            ->whereIn('subject_id', $risks->modelKeys())
            ->whereIn('event', ['risk.created', 'risk.updated', 'risk.deleted'])
            ->orderByDesc('occurred_at')
            ->limit(2000)
            ->get()
            ->groupBy('subject_id')
            ->map(fn ($entries) => $entries->take(10)->values());

        return Inertia::render('projects/risks', [
            'project' => ['id' => $project->id, 'code' => sprintf('PRP-%04d', $project->id), 'title' => $project->title],
            'filters' => $state,
            'risks' => $risks->map(fn (ProjectRisk $risk): array => [
                'id' => $risk->id, 'type' => $risk->type->value, 'title' => $risk->title, 'description' => $risk->description,
                'impact' => $risk->impact, 'likelihood' => $risk->likelihood->value, 'priority' => $risk->priority->value,
                'mitigation' => $risk->mitigation, 'pic_id' => $risk->pic_id, 'pic' => $risk->pic?->name,
                'due_date' => $risk->due_date === null ? null : Carbon::parse($risk->due_date)->toDateString(), 'status' => $risk->status->value,
                'is_overdue' => $risk->due_date !== null && Carbon::parse($risk->due_date)->toDateString() < $today && ! $risk->status->isFinished(),
                'history' => $riskHistory->get($risk->id, collect())->map(fn (AuditLog $entry): array => [
                    'id' => $entry->id,
                    'event' => match ($entry->event) {
                        'risk.created' => 'Risiko / isu dicatat',
                        'risk.deleted' => 'Risiko / isu dihapus',
                        default => 'Risiko / isu diperbarui',
                    },
                    'actor' => $entry->actor->name ?? 'Akun dihapus',
                    'before' => $entry->before_json ?? [],
                    'after' => $entry->after_json ?? [],
                    'reason' => $entry->reason,
                    'occurred_at' => $entry->occurred_at->toIso8601String(),
                ])->all(),
            ])->all(),
            'actions' => $actions->map(fn (ActionItem $item): array => [
                'id' => $item->id, 'title' => $item->title, 'description' => $item->description, 'pic_id' => $item->pic_id,
                'pic' => $item->pic?->name, 'due_date' => $item->due_date === null ? null : Carbon::parse($item->due_date)->toDateString(), 'status' => $item->status->value,
                'source_type' => $item->source_type, 'source_id' => $item->source_id,
                'completed_at' => $item->completed_at === null ? null : Carbon::parse($item->completed_at)->toIso8601String(),
                'is_overdue' => $item->due_date !== null && Carbon::parse($item->due_date)->toDateString() < $today && ! $item->status->isFinished(),
            ])->all(),
            'summary' => [
                'open_risks' => $project->risks()->where('type', RiskType::Risk->value)->whereNotIn('status', [RiskStatus::Resolved->value, RiskStatus::Closed->value])->count(),
                'open_issues' => $project->risks()->where('type', RiskType::Issue->value)->whereNotIn('status', [RiskStatus::Resolved->value, RiskStatus::Closed->value])->count(),
                'overdue_actions' => $project->actionItems()->where('due_date', '<', $today)->whereNotIn('status', [ActionStatus::Completed->value, ActionStatus::Cancelled->value])->count(),
            ],
            'options' => [
                'types' => array_map(fn (RiskType $t): array => ['value' => $t->value, 'label' => $t->label()], RiskType::cases()),
                'likelihoods' => array_map(fn (RiskLevel $l): array => ['value' => $l->value, 'label' => $l->label()], RiskLevel::likelihoods()),
                'priorities' => array_map(fn (RiskLevel $l): array => ['value' => $l->value, 'label' => $l->label()], RiskLevel::cases()),
                'riskStatuses' => array_map(fn (RiskStatus $s): array => ['value' => $s->value, 'label' => $s->label()], RiskStatus::cases()),
                'actionStatuses' => array_map(fn (ActionStatus $s): array => ['value' => $s->value, 'label' => $s->label()], ActionStatus::cases()),
            ],
            'members' => $project->members()->wherePivot('status', 'ACTIVE')->wherePivotNull('left_at')->orderBy('users.name')->get(['users.id', 'users.name'])
                ->map(fn (User $member): array => ['value' => (string) $member->id, 'label' => $member->name])->all(),
            'can_manage' => $request->user()->can('manageRisks', $project),
        ]);
    }

    public function storeRisk(SaveProjectRiskRequest $request, Project $project): RedirectResponse
    {
        DB::transaction(function () use ($request, $project): void {
            $risk = $project->risks()->create([...$request->validated(), 'created_by' => $request->user()->id, 'resolved_at' => $this->resolvedAt(RiskStatus::from($request->validated('status')))]);
            $this->audit->record('risk.created', $risk, after: $this->snapshot($risk), subjectLabel: $this->label($project, $risk->title));
            $this->notifier->notify(
                $risk->pic ? [$risk->pic] : [], $project, 'Risiko atau isu ditugaskan', "{$risk->title} ditugaskan kepadamu.",
                route('projects.risks.index', ['project' => $project, 'tab' => 'risks'], false), 'RISK_ASSIGNED', $request->user(),
            );
        });

        return $this->done($project, 'risks', 'Risiko atau isu berhasil dicatat.');
    }

    public function updateRisk(SaveProjectRiskRequest $request, Project $project, ProjectRisk $risk): RedirectResponse
    {
        abort_unless($risk->project_id === $project->id, 404);

        DB::transaction(function () use ($request, $project, $risk): void {
            $before = $this->snapshot($risk);
            $previousPicId = $risk->pic_id;
            $status = RiskStatus::from($request->validated('status'));
            $risk->fill($request->validated())->forceFill(['resolved_at' => $status->isFinished() ? ($risk->resolved_at ?? now()) : null])->save();
            $this->audit->record('risk.updated', $risk, before: $before, after: $this->snapshot($risk), subjectLabel: $this->label($project, $risk->title));
            if ($risk->pic_id !== $previousPicId) {
                $this->notifier->notify(
                    $risk->pic ? [$risk->pic] : [], $project, 'Risiko atau isu ditugaskan', "{$risk->title} ditugaskan kepadamu.",
                    route('projects.risks.index', ['project' => $project, 'tab' => 'risks'], false), 'RISK_ASSIGNED', $request->user(),
                );
            }
        });

        return $this->done($project, 'risks', 'Risiko atau isu berhasil diperbarui.');
    }

    public function destroyRisk(Request $request, Project $project, ProjectRisk $risk): RedirectResponse
    {
        Gate::authorize('manageRisks', $project);
        abort_unless($risk->project_id === $project->id, 404);

        DB::transaction(function () use ($project, $risk): void {
            $this->audit->record('risk.deleted', $risk, before: $this->snapshot($risk), subjectLabel: $this->label($project, $risk->title));
            ActionItem::query()->where('project_id', $project->id)->where('source_type', 'RISK')->where('source_id', $risk->id)->update(['source_type' => 'PROJECT', 'source_id' => null]);
            $risk->delete();
        });

        return $this->done($project, 'risks', 'Risiko atau isu dihapus.');
    }

    public function storeAction(SaveActionItemRequest $request, Project $project): RedirectResponse
    {
        DB::transaction(function () use ($request, $project): void {
            $data = $this->actionData($request->validated());
            $item = $project->actionItems()->create([...$data, 'created_by' => $request->user()->id, 'completed_at' => $data['status'] === ActionStatus::Completed->value ? now() : null]);
            $this->audit->record('action_item.created', $item, after: $this->snapshot($item), subjectLabel: $this->label($project, $item->title));
            $this->notifier->notify(
                $item->pic ? [$item->pic] : [], $project, 'Tindakan baru ditugaskan', "{$item->title} ditugaskan kepadamu.",
                route('projects.risks.index', ['project' => $project, 'tab' => 'actions'], false), 'ACTION_ASSIGNED', $request->user(),
            );
        });

        return $this->done($project, 'actions', 'Tindakan berhasil dicatat.');
    }

    public function updateAction(SaveActionItemRequest $request, Project $project, ActionItem $action): RedirectResponse
    {
        abort_unless($action->project_id === $project->id, 404);

        DB::transaction(function () use ($request, $project, $action): void {
            $before = $this->snapshot($action);
            $previousPicId = $action->pic_id;
            $data = $this->actionData($request->validated());
            if (in_array($action->source_type, ['BIWEEKLY_REPORT', 'MENTORING_REPORT'], true)) {
                unset($data['source_type'], $data['source_id']);
            }
            $action->fill($data)->forceFill(['completed_at' => $data['status'] === ActionStatus::Completed->value ? ($action->completed_at ?? now()) : null])->save();
            $this->audit->record('action_item.updated', $action, before: $before, after: $this->snapshot($action), subjectLabel: $this->label($project, $action->title));
            if ($action->pic_id !== $previousPicId) {
                $this->notifier->notify(
                    $action->pic ? [$action->pic] : [], $project, 'Tindakan baru ditugaskan', "{$action->title} ditugaskan kepadamu.",
                    route('projects.risks.index', ['project' => $project, 'tab' => 'actions'], false), 'ACTION_ASSIGNED', $request->user(),
                );
            }
        });

        return $this->done($project, 'actions', 'Tindakan berhasil diperbarui.');
    }

    public function destroyAction(Request $request, Project $project, ActionItem $action): RedirectResponse
    {
        Gate::authorize('manageRisks', $project);
        abort_unless($action->project_id === $project->id, 404);

        DB::transaction(function () use ($project, $action): void {
            $this->audit->record('action_item.deleted', $action, before: $this->snapshot($action), subjectLabel: $this->label($project, $action->title));
            $action->delete();
        });

        return $this->done($project, 'actions', 'Tindakan dihapus.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function actionData(array $validated): array
    {
        $validated['source_type'] = $validated['source_type'] ?? 'PROJECT';
        $validated['source_id'] = $validated['source_type'] === 'RISK' ? ($validated['source_id'] ?? null) : null;

        return $validated;
    }

    private function resolvedAt(RiskStatus $status): mixed
    {
        return $status->isFinished() ? now() : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(ProjectRisk|ActionItem $model): array
    {
        $fields = $model instanceof ProjectRisk
            ? ['type', 'title', 'description', 'impact', 'priority', 'likelihood', 'mitigation', 'pic_id', 'due_date', 'status']
            : ['title', 'description', 'pic_id', 'due_date', 'status', 'source_type', 'source_id'];

        return collect($model->only($fields))
            ->map(fn (mixed $value): mixed => $value instanceof \BackedEnum ? $value->value : ($value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value))
            ->all();
    }

    private function label(Project $project, string $title): string
    {
        return $title.' (proposal PRP-'.sprintf('%04d', $project->id).')';
    }

    private function done(Project $project, string $tab, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('projects.risks.index', ['project' => $project, 'tab' => $tab]);
    }
}
