<?php

namespace App\Http\Controllers;

use App\Enums\ReportStatus;
use App\Models\ActionItem;
use App\Models\EvidenceLink;
use App\Models\MentoringReport;
use App\Models\Project;
use App\Models\ProjectMentor;
use App\Models\User;
use App\Services\AuditService;
use App\Services\ProjectNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Owner QCC assigns mentors; active mentors can review and approve 8 Step work
 * through the dedicated step workflow.
 */
class MentoringController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly ProjectNotifier $notifier,
    ) {}

    public function index(Request $request, Project $project): Response
    {
        Gate::authorize('view', $project);

        $user = $request->user();
        $canWrite = $user->can('writeMentoring', $project);
        $canViewEvidence = $user->can('viewEvidence', $project);
        $assignments = $project->mentorAssignments()->with('mentor:id,name')->orderByDesc('id')->get();
        $reports = MentoringReport::query()->where('project_id', $project->id)
            ->with(['assignment.mentor:id,name', 'actions.pic:id,name'])
            ->when($canViewEvidence, fn ($query) => $query->with('evidenceLinks.evidence'))
            ->orderByDesc('session_at')->limit(100)->get();

        return Inertia::render('projects/mentoring', [
            'project' => ['id' => $project->id, 'code' => sprintf('PRP-%04d', $project->id), 'title' => $project->title],
            'assignments' => $assignments->map(fn (ProjectMentor $item): array => [
                'id' => $item->id, 'mentor' => $item->mentor?->name, 'status' => $item->status,
                'assigned_at' => Carbon::parse($item->assigned_at)->toIso8601String(), 'ended_at' => $item->ended_at === null ? null : Carbon::parse($item->ended_at)->toIso8601String(),
            ])->all(),
            'reports' => $reports->map(fn (MentoringReport $report): array => [
                'id' => $report->id, 'session_at' => Carbon::parse($report->session_at)->toIso8601String(),
                'mentor' => $report->assignment?->mentor?->name, 'topic' => $report->topic, 'findings' => $report->findings,
                'suggestions' => $report->suggestions, 'next_steps' => $report->next_steps,
                'status' => $report->status->value, 'status_label' => $report->status->label(),
                'team_response' => $report->team_response,
                'actions' => $report->actions->map(fn (ActionItem $action): array => [
                    'id' => $action->id, 'title' => $action->title, 'description' => $action->description,
                    'pic_id' => $action->pic_id, 'pic' => $action->pic?->name,
                    'due_date' => $action->due_date === null ? null : Carbon::parse($action->due_date)->toDateString(), 'status' => $action->status->value,
                ])->all(),
                'evidence' => $canViewEvidence ? $report->evidenceLinks->map(fn (EvidenceLink $link): array => [
                    'id' => $link->evidence->id, 'title' => $link->evidence->title,
                    'original_name' => $link->evidence->original_name, 'scan_status' => $link->evidence->scan_status,
                    'download_url' => $link->evidence->scan_status === 'PASSED'
                        ? route('projects.evidence.download', [$project, $link->evidence]) : null,
                ])->all() : [],
                'can_edit' => $canWrite && $report->status === ReportStatus::Draft && $report->created_by === $user->id,
                'can_acknowledge' => $report->status === ReportStatus::Submitted && $user->can('manageReports', $project),
            ])->all(),
            'members' => $project->members()->wherePivot('status', 'ACTIVE')->wherePivotNull('left_at')->orderBy('users.name')->get(['users.id', 'users.name'])
                ->map(fn (User $member): array => ['value' => (string) $member->id, 'label' => $member->name])->all(),
            'mentor_options' => $user->can('assignMentors', $project)
                ? User::role('Mentor')->where('is_active', true)
                    ->whereNotIn('id', $project->mentorAssignments()->where('status', 'ACTIVE')->pluck('mentor_id'))
                    ->orderBy('name')->get(['id', 'name'])->map(fn (User $mentor): array => ['value' => (string) $mentor->id, 'label' => $mentor->name])->all()
                : [],
            'can_assign' => $user->can('assignMentors', $project),
            'can_write' => $canWrite,
        ]);
    }

    public function assign(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('assignMentors', $project);

        $data = $request->validate([
            'mentor_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
        ], ['mentor_id.required' => 'Pilih mentor.']);

        $mentor = User::query()->whereKey((int) $data['mentor_id'])->firstOrFail();

        DB::transaction(function () use ($request, $project, $mentor): void {
            Project::query()->whereKey($project->id)->lockForUpdate()->first();

            if (! $mentor->hasRole('Mentor')) {
                throw ValidationException::withMessages(['mentor_id' => 'Pengguna ini bukan Mentor.']);
            }

            if ($project->mentorAssignments()->where('mentor_id', $mentor->id)->where('status', 'ACTIVE')->exists()) {
                throw ValidationException::withMessages(['mentor_id' => 'Mentor ini sudah ditugaskan di proyek ini.']);
            }

            $assignment = ProjectMentor::query()->create([
                'project_id' => $project->id, 'mentor_id' => $mentor->id, 'assigned_by' => $request->user()->id,
                'status' => 'ACTIVE', 'assigned_at' => now(),
            ]);

            $this->audit->record('mentor.assigned', $assignment, after: ['mentor_id' => $mentor->id], subjectLabel: "{$mentor->name} untuk proposal PRP-".sprintf('%04d', $project->id));

            $this->notifier->notify([$mentor], $project, 'Kamu ditugaskan sebagai mentor', "Kamu menjadi mentor proyek {$project->title}.", route('projects.mentoring.index', $project, false), 'MENTOR_ASSIGNED', $request->user());
            $this->notifier->notify($this->notifier->leaders($project), $project, 'Mentor ditugaskan', "{$mentor->name} menjadi mentor proyek {$project->title}.", route('projects.mentoring.index', $project, false), 'MENTOR_ASSIGNED', $request->user());
        });

        return $this->done($project, 'Mentor berhasil ditugaskan.');
    }

    public function endAssignment(Request $request, Project $project, ProjectMentor $assignment): RedirectResponse
    {
        Gate::authorize('assignMentors', $project);
        abort_unless($assignment->project_id === $project->id, 404);

        DB::transaction(function () use ($project, $assignment): void {
            $assignment = ProjectMentor::query()->lockForUpdate()->findOrFail($assignment->id);

            if ($assignment->status !== 'ACTIVE') {
                throw ValidationException::withMessages(['assignment' => 'Penugasan ini sudah berakhir.']);
            }

            $assignment->forceFill(['status' => 'ENDED', 'ended_at' => now()])->save();
            $this->audit->record('mentor.unassigned', $assignment, subjectLabel: 'Mentor proposal PRP-'.sprintf('%04d', $project->id));
        });

        return $this->done($project, 'Penugasan mentor diakhiri.');
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('writeMentoring', $project);

        $data = $this->validated($request, $project);
        $assignment = $project->mentorAssignments()->where('mentor_id', $request->user()->id)->where('status', 'ACTIVE')->firstOrFail();

        $report = DB::transaction(function () use ($request, $project, $assignment, $data): MentoringReport {
            $report = MentoringReport::query()->create([...collect($data)->except('actions')->all(), 'project_id' => $project->id, 'project_mentor_id' => $assignment->id, 'status' => ReportStatus::Draft, 'created_by' => $request->user()->id]);
            $this->syncActions($request, $project, $report, $data['actions'] ?? []);

            return $report;
        });
        $this->audit->record('mentoring.created', $report, subjectLabel: $this->label($project, $report));

        return $this->done($project, 'Sesi mentoring disimpan sebagai draf.');
    }

    public function update(Request $request, Project $project, MentoringReport $report): RedirectResponse
    {
        Gate::authorize('writeMentoring', $project);
        $data = $this->validated($request, $project);

        DB::transaction(function () use ($request, $project, $report, $data): void {
            $report = $this->lock($project, $report);
            $this->guardOwnDraft($report, $request->user());
            $report->fill(collect($data)->except('actions')->all())->save();
            $this->syncActions($request, $project, $report, $data['actions'] ?? []);
            $this->audit->record('mentoring.updated', $report, subjectLabel: $this->label($project, $report));
        });

        return $this->done($project, 'Sesi mentoring diperbarui.');
    }

    public function submit(Request $request, Project $project, MentoringReport $report): RedirectResponse
    {
        Gate::authorize('writeMentoring', $project);
        $data = $this->validated($request, $project);

        DB::transaction(function () use ($request, $project, $report, $data): void {
            $report = $this->lock($project, $report);
            $this->guardOwnDraft($report, $request->user());
            $report->fill(collect($data)->except('actions')->all());
            $this->syncActions($request, $project, $report, $data['actions'] ?? []);

            $missing = [];

            foreach (['findings' => 'Temuan', 'suggestions' => 'Saran'] as $field => $label) {
                if (blank($report->{$field})) {
                    $missing[$field] = "{$label} wajib diisi sebelum sesi dikirim.";
                }
            }

            if ($missing !== []) {
                throw ValidationException::withMessages($missing);
            }

            $report->forceFill(['status' => ReportStatus::Submitted, 'submitted_by' => $request->user()->id, 'submitted_at' => now()])->save();
            $this->audit->record('mentoring.submitted', $report, subjectLabel: $this->label($project, $report));

            $this->notifier->notify($this->notifier->team($project), $project, 'Feedback mentor baru', "Mentor mengirim feedback sesi \"{$report->topic}\".", route('projects.mentoring.index', $project, false), 'MENTORING_SUBMITTED', $request->user());
        });

        return $this->done($project, 'Feedback mentoring dikirim ke tim.');
    }

    public function acknowledge(Request $request, Project $project, MentoringReport $report): RedirectResponse
    {
        Gate::authorize('manageReports', $project);
        $data = $request->validate(['team_response' => ['required', 'string', 'max:10000']], ['team_response.required' => 'Tulis tanggapan tim.']);

        DB::transaction(function () use ($request, $project, $report, $data): void {
            $report = $this->lock($project, $report);

            if ($report->status !== ReportStatus::Submitted) {
                throw ValidationException::withMessages(['report' => 'Hanya feedback yang sudah dikirim yang bisa ditanggapi.']);
            }

            $report->forceFill(['status' => ReportStatus::Acknowledged, 'team_response' => $data['team_response'], 'acknowledged_by' => $request->user()->id, 'acknowledged_at' => now()])->save();
            $this->audit->record('mentoring.acknowledged', $report, subjectLabel: $this->label($project, $report));

            $mentor = $report->assignment?->mentor;
            $this->notifier->notify($mentor ? [$mentor] : [], $project, 'Tim menanggapi feedback', "Tim menanggapi sesi \"{$report->topic}\".", route('projects.mentoring.index', $project, false), 'MENTORING_ACKNOWLEDGED', $request->user());
        });

        return $this->done($project, 'Tanggapan tim tersimpan.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Project $project): array
    {
        return $request->validate([
            'session_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'topic' => ['required', 'string', 'max:255'],
            'findings' => ['nullable', 'string', 'max:20000'],
            'suggestions' => ['nullable', 'string', 'max:20000'],
            'next_steps' => ['nullable', 'string', 'max:20000'],
            'actions' => ['nullable', 'array', 'max:50'],
            'actions.*.id' => ['nullable', 'integer', 'distinct'],
            'actions.*.title' => ['required', 'string', 'max:255'],
            'actions.*.description' => ['nullable', 'string', 'max:10000'],
            'actions.*.pic_id' => ['required', 'integer', Rule::exists('project_members', 'user_id')
                ->where('project_id', $project->id)->where('status', 'ACTIVE')->whereNull('left_at')],
            'actions.*.due_date' => ['required', 'date_format:Y-m-d'],
        ], ['session_at.required' => 'Waktu sesi wajib diisi.', 'topic.required' => 'Topik wajib diisi.']);
    }

    /** @param list<array{id?: int, title: string, description?: string|null, pic_id: int, due_date: string}> $actions */
    private function syncActions(Request $request, Project $project, MentoringReport $report, array $actions): void
    {
        $keptIds = [];

        foreach ($actions as $action) {
            $values = [
                'project_id' => $project->id, 'source_type' => 'MENTORING_REPORT', 'source_id' => $report->id,
                'title' => $action['title'], 'description' => $action['description'] ?? null,
                'pic_id' => $action['pic_id'], 'due_date' => $action['due_date'], 'created_by' => $request->user()->id,
            ];

            if (isset($action['id'])) {
                $item = $report->actions()->whereKey($action['id'])->lockForUpdate()->first();
                if ($item === null) {
                    throw ValidationException::withMessages(['actions' => 'Tindakan tidak valid untuk sesi ini.']);
                }
                $before = $item->only(['title', 'description', 'pic_id', 'due_date']);
                $previousPicId = $item->pic_id;
                $item->fill(collect($values)->except(['project_id', 'source_type', 'source_id', 'created_by'])->all())->save();
                $keptIds[] = $item->id;
                $this->audit->record('action_item.updated', $item, before: $before, after: $item->only(['title', 'description', 'pic_id', 'due_date']));
                if ($item->pic_id !== $previousPicId) {
                    $this->notifier->notify(
                        $item->pic ? [$item->pic] : [], $project, 'Action item mentoring ditugaskan', "{$item->title} ditugaskan kepadamu.",
                        route('projects.mentoring.index', $project, false), 'ACTION_ASSIGNED', $request->user(),
                    );
                }
            } else {
                $item = $report->actions()->create($values);
                $keptIds[] = $item->id;
                $this->audit->record('action_item.created', $item, after: $item->only(['title', 'description', 'pic_id', 'due_date']));
                $this->notifier->notify(
                    $item->pic ? [$item->pic] : [], $project, 'Action item mentoring ditugaskan', "{$item->title} ditugaskan kepadamu.",
                    route('projects.mentoring.index', $project, false), 'ACTION_ASSIGNED', $request->user(),
                );
            }
        }

        $removed = $report->actions()->whereNotIn('id', $keptIds)->get();
        foreach ($removed as $item) {
            $this->audit->record('action_item.deleted', $item, before: $item->only(['title', 'description', 'pic_id', 'due_date']));
            $item->delete();
        }
    }

    private function lock(Project $project, MentoringReport $report): MentoringReport
    {
        abort_unless($report->project_id === $project->id, 404);

        return MentoringReport::query()->lockForUpdate()->findOrFail($report->id);
    }

    private function guardOwnDraft(MentoringReport $report, User $user): void
    {
        if ($report->status !== ReportStatus::Draft || $report->created_by !== $user->id) {
            throw ValidationException::withMessages(['report' => 'Hanya draf milikmu yang bisa diubah.']);
        }
    }

    private function label(Project $project, MentoringReport $report): string
    {
        return 'Mentoring "'.$report->topic.'" proposal PRP-'.sprintf('%04d', $project->id);
    }

    private function done(Project $project, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('projects.mentoring.index', $project);
    }
}
