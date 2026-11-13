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

it('lists approved proposals for reviewers who can view all projects', function () {
    $this->seed(ProjectAccessSeeder::class);

    $owner = teamFormationUser('Owner QCC');
    Project::factory()->status(ProposalStatus::Approved)->create(['title' => 'Optimasi proses pengemasan']);
    Project::factory()->status(ProposalStatus::Approved)->create(['title' => 'Perbaikan waktu setup']);
    Project::factory()->status(ProposalStatus::UnderReview)->create(['title' => 'Masih diseleksi']);
    Project::factory()->create(['title' => 'Proposal masih draf']);

    $this->actingAs($owner)->get(route('team-formation.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('teams/index')
            ->where('proposals.total', 2)
            ->has('proposals.data', 2)
            ->where('proposals.data.0.title', 'Perbaikan waktu setup')
            ->where('proposals.data.1.title', 'Optimasi proses pengemasan'));
});

it('limits Team Leader to their own approved proposals', function () {
    $this->seed(ProjectAccessSeeder::class);

    $leader = teamFormationUser('Team Leader');
    Project::factory()->status(ProposalStatus::Approved)->create(['proposer_id' => $leader->id, 'title' => 'Proposal milik saya']);
    Project::factory()->status(ProposalStatus::Approved)->create(['title' => 'Proposal milik orang lain']);

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
    $match = Project::factory()->status(ProposalStatus::Approved)->create(['title' => 'Kualitas produk']);
    Project::factory()->status(ProposalStatus::Approved)->create(['title' => 'Efisiensi mesin']);

    $this->actingAs($owner)->get(route('team-formation.index', ['search' => sprintf('PRP-%04d', $match->id)]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('proposals.data', 1)
            ->where('proposals.data.0.id', $match->id));
});

it('leaves proposals that are still in selection out of team formation', function () {
    $this->seed(ProjectAccessSeeder::class);

    $owner = teamFormationUser('Owner QCC');
    Project::factory()->status(ProposalStatus::RevisionRequired)->create(['title' => 'Perlu revisi']);
    Project::factory()->status(ProposalStatus::Rejected)->create(['title' => 'Tidak lolos']);

    $this->actingAs($owner)->get(route('team-formation.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('proposals.data', 0));
});

it('forbids members without team formation authority', function () {
    $member = teamFormationUser('SIMer / Member');
    $member->givePermissionTo(Permission::findOrCreate('projects.view', 'web'));

    $this->actingAs($member)->get(route('team-formation.index'))->assertForbidden();
});
