<?php

use App\Enums\ProposalStatus;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\ProjectAccessSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function teamFormationUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate($role, 'web'));

    return $user;
}

it('lists draft proposals for reviewers who can view all projects', function () {
    $this->seed(ProjectAccessSeeder::class);

    $owner = teamFormationUser('Owner QCC');
    $ownedDraft = Project::factory()->create(['title' => 'Optimasi proses pengemasan']);
    $otherDraft = Project::factory()->create(['title' => 'Perbaikan waktu setup']);
    Project::factory()->status(ProposalStatus::Submitted)->create(['title' => 'Proposal sudah diajukan']);

    $this->actingAs($owner)->get(route('team-formation.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teams/index')
            ->where('proposals.total', 2)
            ->has('proposals.data', 2)
            ->where('proposals.data.0.title', 'Perbaikan waktu setup')
            ->where('proposals.data.1.title', 'Optimasi proses pengemasan'));
});

it('limits Team Leader to their own draft proposals', function () {
    $this->seed(ProjectAccessSeeder::class);

    $leader = teamFormationUser('Team Leader');
    Project::factory()->create(['proposer_id' => $leader->id, 'title' => 'Proposal milik saya']);
    Project::factory()->create(['title' => 'Proposal milik orang lain']);

    $this->actingAs($leader)->get(route('team-formation.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teams/index')
            ->has('proposals.data', 1)
            ->where('proposals.data.0.title', 'Proposal milik saya'));
});

it('filters team formation proposals by title or proposal code', function () {
    $this->seed(ProjectAccessSeeder::class);

    $owner = teamFormationUser('Owner QCC');
    $match = Project::factory()->create(['title' => 'Kualitas produk']);
    Project::factory()->create(['title' => 'Efisiensi mesin']);

    $this->actingAs($owner)->get(route('team-formation.index', ['search' => sprintf('PRP-%04d', $match->id)]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('proposals.data', 1)
            ->where('proposals.data.0.id', $match->id));
});

it('shows proposals returned for revision in team formation to Owner QCC', function () {
    $this->seed(ProjectAccessSeeder::class);

    $owner = teamFormationUser('Owner QCC');
    Project::factory()->status(ProposalStatus::RevisionRequired)->create(['title' => 'Perlu susun ulang tim']);

    $this->actingAs($owner)->get(route('team-formation.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('proposals.data', 1)
            ->where('proposals.data.0.title', 'Perlu susun ulang tim'));
});

it('forbids members without team formation authority', function () {
    $member = teamFormationUser('SIMer / Member');
    $member->givePermissionTo(Permission::findOrCreate('projects.view', 'web'));

    $this->actingAs($member)->get(route('team-formation.index'))->assertForbidden();
});
