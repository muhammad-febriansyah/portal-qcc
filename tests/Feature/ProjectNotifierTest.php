<?php

use App\Models\Project;
use App\Models\ProjectMentor;
use App\Models\ReviewAssignment;
use App\Models\User;
use App\Services\ProjectNotifier;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function notifierUser(string $role, bool $global = false): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate($role, 'web'));
    $user->givePermissionTo(Permission::findOrCreate('projects.review', 'web'));

    if ($global) {
        $user->givePermissionTo(Permission::findOrCreate('projects.viewAny', 'web'));
    }

    return $user;
}

it('limits review notifications to global reviewers or active assignments for the project and scope', function () {
    $project = Project::factory()->create();
    $otherProject = Project::factory()->create();
    $owner = notifierUser('Owner QCC', true);
    $mentor = notifierUser('Mentor');
    $assigned = notifierUser('Team Leader');
    $unassigned = notifierUser('Team Leader');

    ProjectMentor::query()->create([
        'project_id' => $project->id,
        'mentor_id' => $mentor->id,
        'assigned_by' => $owner->id,
        'status' => 'ACTIVE',
        'assigned_at' => now(),
    ]);
    ReviewAssignment::factory()->create(['project_id' => $project->id, 'reviewer_id' => $assigned->id, 'scope' => 'steps']);
    ReviewAssignment::factory()->create(['project_id' => $otherProject->id, 'reviewer_id' => $unassigned->id, 'scope' => 'steps']);

    $notifier = app(ProjectNotifier::class);

    expect($notifier->reviewers($project, 'proposal')->modelKeys())->toBe([$owner->id])
        ->and($notifier->reviewers($project, 'steps')->modelKeys())->toEqualCanonicalizing([$owner->id, $mentor->id, $assigned->id])
        ->and($notifier->reviewers($project, 'biweekly')->modelKeys())->toEqualCanonicalizing([$owner->id, $mentor->id])
        ->and($notifier->reviewers($project, 'unknown'))->toBeEmpty();
});
