<?php

use App\Actions\Proposals\InitializeProjectSteps;
use App\Enums\ProposalStatus;
use App\Enums\StepStatus;
use App\Enums\ThemeCategory;
use App\Models\BiweeklyReport;
use App\Models\Competency;
use App\Models\MemberCompetency;
use App\Models\Organization;
use App\Models\Project;
use App\Models\QccBatch;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function programBatch(): QccBatch
{
    return QccBatch::factory()->active()->create([
        'name' => 'QCC Batch 4',
        'starts_on' => '2026-11-01',
        'ends_on' => '2027-07-31',
        'participant_target' => 4,
        'registration_starts_on' => '2026-11-09',
        'registration_ends_on' => '2026-11-30',
        'selection_starts_on' => '2026-12-01',
        'selection_ends_on' => '2026-12-30',
        'execution_starts_on' => '2027-01-04',
        'execution_ends_on' => '2027-07-31',
    ]);
}

function programParticipant(?Organization $organization = null, bool $completeProfile = true): User
{
    $user = User::factory()->create($completeProfile ? [
        'organization_id' => ($organization ?? Organization::factory()->create())->id,
        'position' => 'Staf',
        'work_location' => 'Jakarta',
        'work_contact' => '0211234567',
        'personal_contact' => '081234567890',
        'availability_status' => 'AVAILABLE',
    ] : []);
    $user->assignRole(Role::findOrCreate('SIMer / Member', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('projects.create', 'web'));

    return $user;
}

function programOwner(): User
{
    $owner = User::factory()->create();
    $owner->assignRole(Role::findOrCreate('Owner QCC', 'web'));

    return $owner;
}

test('a profile records the moment it first becomes complete', function () {
    Carbon::setTestNow('2026-11-10 09:00:00');
    $user = programParticipant(completeProfile: false);

    expect($user->fresh()->profile_completed_at)->toBeNull();

    $user->forceFill([
        'organization_id' => Organization::factory()->create()->id,
        'position' => 'Staf',
        'work_location' => 'Jakarta',
        'work_contact' => '0211234567',
        'personal_contact' => '081234567890',
        'availability_status' => 'AVAILABLE',
    ])->save();

    Carbon::setTestNow('2026-11-12 09:00:00');
    $user->forceFill(['position' => 'Supervisor'])->save();

    expect($user->fresh()->profile_completed_at->toDateTimeString())->toBe('2026-11-10 09:00:00');
});

test('owner QCC sees phase 1 figures while phases 2 and 3 stay locked', function () {
    Carbon::setTestNow('2026-11-20 10:00:00');
    $batch = programBatch();
    $finance = Organization::factory()->create(['name' => 'DIV FINANCE']);
    $security = Organization::factory()->create(['name' => 'LOB SECURITY']);
    $first = programParticipant($finance);
    $second = programParticipant($finance);
    programParticipant($security);
    programParticipant(completeProfile: false);
    $competency = Competency::factory()->create();
    MemberCompetency::factory()->create(['user_id' => $first->id, 'competency_id' => $competency->id]);
    MemberCompetency::factory()->create(['user_id' => $second->id, 'competency_id' => $competency->id]);

    foreach ([[$first, ThemeCategory::CostEfficiency], [$second, ThemeCategory::CostEfficiency]] as [$proposer, $category]) {
        Project::factory()->status(ProposalStatus::Submitted)->create([
            'batch_id' => $batch->id,
            'proposer_id' => $proposer->id,
            'theme_category' => $category,
            'submitted_at' => '2026-11-18 08:00:00',
        ]);
    }
    // A draft is not a submitted proposal yet.
    Project::factory()->create(['batch_id' => $batch->id, 'proposer_id' => $first->id]);

    $this->actingAs(programOwner())->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('program.batch.name', 'QCC Batch 4')
            ->where('program.active_phase', 'registration')
            ->where('program.phases.0.state', 'active')
            ->where('program.phases.1.state', 'upcoming')
            ->where('program.phases.2.state', 'upcoming')
            ->where('program.registration.participant_target', 4)
            ->where('program.registration.participants', 4)
            ->where('program.registration.registered', 3)
            ->where('program.registration.competency_completed', 2)
            ->where('program.registration.competency_completed_percent', 67)
            ->where('program.registration.proposals_submitted', 2)
            ->where('program.registration.submitters', 2)
            ->where('program.registration.submitters_percent', 50)
            ->where('program.registration.submissions_by_organization.0', ['name' => 'DIV FINANCE', 'count' => 2])
            ->where('program.registration.themes.2', ['key' => ThemeCategory::CostEfficiency->value, 'label' => 'Cost & Efficiency', 'count' => 2, 'percent' => 100])
            ->where('program.registration.trend.0.date', '2026-11-09')
            ->where('program.registration.trend.9', ['date' => '2026-11-18', 'registered' => 0, 'submitted' => 2])
            ->where('program.registration.trend.11', ['date' => '2026-11-20', 'registered' => 3, 'submitted' => 2])
            ->has('program.registration.trend', 12));
});

test('a phase opens on its start date and is done after its end date', function () {
    Carbon::setTestNow('2026-12-05 10:00:00');
    programBatch();

    $this->actingAs(programOwner())->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('program.active_phase', 'selection')
            ->where('program.phases.0.state', 'done')
            ->where('program.phases.1.state', 'active')
            ->where('program.phases.2.state', 'upcoming')
            // A finished phase stays readable, its trend stopping at the phase end.
            ->where('program.registration.trend.21.date', '2026-11-30')
            ->has('program.registration.trend', 22));
});

test('SIMers do not get the program dashboard', function () {
    Carbon::setTestNow('2027-02-01 10:00:00');
    programBatch();

    $this->actingAs(programParticipant())->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('member-dashboard')
            ->where('program', null));
});

test('mentors follow all three phases like owner QCC', function () {
    Carbon::setTestNow('2026-11-20 10:00:00');
    programBatch();
    $mentor = User::factory()->create();
    $mentor->assignRole(Role::findOrCreate('Mentor', 'web'));

    $this->actingAs($mentor)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('program.phases', 3)
            ->where('program.active_phase', 'registration')
            ->has('program.registration.trend'));
});

test('team leaders only see the execution phase, once it has opened', function () {
    Carbon::setTestNow('2026-12-10 10:00:00');
    programBatch();
    $leader = programParticipant();
    $leader->syncRoles([Role::findOrCreate('Team Leader', 'web')]);

    $this->actingAs($leader)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('member-dashboard')
            ->where('program', null));

    Carbon::setTestNow('2027-01-04 08:00:00');

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('program.phases', 1)
            ->where('program.phases.0.key', 'execution')
            ->where('program.phases.0.state', 'active')
            ->where('program.registration', null));
});

test('the program dashboard is empty when no batch is running', function () {
    QccBatch::factory()->closed()->create();

    $this->actingAs(programOwner())->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('program', null));
});

test('registration figures are readable before phase 1 officially opens', function () {
    Carbon::setTestNow('2026-11-06 10:00:00');
    programBatch();
    programParticipant();

    $this->actingAs(programOwner())->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('program.active_phase', null)
            ->where('program.phases.0.state', 'upcoming')
            ->where('program.phases.0.viewable', true)
            ->where('program.phases.1.viewable', false)
            ->where('program.registration.registered', 1)
            ->has('program.registration.trend', 0));
});

function programTeam(Project $project, User $leader, array $members = []): void
{
    $project->members()->attach($leader->id, ['role_in_project' => 'LEADER', 'status' => 'ACTIVE', 'joined_at' => now()]);

    foreach ($members as $member) {
        $project->members()->attach($member->id, ['role_in_project' => 'MEMBER', 'status' => 'ACTIVE', 'joined_at' => now()]);
    }
}

test('phase 2 shows selection results, formed teams and how well they match', function () {
    Carbon::setTestNow('2026-12-05 10:00:00');
    $batch = programBatch();
    $finance = Organization::factory()->create(['name' => 'DIV FINANCE']);
    $competency = Competency::factory()->create();
    [$leader, $member, $soloLeader, $other] = [programParticipant($finance), programParticipant(), programParticipant($finance), programParticipant()];
    MemberCompetency::factory()->create(['user_id' => $member->id, 'competency_id' => $competency->id, 'self_level' => 3]);

    $withTeam = Project::factory()->status(ProposalStatus::Approved)->create(['batch_id' => $batch->id, 'proposer_id' => $leader->id, 'submitted_at' => now()]);
    DB::table('project_requirements')->insert(['project_id' => $withTeam->id, 'competency_id' => $competency->id, 'minimum_level' => 2, 'created_at' => now(), 'updated_at' => now()]);
    programTeam($withTeam, $leader, [$member]);
    $leaderOnly = Project::factory()->status(ProposalStatus::Approved)->create(['batch_id' => $batch->id, 'proposer_id' => $soloLeader->id, 'submitted_at' => now()]);
    programTeam($leaderOnly, $soloLeader);
    Project::factory()->status(ProposalStatus::Rejected)->create(['batch_id' => $batch->id, 'proposer_id' => $other->id, 'submitted_at' => now()]);
    Project::factory()->status(ProposalStatus::UnderReview)->create(['batch_id' => $batch->id, 'proposer_id' => $other->id, 'submitted_at' => now()]);

    $this->actingAs(programOwner())->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('program.active_phase', 'selection')
            ->where('program.selection.total', 4)
            ->where('program.selection.passed', 2)
            ->where('program.selection.failed', 1)
            ->where('program.selection.in_selection', 1)
            ->where('program.selection.teams_formed', 1)
            ->where('program.selection.teams_pending', 1)
            ->where('program.selection.funnel.2', ['key' => 'formed', 'label' => 'Tim Terbentuk', 'count' => 1])
            ->where('program.selection.passed_by_organization.0', ['name' => 'DIV FINANCE', 'count' => 2])
            ->where('program.selection.matching.0.count', 1)
            ->where('program.selection.team_sizes.0', ['size' => 2, 'label' => '2 Orang', 'count' => 1, 'percent' => 100])
            ->where('program.execution', null));
});

test('phase 3 tracks step progress, overdue steps and late reports', function () {
    Carbon::setTestNow('2027-02-01 10:00:00');
    $batch = programBatch();
    $leader = programParticipant();
    $leader->syncRoles([Role::findOrCreate('Team Leader', 'web')]);
    $mine = Project::factory()->status(ProposalStatus::Approved)->create(['batch_id' => $batch->id, 'proposer_id' => $leader->id, 'title' => 'Proyek saya', 'approved_at' => now()]);
    $theirs = Project::factory()->status(ProposalStatus::Approved)->create(['batch_id' => $batch->id, 'title' => 'Proyek tim lain', 'approved_at' => now()]);
    programTeam($mine, $leader, [programParticipant()]);
    programTeam($theirs, programParticipant(), [programParticipant()]);

    foreach ([$mine, $theirs] as $project) {
        app(InitializeProjectSteps::class)->handle($project);
        $project->steps()->where('step_number', 1)->update(['status' => StepStatus::Approved->value, 'progress_pct' => 100]);
    }
    // My step 2 is past its target date, so my project is overdue.
    $mine->steps()->where('step_number', 2)->update(['status' => StepStatus::InProgress->value, 'progress_pct' => 40, 'target_date' => '2027-01-20']);
    BiweeklyReport::query()->create(['project_id' => $theirs->id, 'period_no' => 1, 'starts_on' => '2027-01-04', 'ends_on' => '2027-01-17', 'status' => 'DRAFT', 'created_by' => $leader->id]);

    $this->actingAs(programOwner())->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('program.active_phase', 'execution')
            ->where('program.execution.total', 2)
            ->where('program.execution.on_time', ['count' => 1, 'overdue' => 1, 'percent' => 50])
            ->where('program.execution.biweekly', ['compliant' => 1, 'late' => 1, 'percent' => 50])
            ->where('program.execution.steps.0.average_progress', 100)
            ->where('program.execution.steps.1.average_progress', 20)
            ->where('program.execution.steps.1.projects_started', 1)
            ->where('program.execution.projects_scope', 'all')
            ->has('program.execution.projects', 2)
            ->where('program.execution.projects.0.status', 'overdue')
            ->where('program.execution.projects.0.current_step.number', 2)
            ->where('program.execution.projects.0.steps.0', 'done'));

    // A Team Leader gets the same totals but lists only their own project.
    $this->actingAs($leader)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('program.execution.total', 2)
            ->where('program.execution.projects_scope', 'own')
            ->has('program.execution.projects', 1)
            ->where('program.execution.projects.0.title', 'Proyek saya'));
});

test('a registration phase longer than half a year draws only its last 180 days', function () {
    Carbon::setTestNow('2026-11-20 10:00:00');
    QccBatch::factory()->active()->create(['starts_on' => '2025-11-01', 'ends_on' => '2027-07-31']);

    $this->actingAs(programOwner())->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('program.registration.trend', 181)
            ->where('program.registration.trend.180.date', '2026-11-20'));
});

test('a switched-off LOB/Div keeps its proposals on the chart', function () {
    Carbon::setTestNow('2026-11-20 10:00:00');
    $batch = programBatch();
    $closed = Organization::factory()->create(['name' => 'DIV LAMA', 'is_active' => false]);
    Organization::factory()->create(['name' => 'DIV NONAKTIF TANPA PROPOSAL', 'is_active' => false]);
    Project::factory()->status(ProposalStatus::Submitted)->create(['batch_id' => $batch->id, 'proposer_id' => programParticipant($closed)->id, 'submitted_at' => now()]);

    $this->actingAs(programOwner())->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('program.registration.submissions_by_organization.0', ['name' => 'DIV LAMA', 'count' => 1])
            ->where('program.registration.submissions_by_organization', fn ($rows) => collect($rows)->doesntContain('name', 'DIV NONAKTIF TANPA PROPOSAL')));
});

test('proposals saved with the first theme categories move to the new ones', function () {
    $projects = collect(['REVENUE_GM', 'EFFICIENCY', 'RISK_COMPLIANCE_PROCESS_CONTROL', 'PRODUCTIVITY'])
        ->map(fn (string $old) => tap(Project::factory()->create(), fn (Project $project) => DB::table('projects')->where('id', $project->id)->update(['theme_category' => $old])));

    (require database_path('migrations/2026_11_06_134057_rename_theme_categories_on_projects_table.php'))->up();

    expect($projects->map(fn (Project $project) => $project->fresh()->theme_category)->all())->toBe([
        ThemeCategory::NewRevenueStream,
        ThemeCategory::CostEfficiency,
        ThemeCategory::SafetyRiskCompliance,
        ThemeCategory::Productivity,
    ]);
});
