<?php

namespace App\Http\Controllers;

use App\Actions\Proposals\InitializeProjectSteps;
use App\Enums\ProposalStatus;
use App\Enums\QccStep;
use App\Enums\StepStatus;
use App\Models\Project;
use App\Models\ProjectStep;
use App\Models\ProjectStepHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProjectStepController extends Controller
{
    public function show(Request $request, Project $project, ?int $step = null): Response
    {
        Gate::authorize('view', $project);

        abort_if($step !== null && ($step < 1 || $step > count(QccStep::cases())), 404);

        $isApproved = $project->proposal_status === ProposalStatus::Approved;

        if ($isApproved) {
            app(InitializeProjectSteps::class)->handle($project);
        }

        $steps = $isApproved ? $project->steps()->get() : collect();
        $current = $this->currentStepNumber($steps);
        $selectedNumber = $step ?? $current;
        $selected = $steps->firstWhere('step_number', $selectedNumber);
        $selected?->load(['histories' => fn ($query) => $query->with('actor:id,name')->limit(30)]);
        $user = $request->user();
        $editable = $selected !== null && in_array($selected->status, [StepStatus::NotStarted, StepStatus::InProgress, StepStatus::RevisionRequired], true);

        return Inertia::render('projects/steps', [
            'project' => [
                'id' => $project->id,
                'code' => sprintf('PRP-%04d', $project->id),
                'title' => $project->title,
            ],
            'available' => $isApproved,
            'steps' => $steps->map(fn (ProjectStep $item): array => [
                'number' => $item->step_number,
                'name' => $item->definition()->label(),
                'status' => $item->status->value,
                'status_label' => $item->status->label(),
                'progress_pct' => $item->progress_pct,
                'target_date' => $item->target_date?->toDateString(),
                'is_current' => $item->step_number === $current,
            ])->values()->all(),
            'selected' => $selected === null ? null : [
                'number' => $selected->step_number,
                'name' => $selected->definition()->label(),
                'activity_description' => $selected->activity_description,
                'plan_text' => $selected->plan_text,
                'actual_text' => $selected->actual_text,
                'target_date' => $selected->target_date?->toDateString(),
                'status' => $selected->status->value,
                'status_label' => $selected->status->label(),
                'progress_pct' => $selected->progress_pct,
                'submitted_at' => $selected->submitted_at === null ? null : Carbon::parse($selected->submitted_at)->toIso8601String(),
                'approved_at' => $selected->approved_at === null ? null : Carbon::parse($selected->approved_at)->toIso8601String(),
                'updated_at' => $selected->updated_at === null ? null : Carbon::parse($selected->updated_at)->toIso8601String(),
                'submitter_note' => $selected->submitter_note,
                'lock_version' => $selected->lock_version,
                'can_edit' => $editable && $user->can('updateStep', $project),
                'can_submit' => $editable && $user->can('submitStep', $project),
                'can_start_review' => $selected->status === StepStatus::Submitted && $user->can('reviewStep', $project),
                'can_approve' => $selected->status === StepStatus::UnderReview && $user->can('approveStep', $project),
                'can_request_revision' => $selected->status === StepStatus::UnderReview && $user->can('reviewStep', $project),
                'history' => $selected->histories->map(fn (ProjectStepHistory $entry): array => [
                    'id' => $entry->id,
                    'action' => $entry->action,
                    'actor' => $entry->actor?->name,
                    'previous_status' => $entry->previous_status,
                    'new_status' => $entry->new_status,
                    'version' => $entry->version,
                    'reason' => $entry->reason,
                    'created_at' => Carbon::parse($entry->created_at)->toIso8601String(),
                ])->all(),
            ],
            'summary' => $this->summary($steps),
            'catalog' => QccStep::catalog(),
        ]);
    }

    /**
     * The first step that is not approved yet, or the last one when all are.
     *
     * @param  Collection<int, ProjectStep>  $steps
     */
    private function currentStepNumber(Collection $steps): int
    {
        return $steps->first(fn (ProjectStep $step): bool => $step->status !== StepStatus::Approved)->step_number
            ?? count(QccStep::cases());
    }

    /**
     * @param  Collection<int, ProjectStep>  $steps
     * @return array{approved: int, total: int, overall_progress: int, in_review: int, needs_revision: int}
     */
    private function summary(Collection $steps): array
    {
        $total = count(QccStep::cases());
        $progress = $steps->sum(fn (ProjectStep $step): int => $step->status === StepStatus::Approved ? 100 : $step->progress_pct);

        return [
            'approved' => $steps->where('status', StepStatus::Approved)->count(),
            'total' => $total,
            'overall_progress' => (int) round($progress / $total),
            'in_review' => $steps->whereIn('status', [StepStatus::Submitted, StepStatus::UnderReview])->count(),
            'needs_revision' => $steps->where('status', StepStatus::RevisionRequired)->count(),
        ];
    }
}
