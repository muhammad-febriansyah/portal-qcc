<?php

use App\Actions\Proposals\InitializeProjectSteps;
use App\Enums\ProposalStatus;
use App\Enums\ReportStatus;
use App\Enums\ScoringStatus;
use App\Enums\StepStatus;
use App\Models\BiweeklyReport;
use App\Models\Project;
use App\Models\ProjectMentor;
use App\Models\ProjectStep;
use App\Models\RubricSet;
use App\Models\RubricVersion;
use App\Models\ScoringRun;
use App\Models\User;
use Database\Seeders\ProjectAccessSeeder;
use Database\Seeders\ScoringPermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function reviewInboxUser(string $role = 'Owner QCC'): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate($role, 'web'));

    return $user;
}

beforeEach(function () {
    $this->seed(ProjectAccessSeeder::class);
    $this->seed(ScoringPermissionSeeder::class);
    $this->owner = reviewInboxUser();
});

it('shows submitted proposals in the proposal review tab', function () {
    $submitted = Project::factory()->status(ProposalStatus::Submitted)->create([
        'title' => 'Kurangi waktu tunggu',
        'submitted_at' => now(),
    ]);
    Project::factory()->status(ProposalStatus::Approved)->create(['title' => 'Sudah disetujui']);

    $this->actingAs($this->owner)
        ->get(route('approval.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('approval/index')
            ->where('filters.tab', 'proposals')
            ->where('counts.proposals', 1)
            ->has('items.data', 1)
            ->where('items.data.0.title', 'Kurangi waktu tunggu')
            ->where('items.data.0.url', route('proposals.show', $submitted)));
});

it('shows submitted steps and bi-weekly reports only for visible approved projects', function () {
    $project = Project::factory()->status(ProposalStatus::Approved)->create(['title' => 'Perbaikan proses']);
    app(InitializeProjectSteps::class)->handle($project);
    $step = ProjectStep::query()->where('project_id', $project->id)->where('step_number', 1)->firstOrFail();
    $step->forceFill(['status' => StepStatus::Submitted, 'submitted_at' => now()])->save();

    $report = BiweeklyReport::query()->create([
        'project_id' => $project->id,
        'period_no' => 1,
        'starts_on' => '2026-10-01',
        'ends_on' => '2026-10-14',
        'status' => ReportStatus::Submitted,
        'submitted_at' => now(),
        'created_by' => $this->owner->id,
    ]);

    $this->actingAs($this->owner)
        ->get(route('approval.index', ['tab' => 'steps']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('counts.steps', 1)
            ->has('items.data', 1)
            ->where('items.data.0.title', 'Menentukan Tema Improvement · Perbaikan proses'));

    $this->actingAs($this->owner)
        ->get(route('approval.index', ['tab' => 'biweekly']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('counts.biweekly', 1)
            ->has('items.data', 1)
            ->where('items.data.0.title', 'Laporan Bi-weekly Periode 1')
            ->where('items.data.0.url', route('projects.biweekly.index', $project)));
});

it('shows only pending scoring reviews for approved projects in the scoring tab', function () {
    $project = Project::factory()->status(ProposalStatus::Approved)->create(['title' => 'Hemat energi lini produksi']);
    $unapprovedProject = Project::factory()->status(ProposalStatus::Draft)->create(['title' => 'Draft belum disetujui']);
    $rubric = RubricSet::query()->create(['scoring_type' => 'proposal', 'name' => 'Rubrik Proposal']);
    $version = RubricVersion::query()->create([
        'rubric_set_id' => $rubric->id,
        'version_number' => 1,
        'publication_status' => 'draft',
    ]);

    $pendingRun = ScoringRun::factory()->create([
        'project_id' => $project->id,
        'rubric_version_id' => $version->id,
        'triggered_by' => $this->owner->id,
        'status' => ScoringStatus::PendingReview,
        'completed_at' => now(),
    ]);
    ScoringRun::factory()->create([
        'project_id' => $project->id,
        'rubric_version_id' => $version->id,
        'triggered_by' => $this->owner->id,
        'status' => ScoringStatus::Approved,
    ]);
    ScoringRun::factory()->create([
        'project_id' => $unapprovedProject->id,
        'rubric_version_id' => $version->id,
        'triggered_by' => $this->owner->id,
        'status' => ScoringStatus::PendingReview,
    ]);

    $this->actingAs($this->owner)
        ->get(route('approval.index', ['tab' => 'scoring']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.tab', 'scoring')
            ->where('counts.scoring', 1)
            ->has('items.data', 1)
            ->where('items.data.0.title', 'Review AI Scoring · Hemat energi lini produksi')
            ->where('items.data.0.url', route('admin.scoring.show', $pendingRun)));
});

it('does not expose the scoring review tab to users without scoring review access', function () {
    $this->actingAs(reviewInboxUser('Mentor'))
        ->get(route('approval.index', ['tab' => 'scoring']))
        ->assertForbidden();
});

it('hides scoring from project reviewers without scoring permissions', function () {
    $reviewer = User::factory()->create();
    $reviewer->givePermissionTo([
        Permission::findOrCreate('projects.viewAny', 'web'),
        Permission::findOrCreate('projects.review', 'web'),
    ]);

    $this->actingAs($reviewer)
        ->get(route('approval.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('availableTabs', ['proposals', 'steps', 'biweekly'])
            ->where('counts.scoring', 0));

    $this->actingAs($reviewer)
        ->get(route('approval.index', ['tab' => 'scoring']))
        ->assertForbidden();
});

it('allows scoring reviewers without project review access to open only the scoring tab', function () {
    $reviewer = User::factory()->create();
    $reviewer->givePermissionTo([
        Permission::findOrCreate('projects.viewAny', 'web'),
        Permission::findOrCreate('scoring.view', 'web'),
        Permission::findOrCreate('scoring.review', 'web'),
    ]);

    $this->actingAs($reviewer)
        ->get(route('approval.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('availableTabs', ['scoring'])
            ->where('filters.tab', 'scoring')
            ->where('counts.proposals', 0));

    $this->actingAs($reviewer)
        ->get(route('approval.index', ['tab' => 'proposals']))
        ->assertForbidden();
});

it('keeps Mentor bi-weekly access limited and scoring restricted', function () {
    $mentor = reviewInboxUser('Mentor');

    $this->actingAs($mentor)
        ->get(route('approval.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('availableTabs', ['biweekly'])
            ->where('filters.tab', 'biweekly')
            ->where('counts.biweekly', 0));

    $this->actingAs($mentor)
        ->get(route('approval.index', ['tab' => 'scoring']))
        ->assertForbidden();
});

it('shows a Mentor only submitted steps on actively mentored projects', function () {
    $mentor = reviewInboxUser('Mentor');
    $assigned = Project::factory()->status(ProposalStatus::Approved)->create(['title' => 'Proyek bimbingan']);
    $other = Project::factory()->status(ProposalStatus::Approved)->create(['title' => 'Proyek lain']);
    app(InitializeProjectSteps::class)->handle($assigned);
    app(InitializeProjectSteps::class)->handle($other);
    $assigned->steps()->where('step_number', 1)->firstOrFail()->forceFill(['status' => StepStatus::Submitted, 'submitted_at' => now()])->save();
    $other->steps()->where('step_number', 1)->firstOrFail()->forceFill(['status' => StepStatus::Submitted, 'submitted_at' => now()])->save();
    ProjectMentor::query()->create([
        'project_id' => $assigned->id,
        'mentor_id' => $mentor->id,
        'assigned_by' => $this->owner->id,
        'status' => 'ACTIVE',
        'assigned_at' => now(),
    ]);

    $this->actingAs($mentor)
        ->get(route('approval.index', ['tab' => 'steps']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('availableTabs', ['steps', 'biweekly'])
            ->where('counts.steps', 1)
            ->has('items.data', 1)
            ->where('items.data.0.title', 'Menentukan Tema Improvement · Proyek bimbingan'));
});
