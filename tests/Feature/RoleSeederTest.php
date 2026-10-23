<?php

use Database\Seeders\DatabaseSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('database seeding stays at five canonical roles when repeated', function () {
    $expected = ['Mentor', 'Owner QCC', 'SIMer / Member', 'Super Admin', 'Team Leader'];

    $this->seed(DatabaseSeeder::class);
    $firstRun = Role::query()->orderBy('name')->pluck('name')->all();

    $this->seed(DatabaseSeeder::class);
    $secondRun = Role::query()->orderBy('name')->pluck('name')->all();

    expect($firstRun)->toBe($expected)
        ->and($secondRun)->toBe($expected)
        ->and(Role::query()->count())->toBe(5);
});

test('Owner QCC can manage rubric drafts but cannot publish rubric versions by default', function () {
    $this->seed(DatabaseSeeder::class);

    $owner = Role::findByName('Owner QCC', 'web');
    $superAdmin = Role::findByName('Super Admin', 'web');

    expect($owner->hasPermissionTo(Permission::findByName('rubrics.manage', 'web')))->toBeTrue()
        ->and($owner->hasPermissionTo(Permission::findByName('rubrics.publish', 'web')))->toBeFalse()
        ->and($superAdmin->hasPermissionTo(Permission::findByName('rubrics.publish', 'web')))->toBeTrue();
});
