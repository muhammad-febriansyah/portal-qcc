<?php

use App\Enums\ProposalStatus;
use App\Models\ActionItem;
use App\Models\AuditLog;
use App\Models\Competency;
use App\Models\MemberCompetency;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectRisk;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function approvedMember(Project $project, User $user, string $role): void
{
    DB::table('project_members')->insert([
        'project_id' => $project->id,
        'user_id' => $user->id,
        'role_in_project' => $role,
        'status' => 'ACTIVE',
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function approvedCandidate(string $name, Competency $competency, Organization $organization): User
{
    $user = User::factory()->create(['name' => $name, 'organization_id' => $organization->id]);
    $user->assignRole(Role::findOrCreate('SIMer / Member', 'web'));
    MemberCompetency::factory()->create(['user_id' => $user->id, 'competency_id' => $competency->id, 'self_level' => 3]);

    return $user;
}

/** @return list<array{0: int, 1: string}> */
function approvedActiveTeam(Project $project): array
{
    return DB::table('project_members')->where('project_id', $project->id)->where('status', 'ACTIVE')->whereNull('left_at')
        ->orderBy('user_id')->get(['user_id', 'role_in_project'])->map(fn ($row) => [(int) $row->user_id, $row->role_in_project])->all();
}

beforeEach(function () {
    $this->firstOrganization = Organization::factory()->create(['is_active' => true]);
    $this->secondOrganization = Organization::factory()->create(['is_active' => true]);
    $this->skill = Competency::factory()->create(['is_active' => true]);

    $this->project = Project::factory()->create(['proposal_status' => ProposalStatus::Approved, 'approved_at' => now()]);
    DB::table('project_requirements')->insert([
        'project_id' => $this->project->id, 'competency_id' => $this->skill->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->leader = approvedCandidate('Leader', $this->skill, $this->firstOrganization);
    $this->member = approvedCandidate('Anggota', $this->skill, $this->secondOrganization);
    approvedMember($this->project, $this->leader, 'LEADER');
    approvedMember($this->project, $this->member, 'MEMBER');
    $this->project->forceFill(['pic_id' => $this->leader->id])->save();

    $this->owner = User::factory()->create();
    $this->owner->assignRole(Role::findOrCreate('Owner QCC', 'web'));
    $this->owner->givePermissionTo(Permission::findOrCreate('projects.view', 'web'), Permission::findOrCreate('projects.viewAny', 'web'));

    $this->newcomer = approvedCandidate('Tambahan', $this->skill, $this->firstOrganization);
});

it('lets Owner QCC add a qualified person to an approved team and records who did it', function () {
    $this->actingAs($this->owner)
        ->post(route('proposals.team.members.store', $this->project), ['user_id' => $this->newcomer->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(approvedActiveTeam($this->project))->toEqualCanonicalizing([
        [$this->leader->id, 'LEADER'],
        [$this->member->id, 'MEMBER'],
        [$this->newcomer->id, 'MEMBER'],
    ])->and(AuditLog::query()->where('event', 'team.member_added')->sole()->actor_id)->toBe($this->owner->id);
});

it('refuses to add someone without the competency the project needs', function () {
    $stranger = User::factory()->create(['organization_id' => $this->firstOrganization->id]);
    $stranger->assignRole(Role::findOrCreate('SIMer / Member', 'web'));

    $this->actingAs($this->owner)
        ->post(route('proposals.team.members.store', $this->project), ['user_id' => $stranger->id])
        ->assertSessionHasErrors('user_id');

    expect(approvedActiveTeam($this->project))->toHaveCount(2)
        ->and(AuditLog::query()->where('event', 'team.member_added')->count())->toBe(0);
});

it('keeps the team at five members at most', function () {
    foreach (range(1, 3) as $n) {
        $extra = approvedCandidate("Tambahan {$n}", $this->skill, $n % 2 === 0 ? $this->secondOrganization : $this->firstOrganization);

        $this->actingAs($this->owner)
            ->post(route('proposals.team.members.store', $this->project), ['user_id' => $extra->id])
            ->assertSessionHasNoErrors();
    }

    $sixth = approvedCandidate('Keenam', $this->skill, $this->firstOrganization);

    $this->actingAs($this->owner)
        ->post(route('proposals.team.members.store', $this->project), ['user_id' => $sixth->id])
        ->assertSessionHasErrors('members');

    expect(approvedActiveTeam($this->project))->toHaveCount(5);
});

it('refuses to remove the leader and asks for a leader change first', function () {
    $this->actingAs($this->owner)
        ->delete(route('proposals.team.members.destroy', [$this->project, $this->leader]))
        ->assertSessionHasErrors('user_id');

    expect(approvedActiveTeam($this->project))->toEqualCanonicalizing([
        [$this->leader->id, 'LEADER'],
        [$this->member->id, 'MEMBER'],
    ]);
});

it('removes a member and keeps the team within the rules', function () {
    $this->actingAs($this->owner)->post(route('proposals.team.members.store', $this->project), ['user_id' => $this->newcomer->id]);

    $this->actingAs($this->owner)
        ->delete(route('proposals.team.members.destroy', [$this->project, $this->newcomer]))
        ->assertSessionHasNoErrors();

    expect(approvedActiveTeam($this->project))->toEqualCanonicalizing([
        [$this->leader->id, 'LEADER'],
        [$this->member->id, 'MEMBER'],
    ])->and(DB::table('project_members')->where('user_id', $this->newcomer->id)->value('status'))->toBe('REMOVED')
        ->and(AuditLog::query()->where('event', 'team.member_removed')->count())->toBe(1);
});

it('refuses a removal that leaves the team with only one LOB', function () {
    $this->actingAs($this->owner)->post(route('proposals.team.members.store', $this->project), ['user_id' => $this->newcomer->id]);

    $this->actingAs($this->owner)
        ->delete(route('proposals.team.members.destroy', [$this->project, $this->member]))
        ->assertSessionHasErrors('members');

    expect(approvedActiveTeam($this->project))->toHaveCount(3);
});

it('moves the PIC to the leader when the PIC leaves the team', function () {
    $otherSecond = approvedCandidate('Tambahan Dua', $this->skill, $this->secondOrganization);
    $this->project->forceFill(['pic_id' => $this->member->id])->save();
    $this->actingAs($this->owner)->post(route('proposals.team.members.store', $this->project), ['user_id' => $this->newcomer->id]);
    $this->actingAs($this->owner)->post(route('proposals.team.members.store', $this->project), ['user_id' => $otherSecond->id]);

    $this->actingAs($this->owner)
        ->delete(route('proposals.team.members.destroy', [$this->project, $this->member]))
        ->assertSessionHasNoErrors();

    expect($this->project->fresh()->pic_id)->toBe($this->leader->id);
});

it('does not change the team before the proposal is approved', function () {
    $this->project->forceFill(['proposal_status' => ProposalStatus::Submitted])->save();

    $this->actingAs($this->owner)
        ->post(route('proposals.team.members.store', $this->project), ['user_id' => $this->newcomer->id])
        ->assertSessionHasErrors('team');

    expect(approvedActiveTeam($this->project))->toHaveCount(2);
});

it('opens the approved team manager on the candidate page only for approved proposals', function () {
    $this->actingAs($this->owner)
        ->get(route('proposals.candidates', $this->project))
        ->assertInertia(fn (Assert $page) => $page
            ->component('proposals/candidates')
            ->where('can_manage_approved_team', true)
            ->where('can_edit_team', false));

    $this->project->forceFill(['proposal_status' => ProposalStatus::Submitted])->save();

    $this->actingAs($this->owner)
        ->get(route('proposals.candidates', $this->project))
        ->assertInertia(fn (Assert $page) => $page->where('can_manage_approved_team', false));
});

function approvedRisk(Project $project, User $pic, User $creator, string $status = 'OPEN'): ProjectRisk
{
    return ProjectRisk::query()->create([
        'project_id' => $project->id,
        'type' => 'RISK',
        'title' => 'Risiko uji',
        'description' => 'Deskripsi uji',
        'impact' => 'Dampak uji',
        'likelihood' => 'LOW',
        'priority' => 'LOW',
        'pic_id' => $pic->id,
        'status' => $status,
        'created_by' => $creator->id,
    ]);
}

function approvedAction(Project $project, User $pic, User $creator, string $status = 'OPEN'): ActionItem
{
    return ActionItem::query()->create([
        'project_id' => $project->id,
        'source_type' => 'PROJECT',
        'title' => 'Tindakan uji',
        'due_date' => now()->addWeek()->toDateString(),
        'pic_id' => $pic->id,
        'status' => $status,
        'created_by' => $creator->id,
    ]);
}

it('refuses to remove a member who still leads open items unless another member takes them', function () {
    $second = approvedCandidate('Tambahan Dua', $this->skill, $this->secondOrganization);
    $this->actingAs($this->owner)->post(route('proposals.team.members.store', $this->project), ['user_id' => $second->id]);
    $risk = approvedRisk($this->project, $this->member, $this->owner);

    $this->actingAs($this->owner)
        ->delete(route('proposals.team.members.destroy', [$this->project, $this->member]))
        ->assertSessionHasErrors('replacement_pic_id');

    expect($risk->fresh()->pic_id)->toBe($this->member->id)
        ->and(DB::table('project_members')->where('user_id', $this->member->id)->value('status'))->toBe('ACTIVE');
});

it('hands open items to the named replacement and leaves closed ones alone', function () {
    $second = approvedCandidate('Tambahan Dua', $this->skill, $this->secondOrganization);
    $this->actingAs($this->owner)->post(route('proposals.team.members.store', $this->project), ['user_id' => $second->id]);
    $openRisk = approvedRisk($this->project, $this->member, $this->owner);
    $closedRisk = approvedRisk($this->project, $this->member, $this->owner, 'CLOSED');
    $openAction = approvedAction($this->project, $this->member, $this->owner, 'BLOCKED');
    $doneAction = approvedAction($this->project, $this->member, $this->owner, 'COMPLETED');

    $this->actingAs($this->owner)
        ->delete(route('proposals.team.members.destroy', [$this->project, $this->member]), ['replacement_pic_id' => $second->id])
        ->assertSessionHasNoErrors();

    expect($openRisk->fresh()->pic_id)->toBe($second->id)
        ->and($openAction->fresh()->pic_id)->toBe($second->id)
        ->and($closedRisk->fresh()->pic_id)->toBe($this->member->id)
        ->and($doneAction->fresh()->pic_id)->toBe($this->member->id)
        ->and(AuditLog::query()->where('event', 'team.member_removed')->sole()->after_json['reassigned_items']['risk_ids'])->toBe([$openRisk->id])
        ->and($second->notifications()->where('data->decision', 'PIC_REASSIGNED')->count())->toBe(1)
        ->and($this->member->notifications()->where('data->decision', 'TEAM_MEMBER_REMOVED')->count())->toBe(1);
});

it('refuses a replacement who is not staying on the team', function () {
    $second = approvedCandidate('Tambahan Dua', $this->skill, $this->secondOrganization);
    $this->actingAs($this->owner)->post(route('proposals.team.members.store', $this->project), ['user_id' => $second->id]);
    approvedRisk($this->project, $this->member, $this->owner);

    $outsider = approvedCandidate('Luar Tim', $this->skill, $this->firstOrganization);

    foreach ([$outsider->id, $this->member->id] as $replacement) {
        $this->actingAs($this->owner)
            ->delete(route('proposals.team.members.destroy', [$this->project, $this->member]), ['replacement_pic_id' => $replacement])
            ->assertSessionHasErrors('replacement_pic_id');
    }

    expect(approvedActiveTeam($this->project))->toHaveCount(3);
});

it('refuses to remove the leader and says to change the leader first', function () {
    $this->actingAs($this->owner)
        ->delete(route('proposals.team.members.destroy', [$this->project, $this->leader]))
        ->assertSessionHasErrors('user_id');

    expect(approvedActiveTeam($this->project))->toHaveCount(2);
});

it('moves the leader role to another member and keeps exactly one leader', function () {
    $this->actingAs($this->owner)
        ->post(route('proposals.team.leader.update', $this->project), ['user_id' => $this->member->id])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(approvedActiveTeam($this->project))->toEqualCanonicalizing([
        [$this->leader->id, 'MEMBER'],
        [$this->member->id, 'LEADER'],
    ])->and($this->project->fresh()->pic_id)->toBe($this->leader->id)
        ->and(AuditLog::query()->where('event', 'team.leader_changed')->sole()->after_json['leader_id'])->toBe($this->member->id);
});

it('refuses a new leader who is not a plain member of the team', function (string $who) {
    $target = match ($who) {
        'outsider' => approvedCandidate('Luar Tim', $this->skill, $this->firstOrganization)->id,
        'current leader' => $this->leader->id,
    };

    $this->actingAs($this->owner)
        ->post(route('proposals.team.leader.update', $this->project), ['user_id' => $target])
        ->assertSessionHasErrors('user_id');

    expect(approvedActiveTeam($this->project))->toEqualCanonicalizing([
        [$this->leader->id, 'LEADER'],
        [$this->member->id, 'MEMBER'],
    ]);
})->with(['outsider', 'current leader']);

it('does not change the leader before the proposal is approved', function () {
    $this->project->forceFill(['proposal_status' => ProposalStatus::Submitted])->save();

    $this->actingAs($this->owner)
        ->post(route('proposals.team.leader.update', $this->project), ['user_id' => $this->member->id])
        ->assertSessionHasErrors('team');
});

it('shows how many open items each team member still holds', function () {
    approvedRisk($this->project, $this->member, $this->owner);
    approvedAction($this->project, $this->member, $this->owner);
    approvedAction($this->project, $this->member, $this->owner, 'COMPLETED');

    $this->actingAs($this->owner)
        ->get(route('proposals.candidates', $this->project))
        ->assertInertia(fn (Assert $page) => $page
            ->has('team', 2)
            ->where('team.1.user_id', $this->member->id)
            ->where('team.1.open_items', ['risks' => 1, 'actions' => 1])
            ->where('team.0.open_items', ['risks' => 0, 'actions' => 0]));
});

it('finds candidates by name on the approved team page, not only among the first thirty', function () {
    $this->actingAs($this->owner)
        ->get(route('proposals.candidates', $this->project).'?manual_search=Tambahan')
        ->assertInertia(fn (Assert $page) => $page
            ->where('manual_search', 'Tambahan')
            ->has('manual_candidates', 1)
            ->where('manual_candidates.0.name', 'Tambahan'));
});

it('is closed to members who may not manage the team', function () {
    $this->actingAs($this->member)
        ->post(route('proposals.team.members.store', $this->project), ['user_id' => $this->newcomer->id])
        ->assertForbidden();

    expect(approvedActiveTeam($this->project))->toHaveCount(2);
});
