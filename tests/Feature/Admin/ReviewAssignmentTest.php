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

beforeEach(function () {
    $this->seed(ProjectAccessSeeder::class);
    $this->seed(ScoringPermissionSeeder::class);
    $this->owner = User::factory()->create();
    $this->owner->assignRole(Role::findOrCreate('Owner QCC', 'web'));
});

it('creates an audited reviewer assignment for an eligible proposal reviewer', function () {
    $project = Project::factory()->status(ProposalStatus::Submitted)->create();
    $reviewer = User::factory()->create();
    $reviewer->givePermissionTo([
        Permission::findOrCreate('projects.view', 'web'),
        Permission::findOrCreate('projects.review', 'web'),
    ]);
    $this->actingAs($this->owner)
        ->post(route('admin.reviewer-assignments.store'), [
            'project_id' => $project->id,
            'reviewer_id' => $reviewer->id,
            'scope' => 'proposal',
            'expires_at' => now()->addDays(30)->toDateString(),
        ])
        ->assertRedirect(route('admin.reviewer-assignments.index'));

    $assignment = ReviewAssignment::query()->sole();
    expect($assignment->status)->toBe(ReviewAssignment::ACTIVE)
        ->and($assignment->granted_by)->toBe($this->owner->id)
        ->and($assignment->expires_at?->format('H:i:s'))->toBe('23:59:59');
    $this->assertDatabaseHas('audit_logs', ['event' => 'review_assignment.created', 'subject_id' => $assignment->id]);
});

it('renders the Owner assignment page with reviewers filtered by permission scope', function () {
    $project = Project::factory()->status(ProposalStatus::Submitted)->create();
    $reviewer = User::factory()->create();
    $reviewer->givePermissionTo([
        Permission::findOrCreate('projects.view', 'web'),
        Permission::findOrCreate('projects.review', 'web'),
    ]);
    $this->actingAs($this->owner)
        ->get(route('admin.reviewer-assignments.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reviewer-assignments/index')
            ->has('projects', 1)
            ->where('projects.0.value', (string) $project->id)
            ->has('reviewers', 1)
            ->where('reviewers.0.value', (string) $reviewer->id)
            ->where('reviewers.0.scopes', ['proposal', 'steps', 'biweekly']));
});

it('revokes an active assignment and writes a readable audit event', function () {
    $project = Project::factory()->status(ProposalStatus::Submitted)->create();
    $reviewer = User::factory()->create();
    $reviewer->givePermissionTo([
        Permission::findOrCreate('projects.view', 'web'),
        Permission::findOrCreate('projects.review', 'web'),
    ]);
    $assignment = ReviewAssignment::factory()->create([
        'project_id' => $project->id,
        'reviewer_id' => $reviewer->id,
        'scope' => 'proposal',
    ]);

    $this->actingAs($this->owner)
        ->patch(route('admin.reviewer-assignments.revoke', $assignment))
        ->assertRedirect();

    expect($assignment->fresh()->status)->toBe(ReviewAssignment::REVOKED);
    $this->assertDatabaseHas('audit_logs', ['event' => 'review_assignment.revoked', 'subject_id' => $assignment->id]);
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

it('rejects assigning a reviewer who lacks permission for the selected scope', function () {
    $project = Project::factory()->status(ProposalStatus::Submitted)->create();
    $reviewer = User::factory()->create();
    $reviewer->givePermissionTo(Permission::findOrCreate('projects.view', 'web'));

    $this->actingAs($this->owner)
        ->from(route('admin.reviewer-assignments.index'))
        ->post(route('admin.reviewer-assignments.store'), [
            'project_id' => $project->id,
            'reviewer_id' => $reviewer->id,
            'scope' => 'proposal',
        ])
        ->assertSessionHasErrors('reviewer_id');

    $this->assertDatabaseCount('review_assignments', 0);
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
