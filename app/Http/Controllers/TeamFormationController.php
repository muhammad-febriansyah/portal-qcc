<?php

namespace App\Http\Controllers;

use App\Enums\ProposalStatus;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeamFormationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user->can('projects.view'), 403);
        abort_unless($user->can('projects.viewAny') || $user->hasRole('Team Leader'), 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));

        $proposals = Project::query()
            ->select(['id', 'batch_id', 'proposer_id', 'title', 'updated_at'])
            ->with(['batch:id,name', 'proposer:id,name,organization_id', 'proposer.organization:id,name'])
            ->withCount(['members', 'requirements'])
            ->whereIn('proposal_status', [ProposalStatus::Draft->value, ProposalStatus::RevisionRequired->value])
            ->when(! $user->can('projects.viewAny'), fn (Builder $query): Builder => $query->where('proposer_id', $user->id))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhereHas('proposer', fn (Builder $proposer): Builder => $proposer->where('name', 'like', "%{$search}%"));

                    if (preg_match('/^PRP-0*(\d+)$/i', $search, $match) === 1) {
                        $query->orWhere('id', (int) $match[1]);
                    }
                });
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Project $project): array => [
                'id' => $project->id,
                'code' => sprintf('PRP-%04d', $project->id),
                'title' => $project->title,
                'batch' => $project->batch?->name,
                'proposer' => $project->proposer?->name,
                'organization' => $project->proposer?->organization?->name,
                'members_count' => $project->members_count,
                'requirements_count' => $project->requirements_count,
                'updated_at' => $project->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('teams/index', [
            'proposals' => $proposals,
            'filters' => ['search' => $search],
            'can_create_proposal' => $user->can('projects.create'),
        ]);
    }
}
