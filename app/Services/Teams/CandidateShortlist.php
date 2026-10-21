<?php

namespace App\Services\Teams;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Finds the people who could join a project team, based on the competencies the
 * project asks for. Only work data leaves this class: no email, phone or other
 * private contact. The snapshot is the minimum an AI run is allowed to see.
 */
class CandidateShortlist
{
    public const DEFAULT_LIMIT = 30;

    /** Roles whose holders can be put in a QCC team. */
    public const CANDIDATE_ROLES = ['Team Leader', 'SIMer / Member'];

    /** Availability values that take someone out of the shortlist. Empty means unknown, so still eligible. */
    public const UNAVAILABLE_STATUSES = ['UNAVAILABLE'];

    private const EFFECTIVE_LEVEL = 'COALESCE(member_competencies.verified_level, member_competencies.self_level)';

    /**
     * @return array{
     *     requirements: list<array{competency_id: int, code: string, name: string, minimum_level: int|null}>,
     *     candidates: list<array{
     *         user_id: int, name: string, position: string|null, organization_id: int, organization: string,
     *         availability_status: string|null, matched_count: int, requirement_count: int,
     *         competencies: list<array{competency_id: int, self_level: int, verified_level: int|null}>
     *     }>
     * }
     */
    public function for(Project $project, int $limit = self::DEFAULT_LIMIT): array
    {
        $requirements = DB::table('project_requirements')
            ->join('competency_master', 'competency_master.id', '=', 'project_requirements.competency_id')
            ->where('project_requirements.project_id', $project->id)
            ->orderBy('competency_master.code')
            ->get(['competency_master.id as competency_id', 'competency_master.code', 'competency_master.name', 'project_requirements.minimum_level'])
            ->map(fn (object $row): array => [
                'competency_id' => (int) $row->competency_id,
                'code' => $row->code,
                'name' => $row->name,
                'minimum_level' => $row->minimum_level === null ? null : (int) $row->minimum_level,
            ])
            ->all();

        if ($requirements === []) {
            return ['requirements' => [], 'candidates' => []];
        }

        $ranked = $this->rankedQuery($project, max(1, min($limit, 100)))->get();
        $userIds = $ranked->pluck('user_id')->all();

        $people = User::query()
            ->whereIn('users.id', $userIds)
            ->with('organization:id,name')
            ->get(['id', 'name', 'position', 'organization_id', 'availability_status'])
            ->keyBy('id');

        $held = DB::table('member_competencies')
            ->whereIn('user_id', $userIds)
            ->whereIn('competency_id', array_column($requirements, 'competency_id'))
            ->orderBy('competency_id')
            ->get(['user_id', 'competency_id', 'self_level', 'verified_level'])
            ->groupBy('user_id');

        $candidates = [];

        foreach ($ranked as $row) {
            $person = $people->get($row->user_id);

            if ($person === null) {
                continue;
            }

            $candidates[] = [
                'user_id' => $person->id,
                'name' => $person->name,
                'position' => $person->position,
                'organization_id' => (int) $person->organization_id,
                'organization' => $person->organization->name,
                'availability_status' => $person->availability_status,
                'matched_count' => (int) $row->matched_count,
                'requirement_count' => count($requirements),
                'competencies' => $held->get($row->user_id, collect())->map(fn (object $entry): array => [
                    'competency_id' => (int) $entry->competency_id,
                    'self_level' => (int) $entry->self_level,
                    'verified_level' => $entry->verified_level === null ? null : (int) $entry->verified_level,
                ])->values()->all(),
            ];
        }

        return ['requirements' => $requirements, 'candidates' => $candidates];
    }

    /**
     * What an AI run may see about the shortlist: IDs, organizations, availability and levels.
     *
     * @param  array{requirements: list<array<string, mixed>>, candidates: list<array<string, mixed>>}  $shortlist
     * @return array<string, mixed>
     */
    public function snapshot(Project $project, array $shortlist): array
    {
        return [
            'project_id' => $project->id,
            'generated_at' => now()->toIso8601String(),
            'requirements' => array_map(fn (array $requirement): array => [
                'competency_id' => $requirement['competency_id'],
                'minimum_level' => $requirement['minimum_level'],
            ], $shortlist['requirements']),
            'candidates' => array_map(fn (array $candidate): array => [
                'candidate_user_id' => $candidate['user_id'],
                'organization_id' => $candidate['organization_id'],
                'availability_status' => $candidate['availability_status'],
                'competencies' => $candidate['competencies'],
            ], $shortlist['candidates']),
        ];
    }

    /**
     * One aggregate query: eligible people who hold at least one required competency
     * at or above its minimum level, best matches first, bounded by the limit.
     */
    public function rankedQuery(Project $project, int $limit = self::DEFAULT_LIMIT): Builder
    {
        $effective = self::EFFECTIVE_LEVEL;

        return DB::table('member_competencies')
            ->join('project_requirements', function ($join) use ($project): void {
                $join->on('project_requirements.competency_id', '=', 'member_competencies.competency_id')
                    ->where('project_requirements.project_id', '=', $project->id);
            })
            ->join('users', 'users.id', '=', 'member_competencies.user_id')
            ->join('organizations', 'organizations.id', '=', 'users.organization_id')
            ->where('users.is_active', true)
            ->where('organizations.is_active', true)
            ->where(function (Builder $availability): void {
                $availability->whereNull('users.availability_status')
                    ->orWhereNotIn('users.availability_status', self::UNAVAILABLE_STATUSES);
            })
            ->whereRaw("{$effective} >= COALESCE(project_requirements.minimum_level, 0)")
            ->whereIn('users.id', function (Builder $roles): void {
                $roles->select('model_has_roles.model_id')
                    ->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('model_has_roles.model_type', (new User)->getMorphClass())
                    ->whereIn('roles.name', self::CANDIDATE_ROLES);
            })
            ->whereNotExists(function (Builder $members) use ($project): void {
                $members->selectRaw('1')
                    ->from('project_members')
                    ->whereColumn('project_members.user_id', 'users.id')
                    ->where('project_members.project_id', $project->id)
                    ->where('project_members.status', 'ACTIVE')
                    ->whereNull('project_members.left_at');
            })
            ->groupBy('member_competencies.user_id')
            ->selectRaw('member_competencies.user_id as user_id, COUNT(*) as matched_count')
            ->selectRaw("SUM({$effective}) as level_sum")
            ->selectRaw('SUM(CASE WHEN member_competencies.verified_level IS NOT NULL THEN 1 ELSE 0 END) as verified_count')
            ->orderByDesc('matched_count')
            ->orderByDesc('level_sum')
            ->orderByDesc('verified_count')
            ->orderBy('member_competencies.user_id')
            ->limit($limit);
    }
}
