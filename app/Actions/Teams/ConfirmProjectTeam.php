<?php

namespace App\Actions\Teams;

use App\Models\Project;
use App\Models\TeamMatchingRun;
use App\Models\User;
use App\Services\AuditService;
use App\Services\ProjectNotifier;
use App\Services\Teams\Matching\TeamMatchingInputFingerprint;
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
        private readonly TeamMatchingInputFingerprint $fingerprint,
        private readonly ProjectNotifier $notifier,
    ) {}

    /**
     * @param  list<array{user_id: int, role_in_project: string}>  $members
     *
     * @throws ValidationException
     */
    public function handle(Project $project, TeamMatchingRun $run, array $members, int $picId, User $actor): void
    {
        DB::transaction(function () use ($project, $run, $members, $picId, $actor): void {
            $lockedProject = Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();

            if (! $lockedProject->proposal_status->allowsTeamFormation()) {
                throw ValidationException::withMessages(['team' => 'Tim hanya bisa disusun setelah proposal disetujui.']);
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

            if (! $this->fingerprint->matches($lockedProject, $run->candidate_snapshot_json['input_fingerprint'] ?? null)) {
                throw ValidationException::withMessages(['team' => 'Informasi proposal atau kebutuhan kompetensi berubah. Perbarui rekomendasi dan konfirmasi tim kembali.']);
            }

            $eligible = [
                ...array_column($run->candidate_snapshot_json['candidates'] ?? [], 'candidate_user_id'),
                ...array_column($this->currentTeam($project), 'user_id'),
            ];

            if (array_diff(array_column($members, 'user_id'), $eligible) !== []) {
                throw ValidationException::withMessages(['members' => ['Ada anggota yang bukan dari daftar kandidat rekomendasi ini.']]);
            }

            if (! in_array($picId, array_map('intval', array_column($members, 'user_id')), true)) {
                throw ValidationException::withMessages(['pic_id' => 'PIC proposal harus dipilih dari anggota tim yang dikonfirmasi.']);
            }

            $before = $this->currentTeam($project);
            $beforePicId = $lockedProject->pic_id;

            $this->replace->handle($project, $members);
            $lockedProject->forceFill(['pic_id' => $picId])->save();

            $suggested = collect($run->output_json['recommended_members'] ?? [])
                ->map(fn (array $member): string => $member['user_id'].':'.$member['proposed_role'])->sort()->values()->all();
            $chosen = collect($members)
                ->map(fn (array $member): string => $member['user_id'].':'.$member['role_in_project'])->sort()->values()->all();
            $isManual = $run->model === 'manual';

            $run->forceFill(['confirmed_by' => $actor->id, 'confirmed_at' => now()])->save();

            $this->audit->record(
                'team_matching.confirmed',
                $run,
                before: ['team' => $before, 'pic_id' => $beforePicId],
                after: [
                    'team' => $this->currentTeam($project),
                    'pic_id' => $picId,
                    'source' => $isManual ? 'MANUAL' : 'AI',
                    'changed_from_ai' => ! $isManual && $suggested !== $chosen,
                ],
                subjectLabel: 'Proposal PRP-'.sprintf('%04d', $project->id),
            );

            // The proposer and everyone placed on the team hear about it.
            $this->notifier->notify(
                User::query()->whereKey([$lockedProject->proposer_id, ...array_column($members, 'user_id')])->get(),
                $lockedProject,
                'Susunan tim proposal sudah dikonfirmasi',
                'Owner QCC sudah mengonfirmasi tim untuk '.$lockedProject->title.'. Proposal diputuskan setelah review selesai.',
                route('proposals.show', $lockedProject, false),
                'TEAM_COMPOSITION_CONFIRMED',
                $actor,
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
