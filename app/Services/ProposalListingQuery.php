<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ProposalListingQuery
{
    /**
     * @param  array{search?: string, status?: string, batch?: string, sort?: string, direction?: string}  $filters
     * @return Builder<Project>
     */
    public function build(User $user, array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? 'all';
        $batch = $filters['batch'] ?? 'all';
        $sort = $filters['sort'] ?? 'updated_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return Project::query()
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
            ->orderBy('id', $direction);
    }
}
