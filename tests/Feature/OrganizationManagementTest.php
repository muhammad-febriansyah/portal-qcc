<?php

use App\Models\Organization;
use App\Models\User;
use Spatie\Permission\Models\Permission;

function organizationAdmin(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('organizations.manage', 'web'));

    return $user;
}

test('an authorized admin can delete an organization and unassign its members', function () {
    $organization = Organization::factory()->create();
    $member = User::factory()->create(['organization_id' => $organization->id]);

    $this->actingAs(organizationAdmin())
        ->delete(route('admin.organizations.destroy', $organization))
        ->assertRedirect(route('admin.organizations.index'));

    $this->assertDatabaseMissing('organizations', ['id' => $organization->id]);
    $this->assertDatabaseHas('users', ['id' => $member->id, 'organization_id' => null]);
    $this->assertDatabaseHas('audit_logs', [
        'event' => 'organization.deleted',
        'subject_id' => $organization->id,
    ]);
});

test('users without organization management permission cannot delete organizations', function () {
    $organization = Organization::factory()->create();

    $this->actingAs(User::factory()->create())
        ->delete(route('admin.organizations.destroy', $organization))
        ->assertForbidden();

    $this->assertDatabaseHas('organizations', ['id' => $organization->id]);
});
