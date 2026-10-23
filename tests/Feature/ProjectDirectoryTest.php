<?php

use App\Enums\ProposalStatus;
use App\Models\Project;
use App\Models\ProjectMentor;
use App\Models\User;
use Database\Seeders\ProjectAccessSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function projectDirectoryMember(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('SIMer / Member', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('projects.view', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('projects.create', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('evidence.view', 'web'));

    return $user;
}

function attachProjectDirectoryMember(Project $project, User $user): void
{
    $project->members()->attach($user->id, [
        'role_in_project' => 'MEMBER',
        'status' => 'ACTIVE',
        'joined_at' => now(),
    ]);
}

it('lists only accessible projects and limits steps and evidence directories to approved projects', function () {
    $member = projectDirectoryMember();
    $draft = Project::factory()->create(['proposer_id' => $member->id]);
    $approved = Project::factory()->status(ProposalStatus::Approved)->create();
    attachProjectDirectoryMember($approved, $member);
    Project::factory()->create();

    $this->actingAs($member)->get(route('qcc-projects.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/directory')
            ->where('view', 'projects')
            ->has('projects.data', 2)
            ->where('projects.data.0.id', $approved->id)
            ->where('projects.data.1.id', $draft->id));

    $this->get(route('project-steps.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/directory')
            ->where('view', 'steps')
            ->has('projects.data', 1)
            ->where('projects.data.0.id', $approved->id));

    $this->get(route('evidence-repository.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/directory')
            ->where('view', 'evidence')
            ->has('projects.data', 1)
            ->where('projects.data.0.id', $approved->id));
});

it('forbids the evidence directory when the account lacks evidence view permission', function () {
    $member = User::factory()->create();
    $member->assignRole(Role::findOrCreate('SIMer / Member', 'web'));
    $member->givePermissionTo(Permission::findOrCreate('projects.view', 'web'));

    $this->actingAs($member)->get(route('evidence-repository.index'))->assertForbidden();
});

it('lets Owner QCC open the evidence directory with seeded view access', function () {
    $this->seed(ProjectAccessSeeder::class);

    $owner = User::factory()->create();
    $owner->assignRole(Role::findOrCreate('Owner QCC', 'web'));

    $this->actingAs($owner)->get(route('evidence-repository.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/directory')
            ->where('view', 'evidence')
            ->has('projects.data', 0));
});

it('renders empty project directories for users with no accessible projects', function () {
    $member = projectDirectoryMember();

    $this->actingAs($member)->get(route('project-steps.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/directory')
            ->where('view', 'steps')
            ->has('projects.data', 0)
            ->where('projects.total', 0));
});

it('links Team Leader activity menus to approved projects they belong to', function () {
    $this->seed(ProjectAccessSeeder::class);

    $leader = User::factory()->create();
    $leader->assignRole(Role::findOrCreate('Team Leader', 'web'));
    $project = Project::factory()->status(ProposalStatus::Approved)->create();
    $project->members()->attach($leader->id, [
        'role_in_project' => 'LEADER',
        'status' => 'ACTIVE',
        'joined_at' => now(),
    ]);
    Project::factory()->status(ProposalStatus::Approved)->create();
    Project::factory()->create(['proposer_id' => $leader->id]);

    foreach ([
        ['biweekly-directory.index', 'biweekly'],
        ['mentoring-directory.index', 'mentoring'],
        ['risk-directory.index', 'risks'],
    ] as [$routeName, $view]) {
        $this->actingAs($leader)->get(route($routeName))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('projects/directory')
                ->where('view', $view)
                ->has('projects.data', 1)
                ->where('projects.data.0.id', $project->id));
    }
});

it('limits Mentor activity directories to projects with an active assignment', function () {
    $this->seed(ProjectAccessSeeder::class);

    $mentor = User::factory()->create();
    $mentor->assignRole(Role::findOrCreate('Mentor', 'web'));
    $project = Project::factory()->status(ProposalStatus::Approved)->create();
    ProjectMentor::query()->create([
        'project_id' => $project->id,
        'mentor_id' => $mentor->id,
        'assigned_by' => User::factory()->create()->id,
        'status' => 'ACTIVE',
        'assigned_at' => now(),
    ]);
    Project::factory()->status(ProposalStatus::Approved)->create();

    $this->actingAs($mentor)->get(route('mentoring-directory.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/directory')
            ->where('view', 'mentoring')
            ->has('projects.data', 1)
            ->where('projects.data.0.id', $project->id));
});
