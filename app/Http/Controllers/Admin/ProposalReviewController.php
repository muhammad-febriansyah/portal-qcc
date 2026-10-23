<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ReviewAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProposalReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isGlobalReviewer = $user->can('projects.viewAny') && $user->can('projects.review');
        $hasAssignedProposal = $user->can('projects.review') && ReviewAssignment::query()->active()
            ->where('reviewer_id', $user->id)->where('scope', 'proposal')->exists();

        abort_unless($isGlobalReviewer || $hasAssignedProposal, 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['pending', 'all', ...array_column(ProposalStatus::cases(), 'value')])],
            'per_page' => ['nullable', Rule::in([10, 25, 50])],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? 'pending';
        $perPage = (int) ($filters['per_page'] ?? 10);

        $proposals = Project::query()
            ->with(['batch:id,name', 'proposer:id,name,organization_id', 'proposer.organization:id,name'])
            ->when(! $user->can('projects.viewAny'), fn (Builder $query): Builder => $query->whereIn(
                'id',
                ReviewAssignment::query()->active()->where('reviewer_id', $user->id)->where('scope', 'proposal')->select('project_id'),
            ))
            ->when($status === 'pending', fn (Builder $query): Builder => $query->whereIn('proposal_status', [ProposalStatus::Submitted->value, ProposalStatus::UnderReview->value]))
            ->when(! in_array($status, ['pending', 'all'], true), fn (Builder $query): Builder => $query->where('proposal_status', $status))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhereHas('proposer', fn (Builder $proposer): Builder => $proposer->where('name', 'like', "%{$search}%"));

                    if (preg_match('/^PRP-0*(\d+)$/i', $search, $match) === 1) {
                        $query->orWhere('id', (int) $match[1]);
                    }
                });
            })
            ->orderByRaw("CASE proposal_status WHEN 'SUBMITTED' THEN 0 WHEN 'UNDER_REVIEW' THEN 1 ELSE 2 END")
            ->orderByDesc('submitted_at')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('admin/proposals/index', [
            'proposals' => $proposals->through(fn (Project $project): array => [
                'id' => $project->id,
                'code' => sprintf('PRP-%04d', $project->id),
                'title' => $project->title,
                'batch' => $project->batch?->name,
                'proposer' => $project->proposer?->name,
                'organization' => $project->proposer?->organization?->name,
                'status' => $project->proposal_status->value,
                'status_label' => $project->proposal_status->label(),
                'submitted_at' => $project->submitted_at?->toIso8601String(),
                'url' => route('proposals.show', $project),
            ]),
            'filters' => ['search' => $search, 'status' => $status, 'per_page' => $perPage],
            'statusOptions' => ProposalStatus::options(),
        ]);
    }
}
