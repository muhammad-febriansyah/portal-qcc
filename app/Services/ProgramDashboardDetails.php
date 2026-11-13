<?php

namespace App\Services;

use App\Enums\ProposalStatus;
use App\Enums\ThemeCategory;
use App\Models\Competency;
use App\Models\Organization;
use App\Models\Project;
use App\Models\QccBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The records behind one number of the program dashboard: the SIMers, proposals or LOB/Divs it
 * counts. Each metric belongs to a phase and is only given to viewers who may read that phase.
 */
class ProgramDashboardDetails
{
    /** Phase of each metric. Metrics marked with a key need one (a LOB/Div, skill, theme...). */
    public const METRICS = [
        'registered' => 'registration',
        'competency_filled' => 'registration',
        'submitters' => 'registration',
        'not_submitted' => 'registration',
        'proposals' => 'registration',
        'organizations' => 'registration',
        'organization_proposals' => 'registration',
        'competency_holders' => 'registration',
        'theme_proposals' => 'registration',
        'selection_proposals' => 'selection',
        'passed' => 'selection',
        'in_selection' => 'selection',
        'failed' => 'selection',
        'matched' => 'selection',
        'teams_formed' => 'selection',
        'teams_pending' => 'selection',
        'passed_organization' => 'selection',
        'matching_band' => 'selection',
        'team_size' => 'selection',
    ];

    public function __construct(private readonly ProgramDashboardOverview $overview) {}

    /**
     * Whether this viewer may open the metric for the batch today.
     */
    public function canView(User $viewer, QccBatch $batch, string $metric): bool
    {
        $phase = self::METRICS[$metric] ?? null;

        return $phase !== null
            && in_array($phase, $viewer->visibleProgramPhases(), true)
            && $this->overview->phaseIsReadable($batch, $phase);
    }

    /**
     * @return array{
     *     title: string,
     *     description: string,
     *     kind: 'people'|'proposals'|'organizations',
     *     rows: list<array<string, mixed>>
     * }
     */
    public function get(User $viewer, QccBatch $batch, string $metric, ?string $key = null): array
    {
        return match ($metric) {
            'registered' => $this->people(
                'SIMers terdaftar',
                'SIMers yang profilnya sudah lengkap.',
                $this->overview->participants()->whereNotNull('profile_completed_at'),
                $batch,
            ),
            'competency_filled' => $this->people(
                'Profil kompetensi terisi',
                'SIMers terdaftar yang sudah mengisi minimal satu skill.',
                $this->overview->participants()->whereNotNull('profile_completed_at')->has('memberCompetencies'),
                $batch,
            ),
            'submitters' => $this->people(
                'Sudah submit proposal',
                'SIMers yang mengajukan minimal satu proposal di batch ini.',
                $this->overview->participants()->whereIn('users.id', $this->overview->submittedProposals($batch)->select('proposer_id')),
                $batch,
            ),
            'not_submitted' => $this->people(
                'Belum submit proposal',
                $batch->participant_target === null
                    ? 'SIMers yang belum mengajukan proposal di batch ini.'
                    : 'Semua akun SIMers yang belum mengajukan proposal di batch ini. Angka di dashboard dihitung dari target peserta.',
                $this->overview->participants()->whereNotIn('users.id', $this->overview->submittedProposals($batch)->select('proposer_id')),
                $batch,
            ),
            'competency_holders' => $this->competencyHolders($batch, (string) $key),
            'organizations' => $this->organizations($batch),
            'proposals' => $this->proposals(
                $viewer,
                'Proposal diajukan',
                'Semua proposal batch ini yang sudah diajukan.',
                $this->overview->submittedProposals($batch),
            ),
            'organization_proposals' => $this->organizationProposals($viewer, $batch, $key, approvedOnly: false),
            'theme_proposals' => $this->themeProposals($viewer, $batch, (string) $key),
            'selection_proposals' => $this->proposals(
                $viewer,
                'Total proposal',
                'Semua proposal yang masuk seleksi.',
                $this->overview->submittedProposals($batch),
            ),
            'passed' => $this->proposals(
                $viewer,
                'Lolos seleksi',
                'Proposal yang disetujui pada seleksi.',
                $this->overview->submittedProposals($batch)->where('proposal_status', ProposalStatus::Approved->value),
            ),
            'failed' => $this->proposals(
                $viewer,
                'Tidak lolos',
                'Proposal yang ditolak pada seleksi.',
                $this->overview->submittedProposals($batch)->where('proposal_status', ProposalStatus::Rejected->value),
            ),
            'in_selection' => $this->proposals(
                $viewer,
                'Dalam seleksi',
                'Proposal yang belum diberi keputusan lolos atau tidak.',
                $this->overview->submittedProposals($batch)->whereNotIn('proposal_status', [ProposalStatus::Approved->value, ProposalStatus::Rejected->value]),
            ),
            'matched' => $this->approvedSubset($viewer, $batch, 'Matching AI', 'Proposal lolos yang sudah punya saran tim dari AI.',
                fn (Collection $approved): array => $this->overview->matchedProjectIds($approved)),
            'teams_formed' => $this->approvedSubset($viewer, $batch, 'Tim terbentuk', 'Proposal lolos yang timnya sudah terbentuk.',
                fn (Collection $approved): array => $approved->filter(fn (Project $project): bool => $this->overview->hasFormedTeam($project))->pluck('id')->all()),
            'teams_pending' => $this->approvedSubset($viewer, $batch, 'Belum ada tim', 'Proposal lolos yang timnya masih disusun.',
                fn (Collection $approved): array => $approved->reject(fn (Project $project): bool => $this->overview->hasFormedTeam($project))->pluck('id')->all()),
            'passed_organization' => $this->organizationProposals($viewer, $batch, $key, approvedOnly: true),
            'matching_band' => $this->matchingBand($viewer, $batch, (string) $key),
            'team_size' => $this->teamSize($viewer, $batch, (int) $key),
            default => throw new NotFoundHttpException,
        };
    }

    /**
     * @param  Builder<User>  $query
     * @return array{title: string, description: string, kind: 'people', rows: list<array<string, mixed>>}
     */
    private function people(string $title, string $description, Builder $query, QccBatch $batch): array
    {
        $users = $query
            ->with('organization:id,name')
            ->withCount([
                'memberCompetencies as competencies_count',
                'proposals as proposals_count' => fn (Builder $proposals) => $proposals
                    ->where('batch_id', $batch->id)
                    ->whereNotNull('submitted_at')
                    ->where('proposal_status', '!=', ProposalStatus::Draft->value),
            ])
            ->orderBy('name')
            ->get(['users.id', 'users.name', 'users.sim_id', 'users.organization_id', 'users.position']);

        return [
            'title' => $title,
            'description' => $description,
            'kind' => 'people',
            'rows' => $users->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'sim_id' => $user->sim_id,
                'position' => $user->position,
                'organization' => $user->organization?->name,
                'competencies' => (int) $user->competencies_count,
                'proposals' => (int) $user->proposals_count,
            ])->values()->all(),
        ];
    }

    /**
     * Registered SIMers holding one skill of the dictionary.
     *
     * @return array{title: string, description: string, kind: 'people', rows: list<array<string, mixed>>}
     */
    private function competencyHolders(QccBatch $batch, string $code): array
    {
        $competency = Competency::query()->where('code', $code)->firstOrFail();

        return $this->people(
            $competency->name,
            'SIMers terdaftar yang memiliki skill ini.',
            $this->overview->participants()
                ->whereNotNull('profile_completed_at')
                ->whereHas('memberCompetencies', fn (Builder $held) => $held->where('competency_id', $competency->id)),
            $batch,
        );
    }

    /**
     * @return array{title: string, description: string, kind: 'organizations', rows: list<array<string, mixed>>}
     */
    private function organizations(QccBatch $batch): array
    {
        $submitted = $this->overview->submittedProposals($batch)
            ->join('users', 'users.id', '=', 'projects.proposer_id')
            ->groupBy('users.organization_id')
            ->selectRaw('users.organization_id as organization_id, count(*) as proposals, count(distinct projects.proposer_id) as submitters')
            ->toBase()
            ->get()
            ->keyBy('organization_id');

        $rows = Organization::query()
            ->where('is_active', true)
            ->withCount(['users as participants_count' => fn (Builder $users) => $users
                ->where('is_active', true)
                ->whereHas('roles', fn (Builder $roles) => $roles->whereIn('name', ProgramDashboardOverview::PARTICIPANT_ROLES))])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Organization $organization): array => [
                'id' => $organization->id,
                'name' => $organization->name,
                'participants' => (int) $organization->participants_count,
                'submitters' => (int) ($submitted->get($organization->id)?->submitters ?? 0),
                'proposals' => (int) ($submitted->get($organization->id)?->proposals ?? 0),
            ])
            ->sortBy([['proposals', 'desc'], ['name', 'asc']])
            ->values()
            ->all();

        return [
            'title' => 'LOB/Div',
            'description' => 'LOB/Div aktif dengan jumlah SIMers dan proposal yang dikirim.',
            'kind' => 'organizations',
            'rows' => $rows,
        ];
    }

    /**
     * @return array{title: string, description: string, kind: 'proposals', rows: list<array<string, mixed>>}
     */
    private function organizationProposals(User $viewer, QccBatch $batch, ?string $key, bool $approvedOnly): array
    {
        $organization = Organization::query()->findOrFail((int) $key);
        $query = $this->overview->submittedProposals($batch)
            ->whereHas('proposer', fn (Builder $proposer) => $proposer->where('organization_id', $organization->id))
            ->when($approvedOnly, fn (Builder $query) => $query->where('proposal_status', ProposalStatus::Approved->value));

        return $this->proposals(
            $viewer,
            $organization->name,
            $approvedOnly ? 'Proposal lolos seleksi dari LOB/Div ini.' : 'Proposal yang diajukan SIMers LOB/Div ini.',
            $query,
        );
    }

    /**
     * @return array{title: string, description: string, kind: 'proposals', rows: list<array<string, mixed>>}
     */
    private function themeProposals(User $viewer, QccBatch $batch, string $key): array
    {
        $query = $this->overview->submittedProposals($batch);

        if ($key === 'UNCATEGORIZED') {
            return $this->proposals($viewer, 'Belum dikategorikan', 'Proposal yang dikirim sebelum kategori tema ada.', $query->whereNull('theme_category'));
        }

        $theme = ThemeCategory::tryFrom($key) ?? throw new NotFoundHttpException;

        return $this->proposals($viewer, $theme->label(), 'Proposal dengan kategori tema ini.', $query->where('theme_category', $theme->value));
    }

    /**
     * @param  callable(Collection<int, Project>): list<int>  $pick
     * @return array{title: string, description: string, kind: 'proposals', rows: list<array<string, mixed>>}
     */
    private function approvedSubset(User $viewer, QccBatch $batch, string $title, string $description, callable $pick): array
    {
        $ids = $pick($this->overview->approvedProposals($batch));

        return $this->proposals($viewer, $title, $description, Project::query()->whereKey($ids));
    }

    /**
     * @return array{title: string, description: string, kind: 'proposals', rows: list<array<string, mixed>>}
     */
    private function matchingBand(User $viewer, QccBatch $batch, string $band): array
    {
        $label = ProgramDashboardOverview::COVERAGE_BANDS[$band] ?? throw new NotFoundHttpException;

        return $this->approvedSubset($viewer, $batch, "Kecocokan kompetensi: {$label}", 'Tim terbentuk dalam kelompok kecocokan ini.',
            function (Collection $approved) use ($band): array {
                $formed = $approved->filter(fn (Project $project): bool => $this->overview->hasFormedTeam($project));

                return array_keys(array_filter($this->overview->coverageBands($formed), fn (string $value): bool => $value === $band));
            });
    }

    /**
     * @return array{title: string, description: string, kind: 'proposals', rows: list<array<string, mixed>>}
     */
    private function teamSize(User $viewer, QccBatch $batch, int $size): array
    {
        return $this->approvedSubset($viewer, $batch, "Tim {$size} orang", 'Tim terbentuk dengan jumlah anggota aktif ini.',
            fn (Collection $approved): array => $approved
                ->filter(fn (Project $project): bool => $this->overview->hasFormedTeam($project) && $project->members->count() === $size)
                ->pluck('id')
                ->all());
    }

    /**
     * Proposal rows, linked to the proposal page when this viewer may open it.
     *
     * @param  Builder<Project>  $query
     * @return array{title: string, description: string, kind: 'proposals', rows: list<array<string, mixed>>}
     */
    private function proposals(User $viewer, string $title, string $description, Builder $query): array
    {
        $projects = $query
            ->with('proposer:id,name,organization_id', 'proposer.organization:id,name')
            ->withCount(['members as team_size' => fn (Builder $members) => $members
                ->where('project_members.status', 'ACTIVE')
                ->whereNull('project_members.left_at')])
            ->orderByDesc('submitted_at')
            ->orderByDesc('projects.id')
            ->get(['projects.id', 'projects.title', 'projects.proposer_id', 'projects.theme_category', 'projects.proposal_status', 'projects.submitted_at']);

        $openable = Project::query()
            ->visibleTo($viewer)
            ->whereKey($projects->modelKeys())
            ->pluck('id')
            ->flip();

        return [
            'title' => $title,
            'description' => $description,
            'kind' => 'proposals',
            'rows' => $projects->map(fn (Project $project): array => [
                'id' => $project->id,
                'code' => sprintf('PRP-%04d', $project->id),
                'title' => $project->title,
                'proposer' => $project->proposer?->name,
                'organization' => $project->proposer?->organization?->name,
                'theme' => $project->theme_category?->label(),
                'status' => $project->proposal_status->label(),
                'status_key' => $project->proposal_status->value,
                'team_size' => (int) $project->team_size,
                'submitted_at' => $project->submitted_at?->toIso8601String(),
                'url' => $openable->has($project->id) ? route('proposals.show', $project) : null,
            ])->values()->all(),
        ];
    }
}
