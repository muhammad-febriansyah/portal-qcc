<?php

namespace App\Http\Controllers;

use App\Actions\Proposals\ProjectStepWorkflow;
use App\Http\Requests\ReviewProjectStepRequest;
use App\Http\Requests\SaveProjectStepRequest;
use App\Models\Project;
use App\Models\ProjectStep;
use App\Services\ProjectNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ProjectStepWorkflowController extends Controller
{
    public function __construct(private readonly ProjectNotifier $notifier) {}

    public function update(SaveProjectStepRequest $request, Project $project, int $step, ProjectStepWorkflow $workflow): RedirectResponse
    {
        $workflow->save($this->step($project, $step), $request->user(), $request->safe()->except('lock_version'), $request->integer('lock_version'));

        return $this->done($project, $step, 'Draft step berhasil disimpan.');
    }

    public function submit(SaveProjectStepRequest $request, Project $project, int $step, ProjectStepWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('submitStep', $project);

        $workflow->submit($this->step($project, $step), $request->user(), $request->safe()->except('lock_version'), $request->integer('lock_version'));
        $this->notifier->notify(
            $this->notifier->reviewers($project, 'steps'),
            $project,
            'Step QCC menunggu review',
            "Step {$step} proyek {$project->title} diajukan untuk review.",
            route('projects.steps.show', ['project' => $project, 'step' => $step], false),
            'STEP_SUBMITTED',
            $request->user(),
        );

        return $this->done($project, $step, 'Step berhasil diajukan untuk review.');
    }

    public function startReview(ReviewProjectStepRequest $request, Project $project, int $step, ProjectStepWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('reviewStep', $project);

        $workflow->startReview($this->step($project, $step), $request->user());

        return $this->done($project, $step, 'Review step dimulai.');
    }

    public function approve(ReviewProjectStepRequest $request, Project $project, int $step, ProjectStepWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('approveStep', $project);

        $workflow->approve($this->step($project, $step), $request->user());
        $this->notifier->notify(
            $this->notifier->team($project),
            $project,
            'Step QCC disetujui',
            "Step {$step} proyek {$project->title} disetujui.",
            route('projects.steps.show', ['project' => $project, 'step' => $step], false),
            'STEP_APPROVED',
            $request->user(),
        );

        return $this->done($project, $step, 'Step disetujui.');
    }

    public function requestRevision(ReviewProjectStepRequest $request, Project $project, int $step, ProjectStepWorkflow $workflow): RedirectResponse
    {
        Gate::authorize('reviewStep', $project);

        $workflow->requestRevision($this->step($project, $step), $request->user(), $request->validated('reason'));
        $this->notifier->notify(
            $this->notifier->team($project),
            $project,
            'Step QCC perlu revisi',
            "Step {$step} proyek {$project->title} dikembalikan untuk revisi.",
            route('projects.steps.show', ['project' => $project, 'step' => $step], false),
            'STEP_REVISION',
            $request->user(),
        );

        return $this->done($project, $step, 'Permintaan revisi dikirim ke tim.');
    }

    private function step(Project $project, int $number): ProjectStep
    {
        return $project->steps()->where('step_number', $number)->firstOrFail();
    }

    private function done(Project $project, int $step, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('projects.steps.show', ['project' => $project, 'step' => $step]);
    }
}
