<?php

namespace App\Http\Controllers;

use App\Actions\Proposals\ReviewProposal;
use App\Actions\Proposals\SubmitProposal;
use App\Actions\Teams\ConfirmProjectTeam;
use App\Actions\Teams\StartTeamMatching;
use App\Enums\ProposalStatus;
use App\Enums\QccStep;
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
use App\Services\Teams\Matching\TeamMatchingInputFingerprint;
use App\Services\Teams\PicOpenItems;
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
            'can_manage_teams' => $user->can('projects.viewAny') || $user->hasRole('Team Leader'),
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

    public function candidates(Request $request, Project $project, CandidateShortlist $shortlist, AiConnection $ai, TeamMatchingInputFingerprint $fingerprint, PicOpenItems $picItems): Response
    {
        Gate::authorize('findCandidates', $project);

        // The matching screen polls every few seconds while a run is active; it only needs the run.
        if ($request->header('X-Inertia-Partial-Data') === 'matching') {
            return Inertia::render('proposals/candidates', [
                'matching' => $this->latestMatching($project, $fingerprint),
            ]);
        }

        $result = $shortlist->for($project);
        $manualSearch = trim((string) ($request->validate(['manual_search' => ['nullable', 'string', 'max:255']])['manual_search'] ?? ''));
        $manualResult = $manualSearch === '' ? ['candidates' => []] : $shortlist->search($project, $manualSearch, 100);

        return Inertia::render('proposals/candidates', [
            'proposal' => [
                'id' => $project->id,
                'code' => sprintf('PRP-%04d', $project->id),
                'title' => $project->title,
                'pic_id' => $project->pic_id,
            ],
            'requirements' => $result['requirements'],
            'candidates' => $result['candidates'],
            'manual_candidates' => $manualResult['candidates'],
            'manual_search' => $manualSearch,
            'limit' => CandidateShortlist::DEFAULT_LIMIT,
            'ai' => [
                'ready' => $ai->isReady(),
                'message' => AiConnection::MESSAGE_NOT_READY,
                'can_manage' => $request->user()->can('system-settings.manage'),
            ],
            'matching' => $this->latestMatching($project, $fingerprint),
            'team' => $this->currentTeam($project, array_column($result['requirements'], 'competency_id'), $picItems),
            'can_edit_team' => $project->proposal_status->allowsTeamFormation()
                && $request->user()->can('findCandidates', $project),
            'can_manage_approved_team' => $project->proposal_status === ProposalStatus::Approved
                && $request->user()->can('findCandidates', $project),
        ]);
    }

    public function show(Request $request, Project $project, TeamMatchingInputFingerprint $fingerprint): Response
    {
        Gate::authorize('view', $project);

        $project->load([
            'batch:id,name',
            'proposer:id,name,organization_id',
            'proposer.organization:id,name',
            'pic:id,name',
            'attachments:id,project_id,original_name,mime_type,size_bytes,scan_status,created_at',
            'reviewDecisions.actor:id,name',
        ]);
        $confirmedRun = TeamMatchingRun::query()
            ->where('project_id', $project->id)
            ->whereNotNull('confirmed_at')
            ->latest('id')
            ->first();
        $teamConfirmed = $confirmedRun !== null
            && $fingerprint->matches($project, $confirmedRun->candidate_snapshot_json['input_fingerprint'] ?? null);

        return Inertia::render('proposals/show', [
            'proposal' => [
                'id' => $project->id,
                'code' => sprintf('PRP-%04d', $project->id),
                'title' => $project->title,
                'batch' => $project->batch?->name,
                'proposer' => $project->proposer?->name,
                'pic' => $project->pic?->name,
                'organization' => $project->proposer?->organization?->name,
                'status' => $project->proposal_status->value,
                'status_label' => $project->proposal_status->label(),
                'theme' => $project->theme,
                'background' => $project->background,
                'current_situation' => $project->current_situation,
                'improvement_plan' => $project->improvement_plan,
                'target' => $project->target,
                'scope' => $project->scope,
                'supplementary_information' => $project->supplementary_information,
                'cost_projection' => $project->cost_projection,
                'learning_goals' => $project->learning_goals_json ?? [],
                'step_timeline' => $this->stepTimeline($project),
                // Sections removed from the form; still shown for proposals written before the change.
                'legacy_sections' => array_values(array_filter([
                    ['label' => 'Pernyataan Masalah', 'value' => $project->problem_statement],
                    ['label' => 'Root Cause Analysis (RCA)', 'value' => $project->rca],
                    ['label' => 'Manfaat Bisnis', 'value' => $project->business_benefit],
                ], fn (array $section): bool => filled($section['value']))),
                'updated_at' => $project->updated_at?->toIso8601String(),
                'attachments' => $project->attachments->map(fn (ProjectAttachment $attachment): array => [
                    'id' => $attachment->id,
                    'name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size_bytes' => $attachment->size_bytes,
                    'scan_status' => $attachment->scan_status,
                    'preview_url' => $attachment->scan_status === 'PASSED'
                        ? route('proposals.attachments.preview', [$project, $attachment])
                        : null,
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
                        ? 'Tim sudah dikonfirmasi oleh Owner QCC.'
                        : 'Tim akan disusun oleh Owner QCC setelah proposal diajukan.'),
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

            if (! $project->proposal_status->allowsTeamFormation()) {
                abort(403);
            }

            $candidates = $shortlist->forUsers($project, array_map('intval', array_column($request->validated('members'), 'user_id')));

            $snapshot = $shortlist->snapshot($project, $candidates);

            // A refused team must not leave a manual run behind that hides the AI suggestion.
            DB::transaction(function () use ($project, $request, $snapshot, $confirm): void {
                $lockedProject = Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();

                if (! $lockedProject->proposal_status->allowsTeamFormation()) {
                    throw ValidationException::withMessages(['team' => 'Tim hanya bisa disusun saat proposal diajukan, direview, atau perlu revisi.']);
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

                $confirm->handle($project, $run, $request->validated('members'), $request->integer('pic_id'), $request->user());
            });

            Inertia::flash('toast', ['type' => 'success', 'message' => 'Tim proposal berhasil dikonfirmasi.']);

            return back();
        }

        $confirm->handle(
            $project,
            TeamMatchingRun::query()->findOrFail($request->integer('run_id')),
            $request->validated('members'),
            $request->integer('pic_id'),
            $request->user(),
        );

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
     * @return list<array{step_number: int, name: string, target_date: string|null}>
     */
    private function stepTimeline(Project $project): array
    {
        $dates = collect($project->step_timeline_json ?? [])->pluck('target_date', 'step_number');

        return array_map(fn (QccStep $step): array => [
            'step_number' => $step->value,
            'name' => $step->label(),
            'target_date' => $dates->get($step->value),
        ], QccStep::cases());
    }

    /**
     * @param  list<int>  $requiredCompetencyIds
     * @return list<array<string, mixed>>
     */
    private function currentTeam(Project $project, array $requiredCompetencyIds, PicOpenItems $picItems): array
    {
        $members = $project->members()
            ->wherePivot('status', 'ACTIVE')->wherePivotNull('left_at')
            ->with('organization:id,name')
            ->orderByRaw("CASE project_members.role_in_project WHEN 'LEADER' THEN 0 ELSE 1 END")
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.organization_id', 'users.avatar_path']);

        $held = MemberCompetency::query()
            ->whereIn('user_id', $members->pluck('id'))
            ->whereIn('competency_id', $requiredCompetencyIds)
            ->get()
            ->groupBy('user_id');

        $openItems = $picItems->countsByPic($project);

        return array_values($members->map(fn (User $member): array => [
            'user_id' => $member->id,
            'name' => $member->name,
            'avatar' => $member->avatar,
            'organization' => $member->organization?->name,
            'organization_id' => $member->organization_id,
            'role' => (string) $member->pivot->getAttribute('role_in_project'),
            'open_items' => $openItems->get($member->id, ['risks' => 0, 'actions' => 0]),
            'competencies' => $held->get($member->id)?->map(fn (MemberCompetency $row): array => [
                'competency_id' => $row->competency_id,
                'self_level' => $row->self_level,
                'verified_level' => $row->activeVerifiedLevel(),
            ])->values()->all() ?? [],
        ])->all());
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestMatching(Project $project, TeamMatchingInputFingerprint $fingerprint): ?array
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
            'input_stale' => ! $fingerprint->matches($project, $run->candidate_snapshot_json['input_fingerprint'] ?? null),
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
