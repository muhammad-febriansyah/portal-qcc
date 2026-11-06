<?php

use App\Actions\Proposals\InitializeProjectSteps;
use App\Enums\ProposalStatus;
use App\Enums\StepStatus;
use App\Models\ActionItem;
use App\Models\BiweeklyReport;
use App\Models\MemberCompetency;
use App\Models\MentoringReport;
use App\Models\Project;
use App\Models\ProjectMentor;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function memberDashboardUser(string $role = 'SIMer / Member'): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate($role, 'web'));
    $user->givePermissionTo([
        Permission::findOrCreate('projects.view', 'web'),
        Permission::findOrCreate('projects.create', 'web'),
        Permission::findOrCreate('projects.update', 'web'),
    ]);

    return $user;
}

function memberDashboardProject(User $member, string $roleInProject, string $title = 'Proyek tim saya'): Project
{
    $project = Project::factory()->status(ProposalStatus::Approved)->create(['title' => $title, 'approved_at' => now()]);
    app(InitializeProjectSteps::class)->handle($project);
    $project->members()->attach($member->id, ['role_in_project' => $roleInProject, 'status' => 'ACTIVE', 'joined_at' => now()]);

    return $project;
}

test('a new member starts at the competencies step and is sent to fill them in', function () {
    $this->actingAs(memberDashboardUser())->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('member-dashboard')
            ->where('overview.journey.steps.0.state', 'current')
            ->where('overview.journey.steps.1.state', 'todo')
            ->where('overview.journey.next_action.label', 'Isi kompetensi')
            ->where('overview.journey.next_action.href', route('my-competencies.index'))
            ->where('overview.competencies.count', 0)
            ->where('overview.competencies.limit', 10)
            ->has('overview.projects', 0));
});

test('the next action follows the member flow from proposal to proposal selection', function () {
    $member = memberDashboardUser();
    MemberCompetency::factory()->create(['user_id' => $member->id]);

    $this->actingAs($member)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.journey.steps.0.state', 'done')
            ->where('overview.journey.steps.1.state', 'current')
            ->where('overview.journey.next_action.label', 'Buat proposal QCC'));

    $proposal = Project::factory()->create(['proposer_id' => $member->id, 'title' => 'Ide perbaikan']);

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.journey.next_action.label', 'Lanjutkan draft proposal')
            ->where('overview.journey.next_action.href', route('proposals.edit', $proposal))
            ->where('overview.proposals.0.action_label', 'Lanjutkan'));

    $proposal->forceFill(['proposal_status' => ProposalStatus::Submitted])->save();

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.journey.steps.1.state', 'done')
            ->where('overview.journey.steps.2.state', 'current')
            ->where('overview.journey.steps.2.label', 'Seleksi Proposal')
            ->where('overview.journey.steps.3.label', 'Proposal Lolos/Tidak Lolos')
            ->where('overview.journey.steps.3.state', 'todo')
            ->where('overview.journey.next_action.label', 'Lihat status proposal')
            ->where('overview.proposals.0.status_label', 'Diajukan')
            ->where('overview.proposals.0.action_label', 'Lihat'));

    $proposal->forceFill(['proposal_status' => ProposalStatus::RevisionRequired])->save();

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.journey.next_action.label', 'Perbaiki revisi proposal')
            ->where('overview.tasks.0.title', 'Perbaiki revisi proposal')
            ->where('overview.tasks.0.href', route('proposals.edit', $proposal)));
});

test('the last journey step tells whether the proposal passed selection', function () {
    $member = memberDashboardUser();
    MemberCompetency::factory()->create(['user_id' => $member->id]);
    $proposal = Project::factory()->status(ProposalStatus::Rejected)->create(['proposer_id' => $member->id]);

    $this->actingAs($member)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.journey.steps.2.state', 'done')
            ->where('overview.journey.steps.3.label', 'Proposal Tidak Lolos')
            ->where('overview.journey.steps.3.state', 'rejected'));

    $proposal->forceFill(['proposal_status' => ProposalStatus::Approved])->save();

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.journey.steps.3.label', 'Proposal Lolos')
            ->where('overview.journey.steps.3.state', 'done'));
});

test('a team member sees the current step of their project and only their own action items', function () {
    $member = memberDashboardUser();
    MemberCompetency::factory()->create(['user_id' => $member->id]);
    $project = memberDashboardProject($member, 'MEMBER');
    $project->steps()->where('step_number', 1)->update(['status' => StepStatus::Approved->value]);
    $project->steps()->where('step_number', 2)->update(['status' => StepStatus::InProgress->value, 'progress_pct' => 40, 'target_date' => now()->addDays(3)->toDateString()]);
    $someoneElse = User::factory()->create();
    foreach ([[$member, 'Kumpulkan data'], [$someoneElse, 'Bukan tugas saya']] as [$pic, $title]) {
        ActionItem::query()->create(['project_id' => $project->id, 'created_by' => $someoneElse->id, 'pic_id' => $pic->id, 'title' => $title, 'description' => '', 'status' => 'OPEN', 'due_date' => now()->addDay()->toDateString()]);
    }

    $this->actingAs($member)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('overview.journey.steps.3.state', 'done')
            ->where('overview.journey.steps.3.label', 'Proposal Lolos')
            ->where('overview.journey.next_action.label', 'Buka proyek')
            ->where('overview.projects.0.role', 'Anggota')
            ->where('overview.projects.0.steps_completed', 1)
            ->where('overview.projects.0.current_step.number', 2)
            ->where('overview.projects.0.current_step.progress', 40)
            // A member gets no leader duty for the step that is due soon.
            ->where('overview.task_count', 1)
            ->where('overview.tasks.0.title', 'Kumpulkan data'));
});

test('a project leader is reminded of the step due, late reports and mentoring to answer', function () {
    // An ordinary SIMer account who leads this project still gets the leader duties.
    $leader = memberDashboardUser();
    MemberCompetency::factory()->create(['user_id' => $leader->id]);
    $project = memberDashboardProject($leader, 'LEADER', 'Proyek yang saya pimpin');
    $project->steps()->where('step_number', 1)->update(['status' => StepStatus::InProgress->value, 'target_date' => now()->addDays(2)->toDateString()]);
    BiweeklyReport::query()->create(['project_id' => $project->id, 'period_no' => 1, 'starts_on' => now()->subWeeks(2), 'ends_on' => now()->subDay(), 'status' => 'DRAFT', 'created_by' => $leader->id]);
    $mentor = User::factory()->create();
    $assignment = ProjectMentor::query()->create(['project_id' => $project->id, 'mentor_id' => $mentor->id, 'assigned_by' => $mentor->id, 'status' => 'ACTIVE', 'assigned_at' => now()]);
    MentoringReport::query()->create(['project_id' => $project->id, 'project_mentor_id' => $assignment->id, 'session_at' => now(), 'topic' => 'Sesi Pareto', 'status' => 'SUBMITTED', 'submitted_at' => now(), 'created_by' => $mentor->id]);

    $this->actingAs($leader)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('member-dashboard')
            ->where('overview.projects.0.role', 'Leader')
            ->where('overview.task_count', 3)
            ->where('overview.tasks', fn ($tasks) => collect($tasks)->pluck('title')->sort()->values()->all() === [
                'Ajukan Step 1', 'Kirim laporan bi-weekly ke-1', 'Tanggapi catatan mentoring',
            ])
            ->where('overview.tasks', fn ($tasks) => collect($tasks)->firstWhere('title', 'Kirim laporan bi-weekly ke-1')['overdue'] === true));
});

test('reviewers and mentors keep the monitoring dashboard even with a member role', function (string $otherRole) {
    $user = memberDashboardUser();
    $user->assignRole(Role::findOrCreate($otherRole, 'web'));

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('roleOverview'));
})->with(['Owner QCC', 'Mentor']);
