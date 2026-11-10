<?php

use App\Models\MemberCompetency;
use App\Models\Organization;
use App\Models\QccBatch;
use App\Models\User;
use Database\Seeders\ProjectAccessSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(ProjectAccessSeeder::class);
});

function talentPoolMember(array $attributes = []): User
{
    $user = User::factory()->create(['email' => 'anggota@sim.co.id', 'password' => 'SIM1493', ...$attributes]);
    $user->assignRole('SIMer / Member');

    return $user;
}

test('a member without competencies goes to fill them in right after login', function () {
    talentPoolMember();

    $this->post(route('login.store'), ['email' => 'anggota@sim.co.id', 'password' => 'SIM1493'])
        ->assertRedirect(route('my-competencies.index', absolute: false));

    $this->get(route('my-competencies.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('mustJoinTalentPool', true));
});

test('a member in the talent pool logs in to the dashboard as usual', function () {
    $member = talentPoolMember();
    MemberCompetency::factory()->create(['user_id' => $member->id]);

    $this->post(route('login.store'), ['email' => 'anggota@sim.co.id', 'password' => 'SIM1493'])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->get(route('my-competencies.index'))
        ->assertInertia(fn (Assert $page) => $page->where('mustJoinTalentPool', false));
});

test('completing the profile leads a member outside the talent pool to the competencies page', function () {
    $member = talentPoolMember(['needs_profile_setup' => true]);

    $this->actingAs($member)
        ->patch(route('profile.update'), [
            'name' => $member->name,
            'email' => $member->email,
            'position' => 'Staf Operasional',
            'work_location' => 'Jakarta',
            'phone_number' => '081234567890',
            'organization_id' => Organization::factory()->create(['is_active' => true])->id,
            'availability_status' => 'AVAILABLE',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('my-competencies.index'));
});

test('a proposal can only be started once the member is in the talent pool', function () {
    $member = talentPoolMember();
    $member->givePermissionTo(Permission::findOrCreate('projects.create', 'web'));
    $batch = QccBatch::factory()->active()->create();

    $this->actingAs($member)->get(route('proposals.create'))->assertRedirect(route('my-competencies.index'));
    $this->post(route('proposals.store'), ['batch_id' => $batch->id, 'title' => 'Ide perbaikan', 'wizard_step' => 1, 'advance' => false])
        ->assertRedirect(route('my-competencies.index'));

    MemberCompetency::factory()->create(['user_id' => $member->id]);

    $this->get(route('proposals.create'))->assertOk();
});

test('reviewers are never asked to join the talent pool', function () {
    $owner = User::factory()->create(['email' => 'owner@sim.co.id', 'password' => 'SIM1150']);
    $owner->assignRole(Role::findOrCreate('Owner QCC', 'web'));
    $owner->givePermissionTo(Permission::findOrCreate('projects.viewAny', 'web'));

    $this->post(route('login.store'), ['email' => 'owner@sim.co.id', 'password' => 'SIM1150'])
        ->assertRedirect(route('dashboard', absolute: false));
});
