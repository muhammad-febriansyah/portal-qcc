<?php

use App\Enums\ProposalStatus;
use App\Enums\ReportStatus;
use App\Models\AuditLog;
use App\Models\BiweeklyReport;
use App\Models\EvidenceLink;
use App\Models\MentoringReport;
use App\Models\Project;
use App\Models\ProjectEvidence;
use App\Models\ProjectMentor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function bmPerson(string $role, array $permissions): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate($role, 'web'));
    $user->givePermissionTo(array_map(fn (string $name) => Permission::findOrCreate($name, 'web'), $permissions));

    return $user;
}

function bmMember(Project $project, string $role = 'Team Leader'): User
{
    $user = bmPerson($role, ['projects.view', 'projects.update']);
    DB::table('project_members')->insert([
        'project_id' => $project->id, 'user_id' => $user->id, 'role_in_project' => $role === 'Team Leader' ? 'LEADER' : 'MEMBER',
        'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $user;
}

function bmOwner(): User
{
    return bmPerson('Owner QCC', ['projects.view', 'projects.viewAny', 'projects.review', 'projects.approve']);
}

function bmMentor(): User
{
    return bmPerson('Mentor', ['projects.view']);
}

function reportPayload(array $attendees, array $overrides = []): array
{
    return [
        'starts_on' => '2026-11-01', 'ends_on' => '2026-11-14', 'meeting_date' => '2026-11-14',
        'agenda' => 'Tinjau progres', 'summary' => 'Step 1 hampir selesai', 'planned' => 'Selesaikan step 1', 'actual' => 'Step 1 90%',
        'issues' => '', 'decisions' => '', 'attendee_ids' => $attendees, ...$overrides,
    ];
}

beforeEach(function () {
    $this->project = Project::factory()->status(ProposalStatus::Approved)->create();
    $this->leader = bmMember($this->project);
    $this->member = bmMember($this->project, 'SIMer / Member');
    $this->owner = bmOwner();
    $this->attendees = [$this->leader->id, $this->member->id];
});

it('numbers bi-weekly reports per project and keeps them as drafts until submitted', function () {
    $this->actingAs($this->leader)->post(route('projects.biweekly.store', $this->project), reportPayload($this->attendees))->assertSessionHasNoErrors();
    $this->actingAs($this->leader)->post(route('projects.biweekly.store', $this->project), reportPayload($this->attendees, ['starts_on' => '2026-11-15', 'ends_on' => '2026-11-28']));

    $other = Project::factory()->create();
    BiweeklyReport::query()->create(['project_id' => $other->id, 'period_no' => 1, 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-14', 'created_by' => $this->leader->id]);

    expect(BiweeklyReport::query()->where('project_id', $this->project->id)->orderBy('period_no')->pluck('period_no')->all())->toBe([1, 2])
        ->and(BiweeklyReport::query()->where('project_id', $this->project->id)->first()->status)->toBe(ReportStatus::Draft)
        ->and(BiweeklyReport::query()->first()->attendees()->count())->toBe(2);
});

it('validates the period, the meeting fields and the attendees', function () {
    $store = fn (array $o) => $this->actingAs($this->leader)->post(route('projects.biweekly.store', $this->project), reportPayload($this->attendees, $o));

    $store(['ends_on' => '2026-10-01'])->assertSessionHasErrors('ends_on');
    $store(['starts_on' => ''])->assertSessionHasErrors('starts_on');
    $store(['attendee_ids' => [User::factory()->create()->id]])->assertSessionHasErrors('attendee_ids.0');
    $store(['attendee_ids' => [$this->leader->id, $this->leader->id]])->assertSessionHasErrors('attendee_ids.0');

    expect(BiweeklyReport::query()->count())->toBe(0);
});

it('needs the meeting, agenda, summary, plan, actual and an attendee before it can be submitted', function () {
    $this->actingAs($this->leader)->post(route('projects.biweekly.store', $this->project), reportPayload($this->attendees));
    $report = BiweeklyReport::query()->first();

    $this->actingAs($this->leader)->post(route('projects.biweekly.submit', [$this->project, $report]), reportPayload([], ['meeting_date' => '', 'agenda' => '', 'summary' => '', 'planned' => '', 'actual' => '']))
        ->assertSessionHasErrors(['meeting_date', 'agenda', 'summary', 'planned', 'actual', 'attendee_ids']);

    expect($report->fresh()->status)->toBe(ReportStatus::Draft);
});

it('goes through submit, revision, resubmit and approval with audit and notices', function () {
    $mentor = bmMentor();
    ProjectMentor::query()->create(['project_id' => $this->project->id, 'mentor_id' => $mentor->id, 'assigned_by' => $this->owner->id, 'status' => 'ACTIVE', 'assigned_at' => now()]);
    $this->actingAs($this->leader)->post(route('projects.biweekly.store', $this->project), reportPayload($this->attendees));
    $report = BiweeklyReport::query()->first();

    $this->actingAs($this->leader)->post(route('projects.biweekly.submit', [$this->project, $report]), reportPayload($this->attendees))->assertSessionHasNoErrors();
    expect($report->fresh()->status)->toBe(ReportStatus::Submitted)
        ->and($this->owner->notifications()->count())->toBe(1)->and($mentor->notifications()->count())->toBe(1)
        ->and($this->leader->notifications()->count())->toBe(0);

    $this->actingAs($this->leader)->patch(route('projects.biweekly.update', [$this->project, $report]), reportPayload($this->attendees))->assertSessionHasErrors('report');

    $this->actingAs($this->owner)->post(route('projects.biweekly.revision', [$this->project, $report]), ['reason' => ''])->assertSessionHasErrors('reason');
    $this->actingAs($this->owner)->post(route('projects.biweekly.revision', [$this->project, $report]), ['reason' => 'Lengkapi kendala'])->assertSessionHasNoErrors();
    expect($report->fresh()->status)->toBe(ReportStatus::RevisionRequired)->and($report->fresh()->review_note)->toBe('Lengkapi kendala')
        ->and($this->leader->notifications()->count())->toBe(1);

    $this->actingAs($this->leader)->patch(route('projects.biweekly.update', [$this->project, $report]), reportPayload($this->attendees, ['issues' => 'Responden lambat']))->assertSessionHasNoErrors();
    $this->actingAs($this->leader)->post(route('projects.biweekly.submit', [$this->project, $report]), reportPayload($this->attendees, ['issues' => 'Responden lambat']));
    $this->actingAs($this->owner)->post(route('projects.biweekly.approve', [$this->project, $report]))->assertSessionHasNoErrors();

    expect($report->fresh()->status)->toBe(ReportStatus::Approved)->and($report->fresh()->reviewed_by)->toBe($this->owner->id)
        ->and(AuditLog::query()->where('event', 'like', 'biweekly.%')->orderBy('id')->pluck('event')->all())
        ->toBe(['biweekly.created', 'biweekly.submitted', 'biweekly.revision_requested', 'biweekly.updated', 'biweekly.submitted', 'biweekly.approved']);

    $this->actingAs($this->owner)->post(route('projects.biweekly.approve', [$this->project, $report]))->assertSessionHasErrors('report');
});

it('lets assigned mentors review bi-weekly reports from their approval inbox only', function () {
    $mentor = bmMentor();
    ProjectMentor::query()->create([
        'project_id' => $this->project->id,
        'mentor_id' => $mentor->id,
        'assigned_by' => $this->owner->id,
        'status' => 'ACTIVE',
        'assigned_at' => now(),
    ]);
    $report = BiweeklyReport::query()->create([
        'project_id' => $this->project->id,
        'period_no' => 1,
        'starts_on' => '2026-11-01',
        'ends_on' => '2026-11-14',
        'status' => ReportStatus::Submitted,
        'submitted_by' => $this->leader->id,
        'submitted_at' => now(),
        'created_by' => $this->leader->id,
    ]);
    $unassignedProject = Project::factory()->status(ProposalStatus::Approved)->create();
    $unassignedReport = BiweeklyReport::query()->create([
        'project_id' => $unassignedProject->id,
        'period_no' => 1,
        'starts_on' => '2026-11-01',
        'ends_on' => '2026-11-14',
        'status' => ReportStatus::Submitted,
        'submitted_by' => $this->leader->id,
        'submitted_at' => now(),
        'created_by' => $this->leader->id,
    ]);

    $this->actingAs($mentor)
        ->get(route('approval.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('approval/index')
            ->where('filters.tab', 'biweekly')
            ->where('availableTabs', ['biweekly'])
            ->where('counts.biweekly', 1)
            ->has('items.data', 1)
            ->where('items.data.0.url', route('projects.biweekly.index', $this->project)));

    $this->actingAs($mentor)
        ->get(route('projects.biweekly.index', $this->project))
        ->assertInertia(fn (Assert $page) => $page->where('reports.0.can_review', true));

    $this->actingAs($mentor)
        ->post(route('projects.biweekly.approve', [$this->project, $report]))
        ->assertSessionHasNoErrors();

    $revisionReport = BiweeklyReport::query()->create([
        'project_id' => $this->project->id,
        'period_no' => 2,
        'starts_on' => '2026-11-15',
        'ends_on' => '2026-11-28',
        'status' => ReportStatus::Submitted,
        'submitted_by' => $this->leader->id,
        'submitted_at' => now(),
        'created_by' => $this->leader->id,
    ]);

    $this->actingAs($mentor)
        ->post(route('projects.biweekly.revision', [$this->project, $revisionReport]), ['reason' => 'Lengkapi aktual periode ini.'])
        ->assertSessionHasNoErrors();

    $this->actingAs($mentor)
        ->post(route('projects.biweekly.approve', [$unassignedProject, $unassignedReport]))
        ->assertForbidden();

    expect($report->fresh()->status)->toBe(ReportStatus::Approved)
        ->and($report->fresh()->reviewed_by)->toBe($mentor->id)
        ->and($revisionReport->fresh()->status)->toBe(ReportStatus::RevisionRequired)
        ->and($revisionReport->fresh()->review_note)->toBe('Lengkapi aktual periode ini.')
        ->and(AuditLog::query()->where('event', 'biweekly.approved')->count())->toBe(1)
        ->and(AuditLog::query()->where('event', 'biweekly.revision_requested')->count())->toBe(1);
});

it('can accept reports without review while DEC-08 is open', function () {
    config()->set('qcc.biweekly.require_review', false);
    $this->actingAs($this->leader)->post(route('projects.biweekly.store', $this->project), reportPayload($this->attendees));
    $report = BiweeklyReport::query()->first();

    $this->actingAs($this->leader)->post(route('projects.biweekly.submit', [$this->project, $report]), reportPayload($this->attendees));

    expect($report->fresh()->status)->toBe(ReportStatus::Approved)->and($this->owner->notifications()->count())->toBe(0);
});

it('lets only drafts be deleted and keeps reviewers and strangers out of the writing', function () {
    $this->actingAs($this->leader)->post(route('projects.biweekly.store', $this->project), reportPayload($this->attendees));
    $report = BiweeklyReport::query()->first();

    $this->actingAs($this->owner)->post(route('projects.biweekly.store', $this->project), reportPayload($this->attendees))->assertForbidden();
    $this->actingAs($this->leader)->post(route('projects.biweekly.approve', [$this->project, $report]))->assertForbidden();

    $stranger = bmPerson('Team Leader', ['projects.view', 'projects.update']);
    $this->actingAs($stranger)->get(route('projects.biweekly.index', $this->project))->assertForbidden();

    $this->actingAs($this->leader)->post(route('projects.biweekly.submit', [$this->project, $report]), reportPayload($this->attendees));
    $this->actingAs($this->leader)->delete(route('projects.biweekly.destroy', [$this->project, $report]))->assertSessionHasErrors('report');

    $draft = BiweeklyReport::query()->create(['project_id' => $this->project->id, 'period_no' => 9, 'starts_on' => '2026-12-01', 'ends_on' => '2026-12-14', 'created_by' => $this->leader->id]);
    $this->actingAs($this->leader)->delete(route('projects.biweekly.destroy', [$this->project, $draft]))->assertSessionHasNoErrors();

    expect(BiweeklyReport::query()->find($draft->id))->toBeNull();
});

it('lists the reports with what the viewer may do', function () {
    $this->actingAs($this->leader)->post(route('projects.biweekly.store', $this->project), reportPayload($this->attendees));
    $report = BiweeklyReport::query()->first();
    $this->actingAs($this->leader)->post(route('projects.biweekly.submit', [$this->project, $report]), reportPayload($this->attendees));

    $this->actingAs($this->owner)->get(route('projects.biweekly.index', $this->project))->assertInertia(fn (Assert $page) => $page
        ->component('projects/biweekly')->has('reports', 1)->where('reports.0.can_review', true)->where('reports.0.can_edit', false)
        ->where('next_period', 2)->where('can_create', false)->has('members', 2));
    $this->actingAs($this->leader)->get(route('projects.biweekly.index', $this->project))->assertInertia(fn (Assert $page) => $page
        ->where('reports.0.can_review', false)->where('can_create', true));
});

it('shows linked bi-weekly evidence only to viewers with evidence access', function () {
    $report = BiweeklyReport::query()->create([
        'project_id' => $this->project->id,
        'period_no' => 1,
        'starts_on' => '2026-11-01',
        'ends_on' => '2026-11-14',
        'created_by' => $this->leader->id,
    ]);
    $evidence = ProjectEvidence::query()->create([
        'project_id' => $this->project->id,
        'uploaded_by' => $this->leader->id,
        'title' => 'Grafik hasil',
        'category' => 'QCC_STEP',
        'storage_disk' => 'evidence_private',
        'storage_key' => 'private/grafik.pdf',
        'original_name' => 'grafik.pdf',
        'file_extension' => 'pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 100,
        'checksum_sha256' => str_repeat('a', 64),
        'version_group' => (string) Str::uuid(),
        'scan_status' => 'PASSED',
    ]);
    EvidenceLink::query()->create([
        'evidence_id' => $evidence->id,
        'target_type' => BiweeklyReport::class,
        'target_id' => $report->id,
        'created_by' => $this->leader->id,
    ]);
    $this->leader->givePermissionTo(Permission::findOrCreate('evidence.view', 'web'));

    $this->actingAs($this->leader)->get(route('projects.biweekly.index', $this->project))
        ->assertInertia(fn (Assert $page) => $page
            ->where('reports.0.evidence.0.title', 'Grafik hasil')
            ->where('reports.0.evidence.0.original_name', 'grafik.pdf')
            ->where('reports.0.evidence.0.download_url', route('projects.evidence.download', [$this->project, $evidence])));

    $this->actingAs($this->member)->get(route('projects.biweekly.index', $this->project))
        ->assertInertia(fn (Assert $page) => $page->where('reports.0.evidence', []));
});

it('stores corrective actions against the bi-weekly report and validates project PICs', function () {
    $this->actingAs($this->leader)->post(
        route('projects.biweekly.store', $this->project),
        reportPayload($this->attendees, ['actions' => [[
            'title' => 'Periksa ulang sampel',
            'description' => 'Gunakan ukuran sampel baru',
            'pic_id' => $this->member->id,
            'due_date' => '2026-11-20',
        ]]]),
    )->assertSessionHasNoErrors();

    $report = BiweeklyReport::query()->firstOrFail();
    $action = $report->actions()->firstOrFail();
    expect($action->project_id)->toBe($this->project->id)
        ->and($action->source_type)->toBe('BIWEEKLY_REPORT')
        ->and($action->source_id)->toBe($report->id)
        ->and($action->pic_id)->toBe($this->member->id)
        ->and($this->member->notifications()->count())->toBe(1);

    $this->actingAs($this->leader)->patch(
        route('projects.biweekly.update', [$this->project, $report]),
        reportPayload($this->attendees, ['actions' => [[
            'id' => $action->id,
            'title' => 'Periksa sampel ulang',
            'description' => 'Pakai ukuran sampel terbaru',
            'pic_id' => $this->member->id,
            'due_date' => '2026-11-21',
        ]]]),
    )->assertSessionHasNoErrors();

    expect($action->fresh()->title)->toBe('Periksa sampel ulang')
        ->and($action->fresh()->due_date->toDateString())->toBe('2026-11-21');

    $outsider = User::factory()->create();
    $this->actingAs($this->leader)->patch(
        route('projects.biweekly.update', [$this->project, $report]),
        reportPayload($this->attendees, ['actions' => [[
            'title' => 'PIC tidak valid',
            'pic_id' => $outsider->id,
            'due_date' => '2026-11-22',
        ]]]),
    )->assertSessionHasErrors('actions.0.pic_id');

    expect($report->actions()->count())->toBe(1);
});

it('lets the Owner QCC assign a mentor, once, and tells the mentor and the leader', function () {
    $mentor = bmMentor();

    $this->actingAs($this->owner)->post(route('projects.mentoring.assign', $this->project), ['mentor_id' => $mentor->id])->assertSessionHasNoErrors();
    $this->actingAs($this->owner)->post(route('projects.mentoring.assign', $this->project), ['mentor_id' => $mentor->id])->assertSessionHasErrors('mentor_id');
    $this->actingAs($this->owner)->post(route('projects.mentoring.assign', $this->project), ['mentor_id' => $this->leader->id])->assertSessionHasErrors('mentor_id');

    expect(ProjectMentor::query()->where('project_id', $this->project->id)->count())->toBe(1)
        ->and($mentor->notifications()->count())->toBe(1)->and($this->leader->notifications()->count())->toBe(1)
        ->and(AuditLog::query()->where('event', 'mentor.assigned')->count())->toBe(1);

    $this->actingAs($this->leader)->post(route('projects.mentoring.assign', $this->project), ['mentor_id' => bmMentor()->id])->assertForbidden();
});

it('shows a mentor only the projects they are assigned to', function () {
    $mentor = bmMentor();
    $other = Project::factory()->status(ProposalStatus::Approved)->create();

    $this->actingAs($mentor)->get(route('projects.mentoring.index', $this->project))->assertForbidden();

    ProjectMentor::query()->create(['project_id' => $this->project->id, 'mentor_id' => $mentor->id, 'assigned_by' => $this->owner->id, 'status' => 'ACTIVE', 'assigned_at' => now()]);

    $this->actingAs($mentor)->get(route('projects.mentoring.index', $this->project))->assertOk();
    $this->actingAs($mentor)->get(route('projects.mentoring.index', $other))->assertForbidden();
    expect(Project::query()->visibleTo($mentor)->pluck('id')->all())->toBe([$this->project->id]);

    $this->actingAs($this->owner)->delete(route('projects.mentoring.assignments.end', [$this->project, ProjectMentor::query()->first()]))->assertSessionHasNoErrors();
    $this->actingAs($mentor)->get(route('projects.mentoring.index', $this->project))->assertForbidden();
});

it('walks a mentoring session from draft to the team response', function () {
    $mentor = bmMentor();
    ProjectMentor::query()->create(['project_id' => $this->project->id, 'mentor_id' => $mentor->id, 'assigned_by' => $this->owner->id, 'status' => 'ACTIVE', 'assigned_at' => now()]);
    $payload = ['session_at' => '2026-11-20T10:00', 'topic' => 'Analisis akar masalah', 'findings' => 'Data belum lengkap', 'suggestions' => 'Tambah sampel', 'next_steps' => ''];

    $this->actingAs($mentor)->post(route('projects.mentoring.store', $this->project), $payload)->assertSessionHasNoErrors();
    $report = MentoringReport::query()->first();
    expect($report->status)->toBe(ReportStatus::Draft);

    $this->actingAs($mentor)->post(route('projects.mentoring.submit', [$this->project, $report]), [...$payload, 'findings' => ''])->assertSessionHasErrors('findings');
    $this->actingAs($mentor)->post(route('projects.mentoring.submit', [$this->project, $report]), $payload)->assertSessionHasNoErrors();
    expect($report->fresh()->status)->toBe(ReportStatus::Submitted)
        ->and($this->leader->notifications()->count())->toBe(1)->and($this->member->notifications()->count())->toBe(1)->and($mentor->notifications()->count())->toBe(0);

    $this->actingAs($mentor)->patch(route('projects.mentoring.update', [$this->project, $report]), $payload)->assertSessionHasErrors('report');

    $this->actingAs($this->leader)->post(route('projects.mentoring.acknowledge', [$this->project, $report]), ['team_response' => ''])->assertSessionHasErrors('team_response');
    $this->actingAs($this->leader)->post(route('projects.mentoring.acknowledge', [$this->project, $report]), ['team_response' => 'Sampel akan ditambah'])->assertSessionHasNoErrors();

    expect($report->fresh())->status->toBe(ReportStatus::Acknowledged)->team_response->toBe('Sampel akan ditambah')
        ->and($mentor->notifications()->count())->toBe(1)
        ->and(AuditLog::query()->where('event', 'like', 'mentoring.%')->orderBy('id')->pluck('event')->all())->toBe(['mentoring.created', 'mentoring.submitted', 'mentoring.acknowledged']);
});

it('stores mentoring action items and exposes linked evidence to authorized project viewers', function () {
    $mentor = bmMentor();
    ProjectMentor::query()->create([
        'project_id' => $this->project->id,
        'mentor_id' => $mentor->id,
        'assigned_by' => $this->owner->id,
        'status' => 'ACTIVE',
        'assigned_at' => now(),
    ]);
    $mentor->givePermissionTo(Permission::findOrCreate('evidence.view', 'web'));

    $this->actingAs($mentor)->post(route('projects.mentoring.store', $this->project), [
        'session_at' => '2026-11-20T10:00',
        'topic' => 'Analisis akar masalah',
        'findings' => 'Data belum lengkap',
        'suggestions' => 'Tambah sampel',
        'next_steps' => 'Tinjau hasil minggu depan',
        'actions' => [[
            'title' => 'Tambah sampel produksi',
            'description' => 'Ambil dari tiga shift',
            'pic_id' => $this->member->id,
            'due_date' => '2026-11-25',
        ]],
    ])->assertSessionHasNoErrors();

    $report = MentoringReport::query()->firstOrFail();
    $action = $report->actions()->firstOrFail();
    $evidence = ProjectEvidence::query()->create([
        'project_id' => $this->project->id,
        'uploaded_by' => $this->leader->id,
        'title' => 'Data sampel',
        'category' => 'QCC_STEP',
        'storage_disk' => 'evidence_private',
        'storage_key' => 'private/sampel.pdf',
        'original_name' => 'sampel.pdf',
        'file_extension' => 'pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 100,
        'checksum_sha256' => str_repeat('b', 64),
        'version_group' => (string) Str::uuid(),
        'scan_status' => 'PASSED',
    ]);
    EvidenceLink::query()->create([
        'evidence_id' => $evidence->id,
        'target_type' => MentoringReport::class,
        'target_id' => $report->id,
        'created_by' => $this->leader->id,
    ]);

    expect($action->source_type)->toBe('MENTORING_REPORT')
        ->and($action->source_id)->toBe($report->id)
        ->and($action->pic_id)->toBe($this->member->id)
        ->and($this->member->notifications()->count())->toBe(1);

    $this->actingAs($mentor)->get(route('projects.mentoring.index', $this->project))
        ->assertInertia(fn (Assert $page) => $page
            ->where('reports.0.actions.0.title', 'Tambah sampel produksi')
            ->where('reports.0.evidence.0.title', 'Data sampel')
            ->where('reports.0.evidence.0.download_url', route('projects.evidence.download', [$this->project, $evidence])));
});

it('keeps mentoring scoped to the assigned mentor and does not add mentoring approval', function () {
    $mentor = bmMentor();
    $unassigned = bmMentor();
    ProjectMentor::query()->create(['project_id' => $this->project->id, 'mentor_id' => $mentor->id, 'assigned_by' => $this->owner->id, 'status' => 'ACTIVE', 'assigned_at' => now()]);
    $payload = ['session_at' => '2026-11-20T10:00', 'topic' => 'Topik', 'findings' => 'a', 'suggestions' => 'b'];

    $this->actingAs($unassigned)->post(route('projects.mentoring.store', $this->project), $payload)->assertForbidden();
    $this->actingAs($this->leader)->post(route('projects.mentoring.store', $this->project), $payload)->assertForbidden();
    $this->actingAs($this->owner)->post(route('projects.mentoring.store', $this->project), $payload)->assertForbidden();
    $this->actingAs($mentor)->post(route('projects.mentoring.store', $this->project), [...$payload, 'topic' => ''])->assertSessionHasErrors('topic');

    $report = MentoringReport::query()->create(['project_id' => $this->project->id, 'project_mentor_id' => ProjectMentor::query()->first()->id, 'session_at' => now(), 'topic' => 'x', 'status' => ReportStatus::Submitted, 'created_by' => $mentor->id]);
    $this->actingAs($mentor)->post(route('projects.mentoring.acknowledge', [$this->project, $report]), ['team_response' => 'ok'])->assertForbidden();
});

it('lets Owner assign an active Mentor to a project and records the assignment', function () {
    $mentor = bmMentor();

    $this->actingAs($this->owner)
        ->post(route('projects.mentoring.assign', $this->project), ['mentor_id' => $mentor->id])
        ->assertRedirect(route('projects.mentoring.index', $this->project));

    $assignment = ProjectMentor::query()->sole();

    expect($assignment->mentor_id)->toBe($mentor->id)
        ->and($assignment->assigned_by)->toBe($this->owner->id)
        ->and($assignment->status)->toBe('ACTIVE');
    $this->assertDatabaseHas('audit_logs', [
        'event' => 'mentor.assigned',
        'actor_id' => $this->owner->id,
        'subject_id' => $assignment->id,
    ]);
});

it('hands the mentoring page its lists and what the viewer may do', function () {
    $mentor = bmMentor();
    ProjectMentor::query()->create(['project_id' => $this->project->id, 'mentor_id' => $mentor->id, 'assigned_by' => $this->owner->id, 'status' => 'ACTIVE', 'assigned_at' => now()]);
    $spare = bmMentor();

    $this->actingAs($this->owner)->get(route('projects.mentoring.index', $this->project))->assertInertia(fn (Assert $page) => $page
        ->component('projects/mentoring')->has('assignments', 1)->where('can_assign', true)->where('can_write', false)
        ->has('mentor_options', 1)->where('mentor_options.0.value', (string) $spare->id));
    $this->actingAs($mentor)->get(route('projects.mentoring.index', $this->project))->assertInertia(fn (Assert $page) => $page
        ->where('can_write', true)->where('can_assign', false)->has('mentor_options', 0));
});
