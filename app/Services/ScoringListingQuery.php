<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ScoringRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ScoringListingQuery
{
    /**
     * @param  array{search?: string, status?: string, sort?: string, direction?: string}  $filters
     * @return Builder<ScoringRun>
     */
    public function build(User $user, array $filters): Builder
    {
        $query = ScoringRun::query()
            ->select([
                'scoring_runs.*',
                'projects.title as project_title',
                'rubric_sets.name as rubric_name',
                'rubric_sets.scoring_type',
            ])
            ->leftJoin('projects', 'projects.id', '=', 'scoring_runs.project_id')
            ->leftJoin('rubric_versions', 'rubric_versions.id', '=', 'scoring_runs.rubric_version_id')
            ->leftJoin('rubric_sets', 'rubric_sets.id', '=', 'rubric_versions.rubric_set_id')
            ->with(['rubricVersion.rubricSet', 'triggeredBy', 'result.reviewer']);

        $query->whereIn('scoring_runs.project_id', Project::query()
            ->visibleTo($user)
            ->when(! $user->can('projects.viewAny'), fn (Builder $projects): Builder => $projects->whereHas('reviewAssignments', fn (Builder $assignments): Builder => $assignments
                ->active()->where('reviewer_id', $user->id)->where('scope', 'scoring')))
            ->select('projects.id'));

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('projects.title', 'like', "%{$search}%")
                    ->orWhere('scoring_runs.project_id', $search);
            });
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('scoring_runs.status', $filters['status']);
        }

        $sortColumns = [
            'project_title' => 'projects.title',
            'status' => 'scoring_runs.status',
            'created_at' => 'scoring_runs.created_at',
        ];
        $sort = $filters['sort'] ?? 'created_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query
            ->orderBy($sortColumns[$sort], $direction)
            ->orderByDesc('scoring_runs.id');
    }
}
