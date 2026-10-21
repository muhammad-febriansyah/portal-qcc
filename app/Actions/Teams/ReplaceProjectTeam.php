<?php

namespace App\Actions\Teams;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Set the active team of a project in one transaction. The project row is locked
 * first, so two people changing the same team are served one after the other and
 * each change is judged against the team the other one left behind. A team that
 * breaks the rules is refused as a whole and the current team stays untouched.
 */
class ReplaceProjectTeam
{
    public function __construct(private readonly TeamRuleValidator $rules) {}

    /**
     * @param  list<array{user_id: int, role_in_project: string}>  $members
     *
     * @throws ValidationException
     */
    public function handle(Project $project, array $members): void
    {
        DB::transaction(function () use ($project, $members): void {
            Project::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();

            $people = User::query()
                ->whereIn('id', array_column($members, 'user_id'))
                ->where('is_active', true)
                ->get(['id', 'organization_id'])
                ->keyBy('id');

            $unknown = array_filter($members, fn (array $member): bool => ! $people->has($member['user_id']));

            if ($unknown !== []) {
                throw ValidationException::withMessages(['members' => ['Ada anggota yang tidak ditemukan atau sudah nonaktif.']]);
            }

            $this->rules->validate(array_map(fn (array $member): array => [
                'user_id' => $member['user_id'],
                'organization_id' => $people->get($member['user_id'])->organization_id,
                'role_in_project' => $member['role_in_project'],
            ], $members));

            $now = now();
            $keptIds = array_column($members, 'user_id');

            DB::table('project_members')
                ->where('project_id', $project->id)
                ->where('status', 'ACTIVE')
                ->whereNull('left_at')
                ->whereNotIn('user_id', $keptIds)
                ->update(['status' => 'REMOVED', 'left_at' => $now, 'updated_at' => $now]);

            foreach ($members as $member) {
                $existing = DB::table('project_members')
                    ->where('project_id', $project->id)
                    ->where('user_id', $member['user_id'])
                    ->first();

                if ($existing === null) {
                    DB::table('project_members')->insert([
                        'project_id' => $project->id,
                        'user_id' => $member['user_id'],
                        'role_in_project' => $member['role_in_project'],
                        'status' => 'ACTIVE',
                        'joined_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    continue;
                }

                $wasActive = $existing->status === 'ACTIVE' && $existing->left_at === null;

                DB::table('project_members')->where('id', $existing->id)->update([
                    'role_in_project' => $member['role_in_project'],
                    'status' => 'ACTIVE',
                    'joined_at' => $wasActive ? $existing->joined_at : $now,
                    'left_at' => null,
                    'updated_at' => $now,
                ]);
            }
        });
    }
}
