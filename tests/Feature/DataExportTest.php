<?php

use App\Models\ActionItem;
use App\Models\AuditLog;
use App\Models\BiweeklyReport;
use App\Models\Project;
use App\Models\ProjectRisk;
use App\Models\ScoringRun;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

require_once __DIR__.'/Scoring/helpers.php';

function exportMember(): User
{
    $member = User::factory()->create();
    $member->assignRole(Role::findOrCreate('SIMer / Member', 'web'));
    $member->givePermissionTo(Permission::findOrCreate('projects.view', 'web'));

    return $member;
}

test('member proposal export contains only projects in their listing scope and records the export', function () {
    $member = exportMember();
    Project::factory()->create(['title' => '=1+1', 'proposer_id' => $member->id]);
    Project::factory()->create(['title' => 'Proyek orang lain']);

    $response = $this->actingAs($member)->get(route('exports.proposals', ['search' => '=1+1']));
    $csv = $response->streamedContent();

    expect($csv)
        ->toContain("'=1+1")
        ->not->toContain('Proyek orang lain');

    $audit = AuditLog::query()->where('event', 'data.exported')->sole();
    expect($audit->actor_id)->toBe($member->id)
        ->and($audit->after_json)->toMatchArray([
            'type' => 'daftar_proposal',
            'row_count' => 1,
            'filters' => ['search' => '=1+1'],
        ]);
});

test('mentor, team leader and member cannot export scoring without scoring view permission', function () {
    foreach (['Mentor', 'Team Leader', 'SIMer / Member'] as $roleName) {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($roleName, 'web'));
        $user->givePermissionTo(Permission::findOrCreate('projects.view', 'web'));

        $this->actingAs($user)->get(route('admin.scoring.export'))->assertForbidden();
    }

    expect(AuditLog::query()->where('event', 'data.exported')->exists())->toBeFalse();
});

test('biweekly export includes action item owner, due date and status', function () {
    $member = exportMember();
    $project = Project::factory()->create(['proposer_id' => $member->id]);
    $project->members()->attach($member->id, [
        'role_in_project' => 'MEMBER',
        'status' => 'ACTIVE',
        'joined_at' => now(),
    ]);
    $report = BiweeklyReport::query()->create([
        'project_id' => $project->id,
        'period_no' => 1,
        'starts_on' => '2026-10-01',
        'ends_on' => '2026-10-14',
        'created_by' => $member->id,
    ]);
    ActionItem::query()->create([
        'project_id' => $project->id,
        'source_type' => 'BIWEEKLY_REPORT',
        'source_id' => $report->id,
        'title' => 'Kirim data',
        'pic_id' => $member->id,
        'due_date' => '2026-10-30',
        'status' => 'OPEN',
        'created_by' => $member->id,
    ]);

    $response = $this->actingAs($member)->get(route('projects.biweekly.export', $project));
    $response->assertOk();
    $csv = $response->streamedContent();

    expect($csv)
        ->toContain('Kirim data')
        ->toContain($member->name)
        ->toContain('2026-10-30')
        ->toContain('Terbuka');
});

test('scoring export keeps ai, reviewer and final scores in separate columns', function () {
    $viewer = exportMember();
    $viewer->givePermissionTo(Permission::findOrCreate('projects.viewAny', 'web'));
    $viewer->givePermissionTo(Permission::findOrCreate('scoring.view', 'web'));
    $project = Project::factory()->create(['title' => 'Proyek skor']);
    $version = scoringRubric();
    $run = ScoringRun::factory()->create([
        'project_id' => $project->id,
        'rubric_version_id' => $version->id,
    ]);
    $result = $run->result()->create(['ai_weighted_score' => '81.00']);
    $result->forceFill([
        'reviewer_weighted_score' => '77.50',
        'final_score' => '79.25',
    ])->save();

    $csv = $this->actingAs($viewer)->get(route('admin.scoring.export'))->streamedContent();

    expect($csv)
        ->toContain('"Skor AI","Skor Reviewer","Skor Final"')
        ->toContain('81.00,77.50,79.25');
});

test('risk export honors active tab and status filters', function () {
    $member = exportMember();
    $project = Project::factory()->create(['proposer_id' => $member->id]);
    ProjectRisk::query()->create([
        'project_id' => $project->id,
        'type' => 'RISK',
        'title' => 'Risiko internal',
        'description' => 'Deskripsi risiko',
        'impact' => 'Keterlambatan',
        'likelihood' => 'MEDIUM',
        'priority' => 'HIGH',
        'pic_id' => $member->id,
        'status' => 'OPEN',
        'created_by' => $member->id,
    ]);
    ActionItem::query()->create([
        'project_id' => $project->id,
        'source_type' => 'PROJECT',
        'title' => 'Tindakan terpilih',
        'pic_id' => $member->id,
        'due_date' => '2026-10-30',
        'status' => 'OPEN',
        'created_by' => $member->id,
    ]);

    $csv = $this->actingAs($member)->get(route('projects.risks.export', [
        'project' => $project,
        'tab' => 'actions',
        'status' => 'OPEN',
    ]))->streamedContent();

    expect($csv)->toContain('Tindakan terpilih')->not->toContain('Risiko internal');
});
