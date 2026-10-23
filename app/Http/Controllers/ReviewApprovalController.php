<?php

namespace App\Http\Controllers;

use App\Enums\ProposalStatus;
use App\Enums\ReportStatus;
use App\Enums\ScoringStatus;
use App\Enums\StepStatus;
use App\Models\BiweeklyReport;
use App\Models\Project;
use App\Models\ProjectStep;
use App\Models\ReviewAssignment;
use App\Models\ScoringRun;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReviewApprovalController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isMentor = $user->hasRole('Mentor');

        $hasAssignment = fn (string $scope): bool => ReviewAssignment::query()->active()
            ->where('reviewer_id', $user->id)->where('scope', $scope)->exists();
        $canReviewProposal = ! $isMentor && ($user->can('projects.review') || $user->can('projects.approve'))
            && ($user->can('projects.viewAny') || $hasAssignment('proposal'));
        $canReviewSteps = ($user->can('projects.review') || $user->can('projects.approve'))
            && ($isMentor
                ? Project::query()->visibleTo($user)->whereHas('mentorAssignments', fn ($assignment) => $assignment->where('mentor_id', $user->id)->where('status', 'ACTIVE'))->exists()
                : ($user->can('projects.viewAny') || $hasAssignment('steps')));
        $canReviewProjects = $canReviewProposal || $canReviewSteps;
        $canReviewScoring = ! $isMentor && $user->can('scoring.view')
            && ($user->can('scoring.review') || $user->can('scoring.approve'))
            && ($user->can('projects.viewAny') || $hasAssignment('scoring'));
        $canReviewBiweekly = ($user->can('projects.review')
                && ($user->can('projects.viewAny') || $hasAssignment('biweekly')))
            || ($user->hasRole('Mentor') && $user->can('projects.view'));

        abort_unless($canReviewProjects || $canReviewScoring || $canReviewBiweekly, 403);

        $filters = $request->validate([
            'tab' => ['nullable', Rule::in(['proposals', 'steps', 'biweekly', 'scoring'])],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', Rule::in([10, 25, 50])],
        ]);

        $availableTabs = [
            ...($canReviewProposal ? ['proposals'] : []),
            ...($canReviewSteps ? ['steps'] : []),
            ...($canReviewBiweekly ? ['biweekly'] : []),
            ...($canReviewScoring ? ['scoring'] : []),
        ];
        $tab = $filters['tab'] ?? ($availableTabs[0] ?? null);

        abort_unless(is_string($tab) && in_array($tab, $availableTabs, true), 403);
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? 10);

        $counts = [
            'proposals' => $canReviewProposal ? Project::query()
                ->when(! $user->can('projects.viewAny'), fn ($query) => $query->whereIn('id', ReviewAssignment::query()->active()->where('reviewer_id', $user->id)->where('scope', 'proposal')->select('project_id')))
                ->whereIn('proposal_status', [ProposalStatus::Submitted->value, ProposalStatus::UnderReview->value])->count() : 0,
            'steps' => $canReviewSteps ? ProjectStep::query()
                ->whereIn('status', [StepStatus::Submitted->value, StepStatus::UnderReview->value])
                ->whereHas('project', fn ($query) => $query->visibleTo($user)->where('proposal_status', ProposalStatus::Approved->value)
                    ->when($isMentor, fn ($project) => $project->whereHas('mentorAssignments', fn ($assignment) => $assignment->where('mentor_id', $user->id)->where('status', 'ACTIVE')))
                    ->when(! $isMentor && ! $user->can('projects.viewAny'), fn ($project) => $project->whereHas('reviewAssignments', fn ($assignment) => $assignment->active()->where('reviewer_id', $user->id)->where('scope', 'steps'))))
                ->count() : 0,
            'biweekly' => $canReviewBiweekly ? BiweeklyReport::query()
                ->where('status', ReportStatus::Submitted->value)
                ->whereHas('project', fn ($query) => $query->visibleTo($user)->where('proposal_status', ProposalStatus::Approved->value)
                    ->when(! ($user->can('projects.viewAny') && $user->can('projects.review')), fn ($project) => $project->where(function ($scope) use ($user): void {
                        if ($user->can('projects.review')) {
                            $scope->whereHas('reviewAssignments', fn ($assignment) => $assignment->active()->where('reviewer_id', $user->id)->where('scope', 'biweekly'));
                        }

                        if ($user->hasRole('Mentor')) {
                            $scope->orWhereHas('mentorAssignments', fn ($assignment) => $assignment->where('mentor_id', $user->id)->where('status', 'ACTIVE'));
                        }
                    })))
                ->count() : 0,
            'scoring' => $canReviewScoring
                ? ScoringRun::query()
                    ->where('status', ScoringStatus::PendingReview->value)
                    ->whereHas('project', fn ($query) => $query->visibleTo($user)->where('proposal_status', ProposalStatus::Approved->value)
                        ->when(! $user->can('projects.viewAny'), fn ($project) => $project->whereHas('reviewAssignments', fn ($assignment) => $assignment->active()->where('reviewer_id', $user->id)->where('scope', 'scoring'))))
                    ->count()
                : 0,
        ];

        $items = match ($tab) {
            'steps' => ProjectStep::query()
                ->with('project:id,title,batch_id,proposal_status')
                ->whereIn('status', [StepStatus::Submitted->value, StepStatus::UnderReview->value])
                ->whereHas('project', fn ($query) => $query->visibleTo($user)->where('proposal_status', ProposalStatus::Approved->value)
                    ->when($isMentor, fn ($project) => $project->whereHas('mentorAssignments', fn ($assignment) => $assignment->where('mentor_id', $user->id)->where('status', 'ACTIVE')))
                    ->when(! $isMentor && ! $user->can('projects.viewAny'), fn ($project) => $project->whereHas('reviewAssignments', fn ($assignment) => $assignment->active()->where('reviewer_id', $user->id)->where('scope', 'steps'))))
                ->when($search !== '', fn ($query) => $query->whereHas('project', fn ($project) => $project->where('title', 'like', "%{$search}%")))
                ->orderByRaw("CASE status WHEN 'UNDER_REVIEW' THEN 0 ELSE 1 END")
                ->orderByDesc('submitted_at')
                ->paginate($perPage)
                ->withQueryString()
                ->through(fn (ProjectStep $step): array => [
                    'id' => $step->id,
                    'title' => $step->definition()->label().' · '.$step->project->title,
                    'description' => sprintf('PRP-%04d · Step %d', $step->project_id, $step->step_number),
                    'status' => $step->status->value,
                    'status_label' => $step->status->label(),
                    'submitted_at' => $step->submitted_at?->toIso8601String(),
                    'url' => route('projects.steps.show', [$step->project_id, $step->step_number]),
                ]),
            'biweekly' => BiweeklyReport::query()
                ->with('project:id,title,batch_id,proposal_status')
                ->where('status', ReportStatus::Submitted->value)
                ->whereHas('project', fn ($query) => $query->visibleTo($user)->where('proposal_status', ProposalStatus::Approved->value)
                    ->when(! ($user->can('projects.viewAny') && $user->can('projects.review')), fn ($project) => $project->where(function ($scope) use ($user): void {
                        if ($user->can('projects.review')) {
                            $scope->whereHas('reviewAssignments', fn ($assignment) => $assignment->active()->where('reviewer_id', $user->id)->where('scope', 'biweekly'));
                        }

                        if ($user->hasRole('Mentor')) {
                            $scope->orWhereHas('mentorAssignments', fn ($assignment) => $assignment->where('mentor_id', $user->id)->where('status', 'ACTIVE'));
                        }
                    })))
                ->when($search !== '', fn ($query) => $query->whereHas('project', fn ($project) => $project->where('title', 'like', "%{$search}%")))
                ->orderBy('submitted_at')
                ->paginate($perPage)
                ->withQueryString()
                ->through(fn (BiweeklyReport $report): array => [
                    'id' => $report->id,
                    'title' => 'Laporan Bi-weekly Periode '.$report->period_no,
                    'description' => sprintf('PRP-%04d · %s', $report->project_id, $report->project->title),
                    'status' => $report->status->value,
                    'status_label' => $report->status->label(),
                    'submitted_at' => $report->submitted_at?->toIso8601String(),
                    'url' => route('projects.biweekly.index', $report->project_id),
                ]),
            'scoring' => ScoringRun::query()
                ->with([
                    'project:id,title,batch_id,proposal_status',
                    'rubricVersion.rubricSet:id,name,scoring_type',
                    'triggeredBy:id,name',
                ])
                ->where('status', ScoringStatus::PendingReview->value)
                ->whereHas('project', fn ($query) => $query->visibleTo($user)->where('proposal_status', ProposalStatus::Approved->value)
                    ->when(! $user->can('projects.viewAny'), fn ($project) => $project->whereHas('reviewAssignments', fn ($assignment) => $assignment->active()->where('reviewer_id', $user->id)->where('scope', 'scoring'))))
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($query) use ($search): void {
                        $query->whereHas('project', fn ($project) => $project->where('title', 'like', "%{$search}%"))
                            ->orWhereHas('rubricVersion.rubricSet', fn ($rubric) => $rubric->where('name', 'like', "%{$search}%"));

                        if (ctype_digit($search)) {
                            $query->orWhere('project_id', (int) $search);
                        }
                    });
                })
                ->orderBy('created_at')
                ->paginate($perPage)
                ->withQueryString()
                ->through(fn (ScoringRun $run): array => [
                    'id' => $run->id,
                    'title' => 'Review AI Scoring · '.$run->project->title,
                    'description' => sprintf(
                        'PRP-%04d · %s · Diminta oleh %s',
                        $run->project_id,
                        $run->rubricVersion->rubricSet->name,
                        $run->triggeredBy->name,
                    ),
                    'status' => $run->status->value,
                    'status_label' => $run->status->label(),
                    'submitted_at' => Carbon::parse($run->completed_at ?? $run->created_at)->toIso8601String(),
                    'url' => route('admin.scoring.show', $run),
                ]),
            default => Project::query()
                ->with(['batch:id,name', 'proposer:id,name'])
                ->when(! $user->can('projects.viewAny'), fn ($query) => $query->whereIn('id', ReviewAssignment::query()->active()->where('reviewer_id', $user->id)->where('scope', 'proposal')->select('project_id')))
                ->whereIn('proposal_status', [ProposalStatus::Submitted->value, ProposalStatus::UnderReview->value])
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($query) use ($search): void {
                        $query->where('title', 'like', "%{$search}%")
                            ->orWhereHas('proposer', fn ($proposer) => $proposer->where('name', 'like', "%{$search}%"));

                        if (preg_match('/^PRP-0*(\d+)$/i', $search, $match) === 1) {
                            $query->orWhere('id', (int) $match[1]);
                        }
                    });
                })
                ->orderByRaw("CASE proposal_status WHEN 'SUBMITTED' THEN 0 WHEN 'UNDER_REVIEW' THEN 1 ELSE 2 END")
                ->orderByDesc('submitted_at')
                ->paginate($perPage)
                ->withQueryString()
                ->through(fn (Project $project): array => [
                    'id' => $project->id,
                    'title' => $project->title,
                    'description' => sprintf('PRP-%04d · %s · %s', $project->id, $project->batch->name, $project->proposer->name),
                    'status' => $project->proposal_status->value,
                    'status_label' => $project->proposal_status->label(),
                    'submitted_at' => $project->submitted_at?->toIso8601String(),
                    'url' => route('proposals.show', $project),
                ]),
        };

        return Inertia::render('approval/index', [
            'items' => $items,
            'counts' => $counts,
            'availableTabs' => $availableTabs,
            'filters' => ['tab' => $tab, 'search' => $search, 'per_page' => $perPage],
        ]);
    }
}
