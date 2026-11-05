<?php

use App\Actions\Proposals\InitializeProjectSteps;
use App\Enums\ProposalStatus;
use App\Enums\StepStatus;
use App\Models\BiweeklyReport;
use App\Models\MentoringReport;
use App\Models\Project;
use App\Models\ProjectMentor;
use App\Models\QccBatch;
use App\Models\TeamMatchingRun;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function roleDashboardUser(string $role, string ...$permissions): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate($role, 'web'));
    $user->givePermissionTo(array_map(fn (string $permission) => Permission::findOrCreate($permission, 'web'), $permissions));

    return $user;
}

function roleDashboardProject(User $proposer, string $title, ProposalStatus $status = ProposalStatus::Approved): Project
{
    return Project::factory()->create([
        'batch_id' => QccBatch::factory()->create()->id,
        'proposer_id' => $proposer->id,
        'title' => $title,
        'proposal_status' => $status,
    ]);
}

test('team members get the member dashboard with only the projects they belong to', function (string $role, string $projectRole, string $roleLabel) {
    $user = roleDashboardUser($role, 'projects.view');
    $visible = roleDashboardProject(User::factory()->create(), 'Proyek yang boleh dilihat');
    $hidden = roleDashboardProject(User::factory()->create(), 'Proyek milik tim lain');
    $visible->members()->attach($user->id, [
        'role_in_project' => $projectRole,
        'status' => 'ACTIVE',
        'joined_at' => now(),
    ]);
    $hidden->members()->attach(User::factory()->create()->id, [
        'role_in_project' => $projectRole,
        'status' => 'ACTIVE',
        'joined_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member-dashboard')
            ->missing('roleOverview')
            ->has('overview.projects', 1)
            ->where('overview.projects.0.title', 'Proyek yang boleh dilihat')
            ->where('overview.projects.0.role', $roleLabel));
})->with([
    'Team Leader sees leader assignments' => ['Team Leader', 'LEADER', 'Leader'],
    'SIMer member sees member assignments' => ['SIMer / Member', 'MEMBER', 'Anggota'],
]);

test('mentor dashboard scopes projects to active mentor assignments', function () {
    $mentor = roleDashboardUser('Mentor', 'projects.view');
    $visible = roleDashboardProject(User::factory()->create(), 'Proyek mentoring aktif');
    $hidden = roleDashboardProject(User::factory()->create(), 'Proyek tanpa penugasan');
    ProjectMentor::query()->create([
        'project_id' => $visible->id,
        'mentor_id' => $mentor->id,
        'assigned_by' => User::factory()->create()->id,
        'status' => 'ACTIVE',
        'assigned_at' => now(),
    ]);

    $this->actingAs($mentor)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('roleOverview.summary.active_projects', 1)
            ->has('roleOverview.projects', 1)
            ->where('roleOverview.projects.0.title', 'Proyek mentoring aktif'));
});

test('reviewer dashboard shows submitted proposals as pending tasks', function () {
    $reviewer = roleDashboardUser('Owner QCC', 'projects.view', 'projects.viewAny', 'projects.review', 'projects.approve');
    roleDashboardProject(User::factory()->create(), 'Menunggu review', ProposalStatus::Submitted)
        ->forceFill(['submitted_at' => now()])->save();
    roleDashboardProject(User::factory()->create(), 'Masih draf', ProposalStatus::Draft);
    $approved = roleDashboardProject(User::factory()->create(), 'Step menunggu review');
    app(InitializeProjectSteps::class)->handle($approved);
    $approved->steps()->firstOrFail()->forceFill([
        'status' => StepStatus::Submitted,
        'submitted_at' => now(),
    ])->save();

    $this->actingAs($reviewer)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            // The submitted proposal also waits for its team.
            ->where('roleOverview.summary.pending_tasks', 3)
            ->where('roleOverview.tasks', fn ($tasks) => collect($tasks)->pluck('title')->sort()->values()->all()
                === ['Proposal menunggu penyusunan tim', 'Review Step 1', 'Review proposal']));
});

test('owner dashboard shows proposals waiting for team composition until team is confirmed', function () {
    $owner = roleDashboardUser('Owner QCC', 'projects.view', 'projects.viewAny', 'projects.review');
    $member = roleDashboardUser('SIMer / Member', 'projects.view', 'projects.create', 'projects.update');
    $project = roleDashboardProject($member, 'Menunggu penyusunan tim', ProposalStatus::Submitted);
    roleDashboardProject($member, 'Masih draf', ProposalStatus::Draft);

    $teamTask = fn (array $tasks): ?array => collect($tasks)->firstWhere('title', 'Proposal menunggu penyusunan tim');

    // A submitted proposal is both waiting for review and for its team; the draft is neither.
    $this->actingAs($owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('roleOverview.summary.pending_tasks', 2)
            ->where('roleOverview.tasks', fn ($tasks) => $teamTask(collect($tasks)->toArray()) !== null
                && $teamTask(collect($tasks)->toArray())['project'] === 'Menunggu penyusunan tim'
                && $teamTask(collect($tasks)->toArray())['href'] === route('proposals.candidates', $project)
                && $teamTask(collect($tasks)->toArray())['detail'] === 'Susun dan konfirmasi tim sebelum proposal disetujui.'));

    $run = TeamMatchingRun::query()->create([
        'project_id' => $project->id,
        'requested_by' => $owner->id,
        'status' => 'READY',
        'idempotency_key' => (string) str()->uuid(),
    ]);
    $run->forceFill(['confirmed_by' => $owner->id, 'confirmed_at' => now()])->save();

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('roleOverview.summary.pending_tasks', 1)
            ->where('roleOverview.tasks', fn ($tasks) => $teamTask(collect($tasks)->toArray()) === null));
});

test('role dashboard summarizes submitted report punctuality and mentoring feedback', function () {
    // Mentors keep the monitoring dashboard and see projects they belong to.
    $owner = roleDashboardUser('Mentor', 'projects.view');
    $visible = roleDashboardProject(User::factory()->create(), 'Proyek terlihat');
    $hidden = roleDashboardProject(User::factory()->create(), 'Proyek tersembunyi');
    $visible->members()->attach($owner->id, ['role_in_project' => 'LEADER', 'status' => 'ACTIVE', 'joined_at' => now()]);
    $assignment = ProjectMentor::query()->create([
        'project_id' => $visible->id,
        'mentor_id' => User::factory()->create()->id,
        'assigned_by' => $owner->id,
        'status' => 'ACTIVE',
        'assigned_at' => now(),
    ]);

    foreach ([['2026-10-21 16:00:00', $visible], ['2026-10-22 09:00:00', $visible], ['2026-10-23 09:00:00', $hidden]] as [$submittedAt, $project]) {
        $report = BiweeklyReport::query()->create([
            'project_id' => $project->id,
            'period_no' => BiweeklyReport::query()->where('project_id', $project->id)->count() + 1,
            'starts_on' => '2026-10-08',
            'ends_on' => '2026-10-21',
            'status' => 'SUBMITTED',
            'created_by' => $owner->id,
        ]);
        $report->forceFill(['submitted_at' => $submittedAt])->save();
    }

    foreach ([$visible, $hidden] as $project) {
        BiweeklyReport::query()->create([
            'project_id' => $project->id,
            'period_no' => BiweeklyReport::query()->where('project_id', $project->id)->count() + 1,
            'starts_on' => today()->subWeeks(2),
            'ends_on' => today()->subDay(),
            'status' => 'DRAFT',
            'created_by' => $owner->id,
        ]);
    }

    MentoringReport::query()->create([
        'project_id' => $visible->id,
        'project_mentor_id' => $assignment->id,
        'session_at' => now(),
        'topic' => 'Sesi 1',
        'status' => 'SUBMITTED',
        'submitted_at' => now(),
        'created_by' => $assignment->mentor_id,
    ]);
    MentoringReport::query()->create([
        'project_id' => $visible->id,
        'project_mentor_id' => $assignment->id,
        'session_at' => now()->subDay(),
        'topic' => 'Sesi 2',
        'status' => 'ACKNOWLEDGED',
        'submitted_at' => now()->subDay(),
        'acknowledged_at' => now(),
        'created_by' => $assignment->mentor_id,
    ]);

    $this->actingAs($owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('roleOverview.summary.biweekly_on_time', 1)
            ->where('roleOverview.summary.biweekly_late', 1)
            ->where('roleOverview.summary.biweekly_overdue', 1)
            ->where('roleOverview.status_counts.4.count', 1)
            ->where('roleOverview.summary.mentoring_sessions', 2)
            ->where('roleOverview.summary.mentoring_feedback_pending', 1));
});

test('dashboard batch filter scopes every QCC count and keeps hidden batches out of options', function () {
    $user = roleDashboardUser('Mentor', 'projects.view');
    $first = roleDashboardProject(User::factory()->create(), 'Batch terlihat A');
    $second = roleDashboardProject(User::factory()->create(), 'Batch terlihat B', ProposalStatus::Submitted);
    roleDashboardProject(User::factory()->create(), 'Batch tersembunyi');

    foreach ([$first, $second] as $project) {
        $project->members()->attach($user->id, ['role_in_project' => 'LEADER', 'status' => 'ACTIVE', 'joined_at' => now()]);
    }

    $this->actingAs($user)->get(route('dashboard', ['batch_id' => $first->batch_id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedBatchId', $first->batch_id)
            ->has('batches', 2)
            ->where('roleOverview.summary.active_projects', 1)
            ->where('roleOverview.status_counts.4.count', 1)
            ->where('roleOverview.status_counts.1.count', 0)
            ->has('roleOverview.projects', 1)
            ->where('roleOverview.projects.0.title', 'Batch terlihat A'));
});

test('dashboard period filters projects by creation date and reports by reporting period', function () {
    $today = today()->setTime(12, 0);
    $this->travelTo($today);

    $leader = roleDashboardUser('Mentor', 'projects.view');
    $recentProject = roleDashboardProject(User::factory()->create(), 'Proyek baru');
    $olderProject = roleDashboardProject(User::factory()->create(), 'Proyek lama');
    $recentProject->forceFill(['created_at' => $today->copy()->subDays(10)])->save();
    $olderProject->forceFill(['created_at' => $today->copy()->subDays(60)])->save();

    foreach ([$recentProject, $olderProject] as $project) {
        $project->members()->attach($leader->id, ['role_in_project' => 'LEADER', 'status' => 'ACTIVE', 'joined_at' => now()]);
    }

    foreach ([[$recentProject, $today->copy()->subDays(5)], [$olderProject, $today->copy()->subDays(5)], [$olderProject, $today->copy()->subDays(60)]] as [$project, $periodEnd]) {
        $report = BiweeklyReport::query()->create([
            'project_id' => $project->id,
            'period_no' => BiweeklyReport::query()->where('project_id', $project->id)->count() + 1,
            'starts_on' => $periodEnd->copy()->subDays(13)->toDateString(),
            'ends_on' => $periodEnd->toDateString(),
            'status' => 'SUBMITTED',
            'created_by' => $leader->id,
        ]);
        $report->forceFill(['submitted_at' => $periodEnd->copy()->addDay()])->save();
    }

    $mentor = User::factory()->create();
    $assignment = ProjectMentor::query()->create([
        'project_id' => $recentProject->id,
        'mentor_id' => $mentor->id,
        'assigned_by' => $leader->id,
        'status' => 'ACTIVE',
        'assigned_at' => now(),
    ]);
    MentoringReport::query()->create([
        'project_id' => $recentProject->id,
        'project_mentor_id' => $assignment->id,
        'session_at' => $today->copy()->subDays(4),
        'topic' => 'Sesi periode ini',
        'status' => 'SUBMITTED',
        'submitted_at' => now(),
        'created_by' => $mentor->id,
    ]);

    $this->actingAs($leader)->get(route('dashboard', ['period' => '30']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedPeriod', '30')
            ->where('roleOverview.summary.active_projects', 1)
            ->where('roleOverview.status_counts.4.count', 1)
            ->has('roleOverview.projects', 1)
            ->where('roleOverview.projects.0.title', 'Proyek baru')
            ->where('roleOverview.summary.biweekly_late', 2)
            ->where('roleOverview.summary.mentoring_sessions', 1));
});

test('Super Admin sees QCC project metrics alongside system overview', function () {
    $admin = roleDashboardUser('Super Admin', 'dashboard.admin', 'projects.view', 'projects.viewAny');
    roleDashboardProject(User::factory()->create(), 'Proyek QCC global');

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('adminOverview')
            ->where('roleOverview.summary.active_projects', 1)
            ->where('roleOverview.status_counts.4.count', 1));
});
