<?php

namespace App\Actions\Teams;

use App\Models\Project;
use App\Models\TeamMatchingRun;
use App\Services\Teams\Matching\TeamMatchingInputFingerprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A proposal can only be approved once Owner QCC has confirmed a valid team for its current content.
 */
class EnsureConfirmedTeam
{
    public function __construct(
        private readonly TeamRuleValidator $teamRules,
        private readonly TeamMatchingInputFingerprint $fingerprint,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(Project $project): void
    {
        $confirmed = TeamMatchingRun::query()
            ->where('project_id', $project->id)
            ->whereNotNull('confirmed_at')
            ->latest('id')
            ->first();

        if ($confirmed === null) {
            throw ValidationException::withMessages(['team' => 'Susun dan konfirmasi tim proposal terlebih dahulu sebelum menyetujui.']);
        }

        if (! $this->fingerprint->matches($project, $confirmed->candidate_snapshot_json['input_fingerprint'] ?? null)) {
            throw ValidationException::withMessages(['team' => 'Isi proposal atau kebutuhan kompetensi berubah setelah tim dikonfirmasi. Perbarui rekomendasi dan konfirmasi tim kembali.']);
        }

        $activeMembers = DB::table('project_members')
            ->join('users', 'users.id', '=', 'project_members.user_id')
            ->where('project_members.project_id', $project->id)
            ->where('project_members.status', 'ACTIVE')
            ->whereNull('project_members.left_at')
            ->get(['project_members.user_id', 'users.organization_id', 'users.is_active', 'project_members.role_in_project']);

        if ($activeMembers->contains(fn (object $member): bool => ! $member->is_active)) {
            throw ValidationException::withMessages(['team' => 'Ada anggota tim yang sudah nonaktif. Konfirmasi ulang tim sebelum menyetujui.']);
        }

        $this->teamRules->validate($activeMembers
            ->map(fn (object $member): array => [
                'user_id' => (int) $member->user_id,
                'organization_id' => $member->organization_id === null ? null : (int) $member->organization_id,
                'role_in_project' => $member->role_in_project,
            ])->all());
    }
}
