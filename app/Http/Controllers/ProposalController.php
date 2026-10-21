<?php

namespace App\Http\Controllers;

use App\Actions\Teams\ConfirmProjectTeam;
use App\Actions\Teams\StartTeamMatching;
use App\Http\Requests\ConfirmProjectTeamRequest;
use App\Enums\ProposalStatus;
use App\Models\Project;
use App\Models\QccBatch;
use App\Models\TeamMatchingRun;
use App\Models\User;
use App\Services\Ai\AiConnection;
use App\Services\Teams\CandidateShortlist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

        $canFindCandidates = fn (Project $project): bool => $user->can('projects.viewAny')
            || ($user->hasRole('Team Leader') && $project->proposer_id === $user->id);

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
                'can_find_candidates' => $canFindCandidates($project),
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

    public function candidates(Request $request, Project $project, CandidateShortlist $shortlist, AiConnection $ai): Response
    {
        Gate::authorize('findCandidates', $project);

        $result = $shortlist->for($project);

        return Inertia::render('proposals/candidates', [
            'proposal' => [
                'id' => $project->id,
                'code' => sprintf('PRP-%04d', $project->id),
                'title' => $project->title,
            ],
            'requirements' => $result['requirements'],
            'candidates' => $result['candidates'],
            'limit' => CandidateShortlist::DEFAULT_LIMIT,
            'ai' => [
                'ready' => $ai->isReady(),
                'message' => AiConnection::MESSAGE_NOT_READY,
                'can_manage' => $request->user()->can('system-settings.manage'),
            ],
            'matching' => $this->latestMatching($project),
            'team' => $this->currentTeam($project, array_column($result['requirements'], 'competency_id')),
        ]);
    }

    public function startMatching(Request $request, Project $project, StartTeamMatching $start): RedirectResponse
    {
        Gate::authorize('findCandidates', $project);

        $start->handle($project, $request->user());

        return back();
    }

    public function confirmTeam(ConfirmProjectTeamRequest $request, Project $project, ConfirmProjectTeam $confirm): RedirectResponse
    {
        $run = TeamMatchingRun::query()->findOrFail($request->integer('run_id'));

        $confirm->handle($project, $run, $request->validated('members'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tim proposal berhasil dikonfirmasi.']);

        return back();
    }

    /**
     * @param  list<int>  $requiredCompetencyIds
     * @return list<array<string, mixed>>
     */
    private function currentTeam(Project $project, array $requiredCompetencyIds = []): array
    {
        $members = $project->members()
            ->wherePivot('status', 'ACTIVE')->wherePivotNull('left_at')
            ->with('organization:id,name')
            ->orderByRaw("CASE project_members.role_in_project WHEN 'LEADER' THEN 0 ELSE 1 END")
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.organization_id']);

        $held = DB::table('member_competencies')
            ->whereIn('user_id', $members->pluck('id'))
            ->whereIn('competency_id', $requiredCompetencyIds)
            ->get(['user_id', 'competency_id', 'self_level', 'verified_level'])
            ->groupBy('user_id');

        return $members->map(fn (User $member): array => [
            'user_id' => $member->id,
            'name' => $member->name,
            'organization' => $member->organization?->name,
            'organization_id' => $member->organization_id,
            'role' => $member->pivot->role_in_project,
            'competencies' => $held->get($member->id, collect())->map(fn (object $row): array => [
                'competency_id' => (int) $row->competency_id,
                'self_level' => (int) $row->self_level,
                'verified_level' => $row->verified_level === null ? null : (int) $row->verified_level,
            ])->values()->all(),
        ])->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestMatching(Project $project): ?array
    {
        $run = TeamMatchingRun::query()->where('project_id', $project->id)->latest('id')->first();

        if ($run === null) {
            return null;
        }

        $snapshot = collect($run->candidate_snapshot_json['candidates'] ?? [])->keyBy('candidate_user_id');
        $names = User::query()->whereIn('id', $snapshot->keys())->with('organization:id,name')->get(['id', 'name', 'organization_id'])->keyBy('id');

        $output = $run->output_json;

        return [
            'id' => $run->id,
            'status' => $run->status->value,
            'status_label' => $run->status->label(),
            'error_code' => $run->error_code,
            'created_at' => $run->created_at?->toIso8601String(),
            'confirmed_at' => $run->confirmed_at?->toIso8601String(),
            'members' => collect($output['recommended_members'] ?? [])->map(fn (array $member): array => [
                'user_id' => $member['user_id'],
                'name' => $names->get($member['user_id'])?->name,
                'organization' => $names->get($member['user_id'])?->organization?->name,
                'organization_id' => $snapshot->get($member['user_id'])['organization_id'] ?? null,
                'competencies' => $snapshot->get($member['user_id'])['competencies'] ?? [],
                'proposed_role' => $member['proposed_role'],
                'matched_competency_ids' => $member['matched_competency_ids'],
                'match_reason' => $member['match_reason'],
                'contribution' => $member['contribution'],
                'gaps' => $member['gaps'],
                'uncertainties' => $member['uncertainties'],
            ])->all(),
            'team_gaps' => $output['team_gaps'] ?? [],
            'cautions' => $output['cautions'] ?? [],
            'rule_problems' => $output['rule_problems'] ?? [],
        ];
    }
}
