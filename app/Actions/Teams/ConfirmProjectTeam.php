<?php

namespace App\Actions\Teams;

use App\Enums\ProposalStatus;
use App\Models\Project;
use App\Models\TeamMatchingRun;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A person turns an AI suggestion, possibly after changing it, into the team.
 * Only people from the shortlist the run was built on, or who are already in the
 * team, can be chosen, and the team rules are checked again here whatever the
 * screen showed.
 */
class ConfirmProjectTeam
{
    public function __construct(
        private readonly ReplaceProjectTeam $replace,
        private readonly AuditService $audit,
    ) {}

    /**
     * @param  list<array{user_id: int, role_in_project: string}>  $members
     *
     * @throws ValidationException
     */
    public function handle(Project $project, TeamMatchingRun $run, array $members, User $actor): void
    {
        DB::transaction(function () use ($project, $run, $members, $actor): void {
            $lockedProject = Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();

            if (! in_array($lockedProject->proposal_status, [ProposalStatus::Draft, ProposalStatus::RevisionRequired], true)) {
                throw ValidationException::withMessages(['team' => 'Tim hanya bisa dikonfirmasi saat proposal masih draf atau perlu revisi.']);
            }

            $run = TeamMatchingRun::query()->whereKey($run->id)->lockForUpdate()->firstOrFail();

            if ($run->project_id !== $project->id || $run->status->value !== 'READY') {
                throw ValidationException::withMessages(['team' => 'Rekomendasi ini tidak bisa dipakai. Jalankan AI Matching lagi.']);
            }

            $latestId = TeamMatchingRun::query()->where('project_id', $project->id)->max('id');

            if ((int) $latestId !== $run->id) {
                throw ValidationException::withMessages(['team' => 'Sudah ada rekomendasi yang lebih baru. Muat ulang halaman dan pakai yang terbaru.']);
            }

            if ($run->confirmed_at !== null) {
                throw ValidationException::withMessages(['team' => 'Rekomendasi ini sudah dikonfirmasi sebelumnya.']);
            }

            $eligible = [
                ...array_column($run->candidate_snapshot_json['candidates'] ?? [], 'candidate_user_id'),
                ...array_column($this->currentTeam($project), 'user_id'),
            ];

            if (array_diff(array_column($members, 'user_id'), $eligible) !== []) {
                throw ValidationException::withMessages(['members' => ['Ada anggota yang bukan dari daftar kandidat rekomendasi ini.']]);
            }

            $before = $this->currentTeam($project);

            $this->replace->handle($project, $members);

            $suggested = collect($run->output_json['recommended_members'] ?? [])
                ->map(fn (array $member): string => $member['user_id'].':'.$member['proposed_role'])->sort()->values()->all();
            $chosen = collect($members)
                ->map(fn (array $member): string => $member['user_id'].':'.$member['role_in_project'])->sort()->values()->all();
            $isManual = $run->model === 'manual';

            $run->forceFill(['confirmed_by' => $actor->id, 'confirmed_at' => now()])->save();

            $this->audit->record(
                'team_matching.confirmed',
                $run,
                before: ['team' => $before],
                after: [
                    'team' => $this->currentTeam($project),
                    'source' => $isManual ? 'MANUAL' : 'AI',
                    'changed_from_ai' => ! $isManual && $suggested !== $chosen,
                ],
                subjectLabel: 'Proposal PRP-'.sprintf('%04d', $project->id),
            );
        });
    }

    /**
     * @return list<array{user_id: int, role: string}>
     */
    private function currentTeam(Project $project): array
    {
        return array_values(DB::table('project_members')
            ->where('project_id', $project->id)->where('status', 'ACTIVE')->whereNull('left_at')
            ->orderBy('user_id')->get(['user_id', 'role_in_project'])
            ->map(fn (object $row): array => ['user_id' => (int) $row->user_id, 'role' => (string) $row->role_in_project])
            ->all());
    }
}
