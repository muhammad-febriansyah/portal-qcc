<?php

use App\Enums\ProposalStatus;
use App\Enums\StepStatus;
use App\Models\Project;
use App\Models\ProjectStep;
use App\Models\ReviewAssignment;
use App\Models\RubricSet;
use App\Models\RubricVersion;
use App\Models\ScoringRun;
use App\Models\User;
use Database\Seeders\ProjectAccessSeeder;
use Database\Seeders\ScoringPermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    config()->set('qcc_features.chapter_ten_scoring', true);
});

beforeEach(function () {
    $this->seed(ProjectAccessSeeder::class);
    $this->seed(ScoringPermissionSeeder::class);
    $this->owner = User::factory()->create();
    $this->owner->assignRole(Role::findOrCreate('Owner QCC', 'web'));
});

it('limits assigned proposal reviewer inbox and proposal review action to assigned projects', function () {
    $assignedProject = Project::factory()->status(ProposalStatus::Submitted)->create(['submitted_at' => now()]);
    $otherProject = Project::factory()->status(ProposalStatus::Submitted)->create(['submitted_at' => now()]);
    $reviewer = User::factory()->create();
    $reviewer->givePermissionTo([
        Permission::findOrCreate('projects.view', 'web'),
        Permission::findOrCreate('projects.review', 'web'),
    ]);
    ReviewAssignment::factory()->create([
        'project_id' => $assignedProject->id,
        'reviewer_id' => $reviewer->id,
        'scope' => 'proposal',
    ]);

    $this->actingAs($reviewer)
        ->get(route('approval.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('availableTabs', ['proposals'])
            ->where('counts.proposals', 1)
            ->has('items.data', 1)
            ->where('items.data.0.id', $assignedProject->id));

    $this->actingAs($reviewer)
        ->get(route('proposals.show', $assignedProject))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('proposal.can_start_review', true));

    $this->actingAs($reviewer)
        ->post(route('proposals.review.start', $otherProject))
        ->assertForbidden();
});

it('does not let proposal assignment authorize step review', function () {
    $project = Project::factory()->status(ProposalStatus::Approved)->create();
    $step = ProjectStep::query()->create([
        'project_id' => $project->id,
        'step_number' => 1,
    ]);
    $step->forceFill(['status' => StepStatus::Submitted, 'submitted_at' => now()])->save();
    $reviewer = User::factory()->create();
    $reviewer->givePermissionTo([
        Permission::findOrCreate('projects.view', 'web'),
        Permission::findOrCreate('projects.review', 'web'),
    ]);
    ReviewAssignment::factory()->create([
        'project_id' => $project->id,
        'reviewer_id' => $reviewer->id,
        'scope' => 'proposal',
    ]);

    $this->actingAs($reviewer)
        ->post(route('projects.steps.review.start', [$project, $step->step_number]))
        ->assertForbidden();

    $this->actingAs($reviewer)
        ->get(route('approval.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('availableTabs', ['proposals'])
            ->where('counts.steps', 0));
});

it('requires the scoring assignment scope before a reviewer can view scoring details', function () {
    $project = Project::factory()->status(ProposalStatus::Approved)->create();
    $rubric = RubricSet::query()->create(['scoring_type' => 'proposal', 'name' => 'Rubrik Proposal']);
    $version = RubricVersion::query()->create([
        'rubric_set_id' => $rubric->id,
        'version_number' => 1,
        'publication_status' => 'draft',
    ]);
    $run = ScoringRun::factory()->create([
        'project_id' => $project->id,
        'rubric_version_id' => $version->id,
    ]);
    $reviewer = User::factory()->create();
    $reviewer->givePermissionTo([
        Permission::findOrCreate('projects.view', 'web'),
        Permission::findOrCreate('scoring.view', 'web'),
        Permission::findOrCreate('scoring.review', 'web'),
    ]);
    $assignment = ReviewAssignment::factory()->create([
        'project_id' => $project->id,
        'reviewer_id' => $reviewer->id,
        'scope' => 'proposal',
    ]);

    $this->actingAs($reviewer)
        ->get(route('admin.scoring.show', $run))
        ->assertForbidden();

    $assignment->update(['scope' => 'scoring']);

    $this->actingAs($reviewer)
        ->get(route('admin.scoring.show', $run))
        ->assertOk();
});

it('denies proposal review when assignment is expired or revoked', function (string $status, ?string $expiresAt) {
    $project = Project::factory()->status(ProposalStatus::Submitted)->create();
    $reviewer = User::factory()->create();
    $reviewer->givePermissionTo([
        Permission::findOrCreate('projects.view', 'web'),
        Permission::findOrCreate('projects.review', 'web'),
    ]);
    ReviewAssignment::factory()->create([
        'project_id' => $project->id,
        'reviewer_id' => $reviewer->id,
        'scope' => 'proposal',
        'status' => $status,
        'expires_at' => $expiresAt,
    ]);

    $this->actingAs($reviewer)
        ->post(route('proposals.review.start', $project))
        ->assertForbidden();
})->with([
    'revoked' => [ReviewAssignment::REVOKED, null],
    'expired' => [ReviewAssignment::ACTIVE, now()->subDay()->toDateTimeString()],
]);
