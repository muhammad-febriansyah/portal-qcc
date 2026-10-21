<?php

namespace App\Http\Controllers;

use App\Enums\ProposalStatus;
use App\Models\Project;
use App\Models\QccBatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProposalController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user->can('projects.view'), 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['all', ...array_column(ProposalStatus::cases(), 'value')])],
            'batch' => ['nullable', 'string', 'regex:/^(all|\d+)$/'],
            'sort' => ['nullable', Rule::in(['code', 'title', 'proposal_status', 'updated_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', Rule::in([10, 25, 50])],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? 'all';
        $batch = $filters['batch'] ?? 'all';
        $sort = $filters['sort'] ?? 'updated_at';
        $direction = $filters['direction'] ?? 'desc';
        $perPage = (int) ($filters['per_page'] ?? 10);

        $proposals = Project::query()
            ->select(['id', 'batch_id', 'proposer_id', 'title', 'proposal_status', 'updated_at'])
            ->with(['batch:id,name', 'proposer:id,name,organization_id', 'proposer.organization:id,name'])
            ->withCount('members')
            ->when(
                ! $user->can('projects.viewAny'),
                fn (Builder $query): Builder => $query->where(function (Builder $scoped) use ($user): void {
                    $scoped->where('proposer_id', $user->id)
                        ->orWhere(fn (Builder $member): Builder => $member->visibleTo($user));
                }),
            )
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $code = preg_match('/^PRP-0*(\d+)$/i', $search, $match) ? (int) $match[1] : null;

                    $query->where('title', 'like', "%{$search}%")
                        ->orWhereHas('proposer', fn (Builder $proposer): Builder => $proposer->where('name', 'like', "%{$search}%"));

                    if ($code !== null) {
                        $query->orWhere('id', $code);
                    }
                });
            })
            ->when($status !== 'all', fn (Builder $query): Builder => $query->where('proposal_status', $status))
            ->when($batch !== 'all', fn (Builder $query): Builder => $query->where('batch_id', (int) $batch))
            ->orderBy($sort === 'code' ? 'id' : $sort, $direction)
            ->orderBy('id', $direction)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('proposals/index', [
            'proposals' => $proposals->through(fn (Project $project): array => [
                'id' => $project->id,
                'code' => sprintf('PRP-%04d', $project->id),
                'title' => $project->title,
                'batch' => $project->batch?->name,
                'proposer' => $project->proposer?->name,
                'organization' => $project->proposer?->organization?->name,
                'members_count' => $project->members_count,
                'status' => $project->proposal_status->value,
                'updated_at' => $project->updated_at?->toIso8601String(),
            ]),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'batch' => $batch,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            'statusOptions' => ProposalStatus::options(),
            'batchOptions' => QccBatch::query()->orderByDesc('starts_on')->get(['id', 'name'])
                ->map(fn (QccBatch $batch): array => ['value' => (string) $batch->id, 'label' => $batch->name])->all(),
        ]);
    }
}
