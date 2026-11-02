<?php

namespace App\Services\Teams;

use App\Models\MemberCompetency;
use App\Models\Project;
use App\Models\User;
use App\Services\Teams\Matching\TeamMatchingInputFingerprint;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Finds the people who could join a project team, based on the competencies the
 * project asks for. Candidates below requested minimum remain eligible so AI
 * can describe gaps and suggest coaching. Only work data leaves this class:
 * no email, phone or other private contact.
 */
class CandidateShortlist
{
    public const DEFAULT_LIMIT = 30;

    /** Roles whose holders can be put in a QCC team. */
    public const CANDIDATE_ROLES = ['Team Leader', 'SIMer / Member'];

    /** Availability values that take someone out of the shortlist. Empty means unknown, so still eligible. */
    public const UNAVAILABLE_STATUSES = ['UNAVAILABLE'];

    private const EFFECTIVE_LEVEL = "CASE WHEN member_competencies.verification_status = 'VERIFIED' THEN member_competencies.verified_level ELSE member_competencies.self_level END";

    public function __construct(private readonly TeamMatchingInputFingerprint $fingerprint) {}

    /**
     * @return array{
     *     requirements: list<array{competency_id: int, code: string, name: string, minimum_level: int|null}>,
     *     candidates: list<array{
     *         user_id: int, name: string, position: string|null, organization_id: int, organization: string,
     *         availability_status: string|null, matched_count: int, requirement_count: int,
     *         qcc_projects_count: int,
     *         competencies: list<array{competency_id: int, self_level: int, verified_level: int|null}>
     *     }>
     * }
     */
    public function for(Project $project, int $limit = self::DEFAULT_LIMIT): array
    {
        $requirements = $this->requirements($project);

        if ($requirements === []) {
            return ['requirements' => [], 'candidates' => []];
        }

        $ranked = $this->rankedQuery($project, max(1, min($limit, 100)))->get();

        return ['requirements' => $requirements, 'candidates' => $this->candidateDetails($project, $requirements, $ranked)];
    }

    /** @return array{requirements: list<array{competency_id: int, code: string, name: string, minimum_level: int|null}>, candidates: list<array<string, mixed>>} */
    public function search(Project $project, string $search, int $limit = self::DEFAULT_LIMIT): array
    {
        $requirements = $this->requirements($project);

        if ($requirements === [] || mb_strlen(trim($search)) < 2) {
            return ['requirements' => $requirements, 'candidates' => []];
        }

        $ranked = $this->rankedQuery($project, max(1, min($limit, 100)), trim($search))->get();

        return ['requirements' => $requirements, 'candidates' => $this->candidateDetails($project, $requirements, $ranked)];
    }

    /** @param list<int> $userIds @return array{requirements: list<array{competency_id: int, code: string, name: string, minimum_level: int|null}>, candidates: list<array<string, mixed>>} */
    public function forUsers(Project $project, array $userIds): array
    {
        $requirements = $this->requirements($project);

        if ($requirements === [] || $userIds === []) {
            return ['requirements' => $requirements, 'candidates' => []];
        }

        $ranked = $this->rankedQuery($project, null, null, array_values(array_unique($userIds)))->get();

        return ['requirements' => $requirements, 'candidates' => $this->candidateDetails($project, $requirements, $ranked)];
    }

    /** @return list<array{competency_id: int, code: string, name: string, minimum_level: int|null}> */
    private function requirements(Project $project): array
    {
        return DB::table('project_requirements')
            ->join('competency_master', 'competency_master.id', '=', 'project_requirements.competency_id')
            ->where('project_requirements.project_id', $project->id)
            ->orderBy('competency_master.code')
            ->get(['competency_master.id as competency_id', 'competency_master.code', 'competency_master.name', 'project_requirements.minimum_level'])
            ->map(fn (object $row): array => [
                'competency_id' => (int) $row->competency_id,
                'code' => (string) $row->code,
                'name' => (string) $row->name,
                'minimum_level' => $row->minimum_level === null ? null : (int) $row->minimum_level,
            ])
            ->all();
    }

    /** @param list<array{competency_id: int, code: string, name: string, minimum_level: int|null}> $requirements */
    private function candidateDetails(Project $project, array $requirements, Collection $ranked): array
    {
        $userIds = $ranked->pluck('user_id')->all();

        $people = User::query()
            ->whereIn('users.id', $userIds)
            ->with('organization:id,name')
            ->get(['id', 'name', 'position', 'organization_id', 'availability_status'])
            ->keyBy('id');

        $held = MemberCompetency::query()
            ->whereIn('user_id', $userIds)
            ->whereIn('competency_id', array_column($requirements, 'competency_id'))
            ->orderBy('competency_id')
            ->get(['user_id', 'competency_id', 'self_level', 'verified_level', 'verification_status'])
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
                'qcc_projects_count' => (int) $row->qcc_projects_count,
                'competencies' => array_values($held->get($row->user_id)?->map(fn (MemberCompetency $entry): array => [
                    'competency_id' => $entry->competency_id,
                    'self_level' => $entry->self_level,
                    'verified_level' => $entry->activeVerifiedLevel(),
                ])->all() ?? []),
            ];
        }

        return $candidates;
    }

    /**
     * What an AI run may see about the shortlist: IDs, organizations, availability,
     * competency levels and aggregate counts of prior QCC project experience.
     *
     * @param  array{requirements: list<array<string, mixed>>, candidates: list<array<string, mixed>>}  $shortlist
     * @return array<string, mixed>
     */
    public function snapshot(Project $project, array $shortlist): array
    {
        $userIds = array_column($shortlist['candidates'], 'user_id');
        $experience = $this->qccExperience($project, $userIds);

        return [
            'project_id' => $project->id,
            'generated_at' => now()->toIso8601String(),
            'input_fingerprint' => $this->fingerprint->for($project),
            'requirements' => array_map(fn (array $requirement): array => [
                'competency_id' => $requirement['competency_id'],
                'minimum_level' => $requirement['minimum_level'],
            ], $shortlist['requirements']),
            'candidates' => array_map(function (array $candidate) use ($experience): array {
                $candidateExperience = $experience->get($candidate['user_id']) ?? [
                    'projects_count' => 0,
                    'leader_projects_count' => 0,
                    'completed_projects_count' => 0,
                ];

                return [
                    'candidate_user_id' => $candidate['user_id'],
                    'organization_id' => $candidate['organization_id'],
                    'availability_status' => $candidate['availability_status'],
                    'competencies' => $candidate['competencies'],
                    'qcc_experience' => [
                        'projects_count' => $candidateExperience['projects_count'],
                        'leader_projects_count' => $candidateExperience['leader_projects_count'],
                        'completed_projects_count' => $candidateExperience['completed_projects_count'],
                    ],
                ];
            }, $shortlist['candidates']),
        ];
    }

    /**
     * @param  list<int>  $userIds
     * @return Collection<int|string, array{user_id: int, projects_count: int, leader_projects_count: int, completed_projects_count: int}>
     */
    private function qccExperience(Project $project, array $userIds): Collection
    {
        if ($userIds === []) {
            return collect();
        }

        $completedProjects = DB::table('qcc_steps')
            ->whereBetween('step_number', [1, 8])
            ->select('project_id')
            ->groupBy('project_id')
            ->havingRaw('COUNT(*) = ?', [8])
            ->havingRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) = ?', ['APPROVED', 8]);

        return DB::table('project_members as project_member')
            ->join('projects', 'projects.id', '=', 'project_member.project_id')
            ->leftJoinSub($completedProjects, 'completed_projects', 'completed_projects.project_id', '=', 'project_member.project_id')
            ->whereIn('project_member.user_id', $userIds)
            ->where('project_member.project_id', '<>', $project->id)
            ->whereNull('projects.deleted_at')
            ->groupBy('project_member.user_id')
            ->select('project_member.user_id')
            ->selectRaw('COUNT(DISTINCT project_member.project_id) as projects_count')
            ->selectRaw("COUNT(DISTINCT CASE WHEN project_member.role_in_project = 'LEADER' THEN project_member.project_id END) as leader_projects_count")
            ->selectRaw('COUNT(DISTINCT completed_projects.project_id) as completed_projects_count')
            ->get()
            ->map(fn (stdClass $row): array => [
                'user_id' => (int) $row->user_id,
                'projects_count' => (int) $row->projects_count,
                'leader_projects_count' => (int) $row->leader_projects_count,
                'completed_projects_count' => (int) $row->completed_projects_count,
            ])
            ->keyBy('user_id');
    }

    /**
     * One aggregate query: eligible people who hold at least one required
     * competency, including candidates below minimum level, best matches first.
     */
    public function rankedQuery(Project $project, ?int $limit = self::DEFAULT_LIMIT, ?string $search = null, ?array $userIds = null): Builder
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
            ->when($userIds !== null, fn (Builder $query): Builder => $query->whereIn('users.id', $userIds))
            ->when($search !== null && $search !== '', fn (Builder $query): Builder => $query->where(function (Builder $matching) use ($search): void {
                $matching->where('users.name', 'like', '%'.$search.'%')
                    ->orWhere('organizations.name', 'like', '%'.$search.'%')
                    ->orWhere('users.position', 'like', '%'.$search.'%');
            }))
            ->where(function (Builder $availability): void {
                $availability->whereNull('users.availability_status')
                    ->orWhereNotIn('users.availability_status', self::UNAVAILABLE_STATUSES);
            })
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
            ->selectRaw('member_competencies.user_id as user_id')
            ->selectRaw("SUM(CASE WHEN {$effective} >= COALESCE(project_requirements.minimum_level, 0) THEN 1 ELSE 0 END) as matched_count")
            ->selectRaw("SUM({$effective}) as level_sum")
            ->selectRaw("SUM(CASE WHEN member_competencies.verification_status = 'VERIFIED' THEN 1 ELSE 0 END) as verified_count")
            ->selectRaw('(SELECT COUNT(DISTINCT prior_member.project_id) FROM project_members as prior_member INNER JOIN projects as prior_project ON prior_project.id = prior_member.project_id WHERE prior_member.user_id = users.id AND prior_member.project_id <> ? AND prior_project.deleted_at IS NULL) as qcc_projects_count', [$project->id])
            ->orderByDesc('matched_count')
            ->orderByDesc('level_sum')
            ->orderByDesc('verified_count')
            ->orderByRaw("CASE WHEN users.availability_status = 'AVAILABLE' THEN 0 ELSE 1 END")
            ->orderByRaw('(SELECT COUNT(*) FROM project_members as current_member WHERE current_member.project_id = ? AND current_member.status = \'ACTIVE\' AND current_member.left_at IS NULL AND current_member.user_id IN (SELECT same_org.id FROM users as same_org WHERE same_org.organization_id = users.organization_id)) ASC', [$project->id])
            ->orderByDesc('qcc_projects_count')
            ->orderBy('member_competencies.user_id')
            ->when($limit !== null, fn (Builder $query): Builder => $query->limit($limit));
    }
}
