<?php

namespace App\Http\Controllers;

use App\Enums\ProposalStatus;
use App\Enums\StepStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectDirectoryController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->directory($request, 'projects', 'projects/directory');
    }

    public function steps(Request $request): Response
    {
        return $this->directory($request, 'steps', 'projects/directory', approvedOnly: true);
    }

    public function evidence(Request $request): Response
    {
        return $this->directory($request, 'evidence', 'projects/directory', approvedOnly: true);
    }

    public function biweekly(Request $request): Response
    {
        return $this->directory($request, 'biweekly', 'projects/directory', approvedOnly: true);
    }

    public function mentoring(Request $request): Response
    {
        return $this->directory($request, 'mentoring', 'projects/directory', approvedOnly: true);
    }

    public function risks(Request $request): Response
    {
        return $this->directory($request, 'risks', 'projects/directory', approvedOnly: true);
    }

    private function directory(Request $request, string $view, string $component, bool $approvedOnly = false): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->can('projects.view'), 403);

        if ($view === 'evidence') {
            abort_unless($user->can('evidence.view'), 403);
        }

        $projects = Project::query()
            ->select(['id', 'batch_id', 'proposer_id', 'title', 'proposal_status', 'updated_at'])
            ->visibleTo($user)
            ->with(['batch:id,name', 'proposer:id,name'])
            ->withCount('evidence')
            ->withCount(['steps as approved_steps_count' => fn (Builder $query): Builder => $query
                ->where('status', StepStatus::Approved->value)])
            ->when($approvedOnly, fn (Builder $query): Builder => $query
                ->where('proposal_status', ProposalStatus::Approved->value))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Project $project): array => [
                'id' => $project->id,
                'code' => sprintf('PRP-%04d', $project->id),
                'title' => $project->title,
                'batch' => $project->batch?->name,
                'proposer' => $project->proposer?->name,
                'status' => $project->proposal_status->value,
                'status_label' => $project->proposal_status->label(),
                'approved_steps_count' => $project->approved_steps_count,
                'evidence_count' => $project->evidence_count,
            ]);

        return Inertia::render($component, [
            'view' => $view,
            'projects' => $projects,
        ]);
    }
}
