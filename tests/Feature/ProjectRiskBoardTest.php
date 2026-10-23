<?php

use App\Enums\ProposalStatus;
use App\Models\ActionItem;
use App\Models\AuditLog;
use App\Models\BiweeklyReport;
use App\Models\Project;
use App\Models\ProjectRisk;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function riskPerson(string $role, array $permissions): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate($role, 'web'));
    $user->givePermissionTo(array_map(fn (string $name) => Permission::findOrCreate($name, 'web'), $permissions));

    return $user;
}

function riskMember(Project $project, string $role = 'Team Leader'): User
{
    $user = riskPerson($role, ['projects.view', 'projects.update']);
    DB::table('project_members')->insert([
        'project_id' => $project->id, 'user_id' => $user->id, 'role_in_project' => $role === 'Team Leader' ? 'LEADER' : 'MEMBER',
        'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $user;
}

function riskPayload(User $pic, array $overrides = []): array
{
    return [
        'type' => 'RISK', 'title' => 'Data survei terlambat', 'description' => 'Responden belum mengisi.',
        'impact' => 'Analisis mundur satu minggu.', 'likelihood' => 'MEDIUM', 'priority' => 'HIGH',
        'mitigation' => 'Kirim pengingat.', 'pic_id' => $pic->id, 'due_date' => '2026-12-01', 'status' => 'OPEN',
        ...$overrides,
    ];
}

function actionPayload(User $pic, array $overrides = []): array
{
    return ['title' => 'Kirim pengingat survei', 'description' => '', 'pic_id' => $pic->id, 'due_date' => '2026-11-20', 'status' => 'OPEN', ...$overrides];
}

beforeEach(function () {
    $this->project = Project::factory()->status(ProposalStatus::Approved)->create();
    $this->leader = riskMember($this->project);
    $this->member = riskMember($this->project, 'SIMer / Member');
});

it('records a risk with its PIC and due date and audits it', function () {
    $this->actingAs($this->leader)->post(route('projects.risks.store', $this->project), riskPayload($this->member))
        ->assertRedirect()->assertSessionHasNoErrors();

    $risk = ProjectRisk::query()->first();

    expect($risk->pic_id)->toBe($this->member->id)->and($risk->due_date->toDateString())->toBe('2026-12-01')
        ->and($risk->created_by)->toBe($this->leader->id)
        ->and(AuditLog::query()->where('event', 'risk.created')->first()->actor_id)->toBe($this->leader->id);
});

it('validates the risk form and keeps the PIC inside the team', function () {
    $outsider = User::factory()->create();

    $this->actingAs($this->leader)->post(route('projects.risks.store', $this->project), riskPayload($outsider))->assertSessionHasErrors('pic_id');
    $this->actingAs($this->leader)->post(route('projects.risks.store', $this->project), riskPayload($this->member, ['likelihood' => 'CRITICAL']))->assertSessionHasErrors('likelihood');
    $this->actingAs($this->leader)->post(route('projects.risks.store', $this->project), riskPayload($this->member, ['title' => '', 'type' => 'X']))->assertSessionHasErrors(['title', 'type']);

    DB::table('project_members')->where('user_id', $this->member->id)->update(['status' => 'REMOVED', 'left_at' => now()]);
    $this->actingAs($this->leader)->post(route('projects.risks.store', $this->project), riskPayload($this->member))->assertSessionHasErrors('pic_id');

    expect(ProjectRisk::query()->count())->toBe(0);
});

it('updates a risk, stamps the resolution time and audits the change', function () {
    $this->actingAs($this->leader)->post(route('projects.risks.store', $this->project), riskPayload($this->member));
    $risk = ProjectRisk::query()->first();

    $this->actingAs($this->leader)->patch(route('projects.risks.update', [$this->project, $risk]), riskPayload($this->member, ['status' => 'RESOLVED', 'priority' => 'LOW']))
        ->assertSessionHasNoErrors();

    $risk->refresh();
    $log = AuditLog::query()->where('event', 'risk.updated')->first();

    expect($risk->status->value)->toBe('RESOLVED')->and($risk->resolved_at)->not->toBeNull()
        ->and($log->before_json['priority'])->toBe('HIGH')->and($log->after_json['priority'])->toBe('LOW');

    $this->actingAs($this->leader)->patch(route('projects.risks.update', [$this->project, $risk]), riskPayload($this->member, ['status' => 'OPEN']));

    expect($risk->fresh()->resolved_at)->toBeNull();
});

it('shows scoped risk change history in the risk board', function () {
    $this->actingAs($this->leader)->post(route('projects.risks.store', $this->project), riskPayload($this->member));
    $risk = ProjectRisk::query()->firstOrFail();
    $this->actingAs($this->leader)->patch(route('projects.risks.update', [$this->project, $risk]), riskPayload($this->member, ['priority' => 'CRITICAL']));

    $this->actingAs($this->leader)->get(route('projects.risks.index', $this->project))
        ->assertInertia(fn (Assert $page) => $page
            ->where('risks.0.history.0.event', 'Risiko / isu diperbarui')
            ->where('risks.0.history.0.before.priority', 'HIGH')
            ->where('risks.0.history.0.after.priority', 'CRITICAL')
            ->where('risks.0.history.0.actor', $this->leader->name));
});

it('audits risk descriptions, mitigation and clearing the assigned PIC', function () {
    $this->actingAs($this->leader)->post(route('projects.risks.store', $this->project), riskPayload($this->member, [
        'description' => 'Kapasitas mesin terbatas.',
        'mitigation' => 'Jadwalkan perawatan tambahan.',
    ]));
    $risk = ProjectRisk::query()->firstOrFail();

    $this->actingAs($this->leader)->patch(route('projects.risks.update', [$this->project, $risk]), riskPayload($this->member, [
        'description' => 'Kapasitas mesin berkurang saat peak.',
        'mitigation' => 'Tambahkan jadwal perawatan mingguan.',
        'pic_id' => null,
    ]))->assertSessionHasNoErrors();

    $log = AuditLog::query()->where('event', 'risk.updated')->sole();

    expect($log->before_json['description'])->toBe('Kapasitas mesin terbatas.')
        ->and($log->after_json['description'])->toBe('Kapasitas mesin berkurang saat peak.')
        ->and($log->before_json['mitigation'])->toBe('Jadwalkan perawatan tambahan.')
        ->and($log->after_json['mitigation'])->toBe('Tambahkan jadwal perawatan mingguan.')
        ->and($log->before_json['pic_id'])->toBe($this->member->id)
        ->and($log->after_json['pic_id'])->toBeNull();

    $this->actingAs($this->leader)->get(route('projects.risks.index', $this->project))
        ->assertInertia(fn (Assert $page) => $page
            ->where('risks.0.history.0.after.pic_id', null)
            ->where('risks.0.history.0.after.mitigation', 'Tambahkan jadwal perawatan mingguan.'));
});

it('deletes a risk and keeps the actions that pointed at it', function () {
    $this->actingAs($this->leader)->post(route('projects.risks.store', $this->project), riskPayload($this->member));
    $risk = ProjectRisk::query()->first();
    $this->actingAs($this->leader)->post(route('projects.risks.actions.store', $this->project), actionPayload($this->member, ['source_type' => 'RISK', 'source_id' => $risk->id]));

    $this->actingAs($this->leader)->delete(route('projects.risks.destroy', [$this->project, $risk]))->assertRedirect();

    expect(ProjectRisk::query()->count())->toBe(0)
        ->and(ActionItem::query()->first())->source_type->toBe('PROJECT')->source_id->toBeNull()
        ->and(AuditLog::query()->where('event', 'risk.deleted')->count())->toBe(1);
});

it('tracks actions with a PIC, a due date and a lifecycle', function () {
    $this->actingAs($this->leader)->post(route('projects.risks.actions.store', $this->project), actionPayload($this->member))->assertSessionHasNoErrors();
    $item = ActionItem::query()->first();

    expect($item->status->value)->toBe('OPEN')->and($item->completed_at)->toBeNull()
        ->and($this->member->notifications()->count())->toBe(1)
        ->and($this->leader->notifications()->count())->toBe(0);

    foreach (['IN_PROGRESS', 'BLOCKED'] as $status) {
        $this->actingAs($this->leader)->patch(route('projects.risks.actions.update', [$this->project, $item]), actionPayload($this->member, ['status' => $status]))->assertSessionHasNoErrors();
    }

    $this->actingAs($this->leader)->patch(route('projects.risks.actions.update', [$this->project, $item]), actionPayload($this->member, ['status' => 'COMPLETED']));
    expect($item->fresh()->completed_at)->not->toBeNull();

    $this->actingAs($this->leader)->patch(route('projects.risks.actions.update', [$this->project, $item]), actionPayload($this->member, ['status' => 'OPEN']));
    expect($item->fresh()->completed_at)->toBeNull()
        ->and(AuditLog::query()->where('event', 'action_item.updated')->count())->toBe(4);
});

it('keeps report-linked action source when action status is changed from the tracker', function () {
    $report = BiweeklyReport::query()->create([
        'project_id' => $this->project->id,
        'period_no' => 1,
        'starts_on' => '2026-11-01',
        'ends_on' => '2026-11-14',
        'created_by' => $this->leader->id,
    ]);
    $action = ActionItem::query()->create([
        ...actionPayload($this->member, ['source_type' => 'BIWEEKLY_REPORT', 'source_id' => $report->id]),
        'project_id' => $this->project->id,
        'created_by' => $this->leader->id,
    ]);

    $this->actingAs($this->leader)->patch(
        route('projects.risks.actions.update', [$this->project, $action]),
        actionPayload($this->member, ['status' => 'IN_PROGRESS']),
    )->assertSessionHasNoErrors();

    expect($action->fresh()->status->value)->toBe('IN_PROGRESS')
        ->and($action->fresh()->source_type)->toBe('BIWEEKLY_REPORT')
        ->and($action->fresh()->source_id)->toBe($report->id);
});

it('needs a due date and a real risk to link an action to', function () {
    $this->actingAs($this->leader)->post(route('projects.risks.actions.store', $this->project), actionPayload($this->member, ['due_date' => '']))->assertSessionHasErrors('due_date');
    $this->actingAs($this->leader)->post(route('projects.risks.actions.store', $this->project), actionPayload($this->member, ['source_type' => 'RISK', 'source_id' => 999]))->assertSessionHasErrors('source_id');

    $otherRisk = ProjectRisk::query()->create([...riskPayload($this->member), 'project_id' => Project::factory()->create()->id, 'created_by' => $this->leader->id]);
    $this->actingAs($this->leader)->post(route('projects.risks.actions.store', $this->project), actionPayload($this->member, ['source_type' => 'RISK', 'source_id' => $otherRisk->id]))->assertSessionHasErrors('source_id');
});

it('does not touch records of another project', function () {
    $other = Project::factory()->create();
    $risk = ProjectRisk::query()->create([...riskPayload($this->member), 'project_id' => $other->id, 'created_by' => $this->leader->id]);

    $this->actingAs($this->leader)->patch(route('projects.risks.update', [$this->project, $risk]), riskPayload($this->member))->assertNotFound();
    $this->actingAs($this->leader)->delete(route('projects.risks.destroy', [$this->project, $risk]))->assertNotFound();
});

it('lists, filters and counts risks and actions', function () {
    $project = $this->project;
    $make = fn (array $o) => ProjectRisk::query()->create([...riskPayload($this->member), 'project_id' => $project->id, 'created_by' => $this->leader->id, ...$o]);
    $make(['title' => 'Kritis', 'priority' => 'CRITICAL', 'due_date' => '2020-01-01']);
    $make(['title' => 'Isu terbuka', 'type' => 'ISSUE', 'priority' => 'LOW', 'due_date' => '2099-01-01']);
    $make(['title' => 'Selesai', 'status' => 'RESOLVED', 'priority' => 'MEDIUM', 'due_date' => '2020-01-01']);
    ActionItem::query()->create([...actionPayload($this->member), 'project_id' => $project->id, 'created_by' => $this->leader->id, 'due_date' => '2020-01-01']);
    ActionItem::query()->create([...actionPayload($this->leader, ['title' => 'Selesai']), 'project_id' => $project->id, 'created_by' => $this->leader->id, 'due_date' => '2020-01-01', 'status' => 'COMPLETED']);

    $this->actingAs($this->leader)->get(route('projects.risks.index', $project))
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/risks')
            ->has('risks', 3)->where('risks.0.title', 'Kritis')->where('risks.0.is_overdue', true)
            ->where('summary.open_risks', 1)->where('summary.open_issues', 1)->where('summary.overdue_actions', 1)
            ->where('can_manage', true)->has('members', 2));

    $this->actingAs($this->leader)->get(route('projects.risks.index', [$project, 'type' => 'ISSUE']))->assertInertia(fn (Assert $page) => $page->has('risks', 1));
    $this->actingAs($this->leader)->get(route('projects.risks.index', [$project, 'priority' => 'CRITICAL']))->assertInertia(fn (Assert $page) => $page->has('risks', 1));
    $this->actingAs($this->leader)->get(route('projects.risks.index', [$project, 'tab' => 'risks', 'overdue' => 1]))->assertInertia(fn (Assert $page) => $page->has('risks', 1)->where('risks.0.title', 'Kritis'));
    $this->actingAs($this->leader)->get(route('projects.risks.index', [$project, 'tab' => 'actions', 'overdue' => 1]))->assertInertia(fn (Assert $page) => $page->has('actions', 1));
    $this->actingAs($this->leader)->get(route('projects.risks.index', [$project, 'tab' => 'actions', 'status' => 'COMPLETED']))->assertInertia(fn (Assert $page) => $page->has('actions', 1)->where('actions.0.title', 'Selesai'));
    $this->actingAs($this->leader)->get(route('projects.risks.index', [$project, 'tab' => 'actions', 'pic' => $this->leader->id]))->assertInertia(fn (Assert $page) => $page->has('actions', 1));
});

it('lets reviewers read but not write, and keeps strangers out', function () {
    $reviewer = riskPerson('Owner QCC', ['projects.view', 'projects.viewAny', 'projects.review']);

    $this->actingAs($reviewer)->get(route('projects.risks.index', $this->project))->assertInertia(fn (Assert $page) => $page->where('can_manage', false));
    $this->actingAs($reviewer)->post(route('projects.risks.store', $this->project), riskPayload($this->member))->assertForbidden();
    $this->actingAs($reviewer)->post(route('projects.risks.actions.store', $this->project), actionPayload($this->member))->assertForbidden();

    $stranger = riskPerson('Team Leader', ['projects.view', 'projects.update']);
    $this->actingAs($stranger)->get(route('projects.risks.index', $this->project))->assertForbidden();
    $this->actingAs($stranger)->post(route('projects.risks.store', $this->project), riskPayload($this->member))->assertForbidden();

    auth()->logout();
    $this->get(route('projects.risks.index', $this->project))->assertRedirect(route('login'));
    expect(ProjectRisk::query()->count())->toBe(0);
});
