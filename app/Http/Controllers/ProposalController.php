<?php

namespace App\Http\Controllers;

use App\Actions\Proposals\ReviewProposal;
use App\Actions\Proposals\SubmitProposal;
use App\Actions\Teams\ConfirmProjectTeam;
use App\Actions\Teams\StartTeamMatching;
use App\Enums\ProposalStatus;
use App\Enums\TeamMatchingStatus;
use App\Http\Requests\ConfirmProjectTeamRequest;
use App\Http\Requests\ReviewProposalDecisionRequest;
use App\Http\Requests\SubmitProposalRequest;
use App\Models\MemberCompetency;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\QccBatch;
use App\Models\ReviewDecision;
use App\Models\TeamMatchingRun;
use App\Models\User;
use App\Services\Ai\AiConnection;
use App\Services\ProposalListingQuery;
use App\Services\Teams\CandidateShortlist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProposalController extends Controller
{
    public function index(Request $request, ProposalListingQuery $listing): Response
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

        $proposals = $listing->build($user, $filters)
            ->paginate($perPage)
            ->withQueryString();

        $canFindCandidates = fn (Project $project): bool => $user->can('projects.viewAny')
            || ($user->hasRole('Team Leader') && $project->proposer_id === $user->id);

        $drafts = Project::query()
            ->with('batch:id,name')
            ->where('proposer_id', $user->id)
            ->where('proposal_status', ProposalStatus::Draft->value)
            ->latest('updated_at')
            ->limit(5)
            ->get(['id', 'batch_id', 'title', 'wizard_step', 'updated_at'])
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'title' => $project->title,
                'batch' => $project->batch?->name,
                'wizard_step' => $project->wizard_step,
                'updated_at' => $project->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('proposals/index', [
            'drafts' => $drafts,
            'can_create' => $user->can('projects.create'),
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
                'can_edit_draft' => in_array($project->proposal_status, [ProposalStatus::Draft, ProposalStatus::RevisionRequired], true)
                    && $project->proposer_id === $user->id,
                'can_submit' => $project->proposer_id === $user->id
                    && in_array($project->proposal_status, [ProposalStatus::Draft, ProposalStatus::RevisionRequired], true)
                    && $user->can('submit', $project),
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
            'can_edit_team' => in_array($project->proposal_status, [ProposalStatus::Draft, ProposalStatus::RevisionRequired], true)
                && $request->user()->can('findCandidates', $project),
        ]);
    }

    public function show(Request $request, Project $project): Response
    {
        Gate::authorize('view', $project);

        $project->load([
            'batch:id,name',
            'proposer:id,name,organization_id',
            'proposer.organization:id,name',
            'attachments:id,project_id,original_name,mime_type,size_bytes,scan_status,created_at',
            'reviewDecisions.actor:id,name',
        ]);
        $teamConfirmed = TeamMatchingRun::query()
            ->where('project_id', $project->id)
            ->whereNotNull('confirmed_at')
            ->exists();

        return Inertia::render('proposals/show', [
            'proposal' => [
                'id' => $project->id,
                'code' => sprintf('PRP-%04d', $project->id),
                'title' => $project->title,
                'batch' => $project->batch?->name,
                'proposer' => $project->proposer?->name,
                'organization' => $project->proposer?->organization?->name,
                'status' => $project->proposal_status->value,
                'status_label' => $project->proposal_status->label(),
                'theme' => $project->theme,
                'background' => $project->background,
                'problem_statement' => $project->problem_statement,
                'current_situation' => $project->current_situation,
                'baseline' => $project->baseline,
                'target' => $project->target,
                'scope' => $project->scope,
                'learning_goals' => $project->learning_goals_json ?? [],
                'rca' => $project->rca,
                'improvement_plan' => $project->improvement_plan,
                'business_benefit' => $project->business_benefit,
                'updated_at' => $project->updated_at?->toIso8601String(),
                'attachments' => $project->attachments->map(fn (ProjectAttachment $attachment): array => [
                    'id' => $attachment->id,
                    'name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size_bytes' => $attachment->size_bytes,
                    'scan_status' => $attachment->scan_status,
                    'preview_url' => route('proposals.attachments.preview', [$project, $attachment]),
                ])->all(),
                'review_history' => $project->reviewDecisions->map(fn (ReviewDecision $decision): array => [
                    'id' => $decision->id,
                    'decision' => $decision->decision,
                    'previous_status' => $decision->previous_status,
                    'new_status' => $decision->new_status,
                    'reason' => $decision->reason,
                    'actor' => $decision->actor?->name,
                    'decided_at' => $decision->decided_at === null ? null : Carbon::parse($decision->decided_at)->toIso8601String(),
                ])->all(),
                'can_edit_draft' => in_array($project->proposal_status, [ProposalStatus::Draft, ProposalStatus::RevisionRequired], true)
                    && $project->proposer_id === $request->user()->id,
                'can_submit' => $project->proposer_id === $request->user()->id
                    && in_array($project->proposal_status, [ProposalStatus::Draft, ProposalStatus::RevisionRequired], true)
                    && $request->user()->can('projects.submit'),
                'team_confirmed' => $teamConfirmed,
                'team_status_message' => $request->user()->can('findCandidates', $project)
                    ? null
                    : ($teamConfirmed
                        ? 'Tim sudah dikonfirmasi. Proposal siap diajukan.'
                        : 'Tim akan disusun oleh Owner QCC. Proposal bisa diajukan setelah tim dikonfirmasi.'),
                'can_start_review' => $project->proposal_status === ProposalStatus::Submitted
                    && $request->user()->can('reviewProposal', $project),
                'can_decide' => $project->proposal_status === ProposalStatus::UnderReview
                    && ($request->user()->can('reviewProposal', $project) || $request->user()->can('approveProposal', $project)),
                'can_find_candidates' => $request->user()->can('projects.viewAny')
                    || ($request->user()->hasRole('Team Leader') && $project->proposer_id === $request->user()->id),
            ],
        ]);
    }

    public function startMatching(Request $request, Project $project, StartTeamMatching $start): RedirectResponse
    {
        Gate::authorize('findCandidates', $project);

        $start->handle($project, $request->user());

        return back();
    }

    public function confirmTeam(ConfirmProjectTeamRequest $request, Project $project, ConfirmProjectTeam $confirm, CandidateShortlist $shortlist): RedirectResponse
    {
        if ($request->boolean('manual')) {
            Gate::authorize('findCandidates', $project);

            if (! in_array($project->proposal_status, [ProposalStatus::Draft, ProposalStatus::RevisionRequired], true)) {
                abort(403);
            }

            $candidates = $shortlist->for($project);

            if ($candidates['candidates'] === []) {
                return back()->withErrors(['team' => 'Belum ada kandidat yang memenuhi syarat untuk disusun secara manual.']);
            }

            $snapshot = $shortlist->snapshot($project, $candidates);
            $run = DB::transaction(function () use ($project, $request, $snapshot): TeamMatchingRun {
                $lockedProject = Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();

                if (! in_array($lockedProject->proposal_status, [ProposalStatus::Draft, ProposalStatus::RevisionRequired], true)) {
                    throw ValidationException::withMessages(['team' => 'Tim hanya bisa dikonfirmasi saat proposal masih draf atau perlu revisi.']);
                }

                $latestRun = TeamMatchingRun::query()->where('project_id', $project->id)->latest('id')->first();

                if ($latestRun?->status->isActive()) {
                    throw ValidationException::withMessages(['matching' => 'Rekomendasi AI masih diproses. Tunggu selesai atau gagal sebelum menyusun tim manual.']);
                }

                $run = TeamMatchingRun::query()->create([
                    'project_id' => $project->id,
                    'requested_by' => $request->user()->id,
                    'status' => TeamMatchingStatus::Ready,
                    'idempotency_key' => (string) Str::uuid(),
                    'candidate_snapshot_json' => $snapshot,
                ]);

                $run->forceFill([
                    'model' => 'manual',
                    'prompt_version' => 'manual',
                    'validated_at' => now(),
                    'output_json' => ['recommended_members' => [], 'rule_problems' => [], 'team_gaps' => [], 'cautions' => [], 'mitigations' => []],
                ])->save();

                return $run;
            });
        } else {
            $run = TeamMatchingRun::query()->findOrFail($request->integer('run_id'));
        }

        $confirm->handle($project, $run, $request->validated('members'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tim proposal berhasil dikonfirmasi.']);

        return back();
    }

    public function submit(SubmitProposalRequest $request, Project $project, SubmitProposal $submit): RedirectResponse
    {
        $submit->handle($project, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Proposal berhasil diajukan untuk direview.']);

        return to_route('proposals.show', $project);
    }

    public function startReview(Request $request, Project $project, ReviewProposal $review): RedirectResponse
    {
        Gate::authorize('reviewProposal', $project);
        $review->start($project, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Review proposal dimulai.']);

        return back();
    }

    public function decide(ReviewProposalDecisionRequest $request, Project $project, ReviewProposal $review): RedirectResponse
    {
        $decision = $request->validated('decision');
        Gate::authorize($decision === 'APPROVE' ? 'approveProposal' : 'reviewProposal', $project);
        $review->decide($project, $request->user(), $decision, $request->validated('reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Keputusan review berhasil disimpan.']);

        return to_route('proposals.show', $project);
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

        $held = MemberCompetency::query()
            ->whereIn('user_id', $members->pluck('id'))
            ->whereIn('competency_id', $requiredCompetencyIds)
            ->get()
            ->groupBy('user_id');

        return array_values($members->map(fn (User $member): array => [
            'user_id' => $member->id,
            'name' => $member->name,
            'organization' => $member->organization?->name,
            'organization_id' => $member->organization_id,
            'role' => (string) $member->pivot->getAttribute('role_in_project'),
            'competencies' => $held->get($member->id)?->map(fn (MemberCompetency $row): array => [
                'competency_id' => $row->competency_id,
                'self_level' => $row->self_level,
                'verified_level' => $row->verified_level,
            ])->values()->all() ?? [],
        ])->all());
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
            'source' => $run->model === 'manual' ? 'MANUAL' : 'AI',
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
            'mitigations' => $output['mitigations'] ?? [],
            'rule_problems' => $output['rule_problems'] ?? [],
        ];
    }
}
