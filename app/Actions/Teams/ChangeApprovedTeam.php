<?php

namespace App\Actions\Teams;

use App\Enums\ProposalStatus;
use App\Models\Project;
use App\Models\User;
use App\Services\AuditService;
use App\Services\ProjectNotifier;
use App\Services\Teams\CandidateShortlist;
use App\Services\Teams\PicOpenItems;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Changes an approved team: add or remove a member, or hand the leader role to another
 * member. Every change goes through ReplaceProjectTeam, so the team rules are checked
 * again and a change that breaks them is refused as a whole.
 */
class ChangeApprovedTeam
{
    public function __construct(
        private readonly ReplaceProjectTeam $replace,
        private readonly CandidateShortlist $shortlist,
        private readonly PicOpenItems $picItems,
        private readonly AuditService $audit,
        private readonly ProjectNotifier $notifier,
    ) {}

    /**
     * @throws ValidationException
     */
    public function add(Project $project, int $userId, User $actor): void
    {
        DB::transaction(function () use ($project, $userId, $actor): void {
            $lockedProject = $this->lockApprovedProject($project);

            if ($this->shortlist->forUsers($lockedProject, [$userId])['candidates'] === []) {
                throw ValidationException::withMessages(['user_id' => 'Orang ini tidak memenuhi kebutuhan kompetensi proyek, tidak tersedia, atau sudah menjadi anggota.']);
            }

            $before = $this->currentTeam($lockedProject);
            $after = [...$before, ['user_id' => $userId, 'role' => 'MEMBER']];

            $this->replace->handle($lockedProject, $this->toMembers($after));

            $this->audit->record(
                'team.member_added',
                $lockedProject,
                before: ['team' => $before],
                after: ['team' => $after, 'user_id' => $userId],
                subjectLabel: $this->label($lockedProject),
            );

            $this->notifier->notify(
                User::query()->whereKey($userId)->get(),
                $lockedProject,
                'Anda ditambahkan ke tim proposal',
                'Anda ditambahkan ke tim untuk '.$lockedProject->title.'.',
                route('proposals.show', $lockedProject, false),
                'TEAM_MEMBER_ADDED',
                $actor,
            );
        });
    }

    /**
     * A person who still holds open risks or action items can only leave when those items
     * are handed to a remaining member in the same change.
     *
     * @throws ValidationException
     */
    public function remove(Project $project, int $userId, User $actor, ?int $replacementPicId = null): void
    {
        DB::transaction(function () use ($project, $userId, $actor, $replacementPicId): void {
            $lockedProject = $this->lockApprovedProject($project);
            $before = $this->currentTeam($lockedProject);

            $member = collect($before)->firstWhere('user_id', $userId);

            if ($member === null) {
                throw ValidationException::withMessages(['user_id' => 'Orang ini bukan anggota tim yang aktif.']);
            }

            if ($member['role'] === 'LEADER') {
                throw ValidationException::withMessages(['user_id' => 'Ganti leader dulu sebelum mengeluarkan leader dari tim.']);
            }

            $after = array_values(array_filter($before, fn (array $member): bool => $member['user_id'] !== $userId));

            $this->replace->handle($lockedProject, $this->toMembers($after));

            $openItems = $this->picItems->countsByPic($lockedProject)->get($userId, ['risks' => 0, 'actions' => 0]);
            $reassigned = null;

            if ($openItems['risks'] + $openItems['actions'] > 0) {
                if ($replacementPicId === null) {
                    throw ValidationException::withMessages(['replacement_pic_id' => "Orang ini masih PIC untuk {$openItems['risks']} risiko dan {$openItems['actions']} tindakan yang belum selesai. Pilih PIC pengganti dari anggota tim yang tetap."]);
                }

                if (! collect($after)->contains(fn (array $member): bool => $member['user_id'] === $replacementPicId)) {
                    throw ValidationException::withMessages(['replacement_pic_id' => 'PIC pengganti harus anggota tim yang tetap.']);
                }

                $reassigned = [
                    'replacement_pic_id' => $replacementPicId,
                    ...$this->picItems->reassign($lockedProject, $userId, $replacementPicId),
                ];

                $this->notifier->notify(
                    User::query()->whereKey($replacementPicId)->get(),
                    $lockedProject,
                    'Anda ditetapkan sebagai PIC',
                    'Anda menjadi PIC untuk '.count($reassigned['risk_ids']).' risiko dan '.count($reassigned['action_ids']).' tindakan di '.$lockedProject->title.'.',
                    route('proposals.show', $lockedProject, false),
                    'PIC_REASSIGNED',
                    $actor,
                );
            }

            if ($lockedProject->pic_id === $userId) {
                $leader = collect($after)->firstWhere('role', 'LEADER');
                $lockedProject->forceFill(['pic_id' => $leader['user_id']])->save();
            }

            $this->audit->record(
                'team.member_removed',
                $lockedProject,
                before: ['team' => $before],
                after: array_filter([
                    'team' => $after,
                    'user_id' => $userId,
                    'reassigned_items' => $reassigned,
                ], fn (mixed $value): bool => $value !== null),
                subjectLabel: $this->label($lockedProject),
            );

            $this->notifier->notify(
                User::query()->whereKey($userId)->get(),
                $lockedProject,
                'Anda dikeluarkan dari tim proposal',
                'Anda tidak lagi tercatat sebagai anggota tim untuk '.$lockedProject->title.'.',
                route('proposals.show', $lockedProject, false),
                'TEAM_MEMBER_REMOVED',
                $actor,
            );
        });
    }

    /**
     * The leader role moves to another member; the old leader stays in the team as a member.
     *
     * @throws ValidationException
     */
    public function changeLeader(Project $project, int $userId, User $actor): void
    {
        DB::transaction(function () use ($project, $userId, $actor): void {
            $lockedProject = $this->lockApprovedProject($project);
            $before = $this->currentTeam($lockedProject);

            if (! collect($before)->contains(fn (array $member): bool => $member['user_id'] === $userId && $member['role'] === 'MEMBER')) {
                throw ValidationException::withMessages(['user_id' => 'Leader baru harus anggota tim yang bukan leader saat ini.']);
            }

            $after = array_map(fn (array $member): array => [
                'user_id' => $member['user_id'],
                'role' => $member['user_id'] === $userId ? 'LEADER' : 'MEMBER',
            ], $before);

            $this->replace->handle($lockedProject, $this->toMembers($after));

            $this->audit->record(
                'team.leader_changed',
                $lockedProject,
                before: ['leader_id' => collect($before)->firstWhere('role', 'LEADER')['user_id'] ?? null],
                after: ['leader_id' => $userId, 'team' => $after],
                subjectLabel: $this->label($lockedProject),
            );

            $this->notifier->notify(
                User::query()->whereKey($userId)->get(),
                $lockedProject,
                'Anda ditetapkan sebagai leader tim',
                'Anda sekarang leader tim untuk '.$lockedProject->title.'.',
                route('proposals.show', $lockedProject, false),
                'TEAM_LEADER_CHANGED',
                $actor,
            );
        });
    }

    private function lockApprovedProject(Project $project): Project
    {
        $lockedProject = Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();

        if ($lockedProject->proposal_status !== ProposalStatus::Approved) {
            throw ValidationException::withMessages(['team' => 'Anggota tim hanya bisa diubah setelah proposal disetujui.']);
        }

        return $lockedProject;
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

    /**
     * @param  list<array{user_id: int, role: string}>  $team
     * @return list<array{user_id: int, role_in_project: string}>
     */
    private function toMembers(array $team): array
    {
        return array_map(fn (array $member): array => [
            'user_id' => $member['user_id'],
            'role_in_project' => $member['role'],
        ], $team);
    }

    private function label(Project $project): string
    {
        return 'Proposal PRP-'.sprintf('%04d', $project->id);
    }
}
