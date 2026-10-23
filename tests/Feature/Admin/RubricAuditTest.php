<?php

use App\Models\AuditLog;
use App\Models\RubricSet;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function rubricManager(bool $canPublish = true): User
{
    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::findOrCreate('rubrics.manage', 'web'));

    if ($canPublish) {
        $admin->assignRole(Role::findOrCreate('Super Admin', 'web'));
        $admin->givePermissionTo(Permission::findOrCreate('rubrics.publish', 'web'));
    }

    return $admin;
}

function createDraftRubric(string $weight = '100.00'): array
{
    $rubricSet = RubricSet::query()->create([
        'scoring_type' => 'proposal',
        'name' => 'Rubrik Proposal',
    ]);
    $version = $rubricSet->versions()->create([
        'version_number' => 1,
        'publication_status' => 'draft',
    ]);
    $criterion = $version->criteria()->create([
        'code' => 'GOALS',
        'name' => 'Tujuan',
        'description' => 'Kejelasan tujuan',
        'weight_pct' => $weight,
        'position' => 1,
    ]);

    return [$rubricSet, $version, $criterion];
}

test('rubric draft and criteria changes are included in audit history', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::findOrCreate('rubrics.manage', 'web'));
    $this->actingAs($admin);

    $this->post(route('admin.rubrics.store'), [
        'scoring_type' => 'proposal',
        'name' => 'Rubrik Proposal',
        'criteria' => [[
            'code' => 'GOALS',
            'name' => 'Tujuan',
            'description' => 'Kejelasan tujuan',
            'weight_pct' => '100.00',
        ]],
    ])->assertRedirect(route('admin.rubrics.index'));

    $rubric = RubricSet::query()->firstOrFail();
    $version = $rubric->versions()->firstOrFail();

    expect(AuditLog::query()->where('event', 'rubric.created')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event', 'rubric_version.created')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event', 'rubric_criterion.created')->exists())->toBeTrue();

    $this->put(route('admin.rubrics.update', $rubric), [
        'name' => 'Rubrik Proposal Revisi',
        'criteria' => [[
            'code' => 'GOALS',
            'name' => 'Tujuan QCC',
            'description' => 'Kejelasan dan ukuran tujuan',
            'weight_pct' => '100.00',
        ]],
    ])->assertRedirect(route('admin.rubrics.index'));

    expect($rubric->fresh()->name)->toBe('Rubrik Proposal Revisi')
        ->and($version->criteria()->firstOrFail()->name)->toBe('Tujuan QCC')
        ->and(AuditLog::query()->where('event', 'rubric.updated')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event', 'rubric_criterion.deleted')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('event', 'rubric_criterion.created')->count())->toBe(2);
});

test('Owner QCC can manage draft rubrics but cannot publish them by default', function () {
    $this->seed(DatabaseSeeder::class);
    // The full seeder also adds the baseline rubrics; this test builds its own proposal rubric.
    RubricSet::query()->delete();

    $owner = User::factory()->create();
    $owner->assignRole(Role::findByName('Owner QCC', 'web'));

    $this->actingAs($owner)
        ->get(route('admin.rubrics.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/rubrics/index')
            ->where('canPublish', false));

    [$rubricSet, $version] = createDraftRubric();

    $this->post(route('admin.rubrics.versions.publish', [$rubricSet, $version]))
        ->assertForbidden();

    expect($version->fresh()->publication_status)->toBe('draft');
});

test('only Super Admin role can publish even if another user has direct publish permission', function () {
    [$rubricSet, $version] = createDraftRubric();
    $owner = User::factory()->create();
    $owner->assignRole(Role::findOrCreate('Owner QCC', 'web'));
    $owner->givePermissionTo(Permission::findOrCreate('rubrics.publish', 'web'));

    $this->actingAs($owner)
        ->post(route('admin.rubrics.versions.publish', [$rubricSet, $version]))
        ->assertForbidden();

    expect($version->fresh()->publication_status)->toBe('draft');
});

test('rubric cannot be published unless criterion weights total exactly 100 percent', function () {
    [$rubricSet, $version] = createDraftRubric('99.99');

    $this->actingAs(rubricManager())
        ->post(route('admin.rubrics.versions.publish', [$rubricSet, $version]))
        ->assertSessionHasErrors('rubric');

    expect($version->fresh()->publication_status)->toBe('draft');
});

test('authorized admins can publish an immutable version and create the next draft from it', function () {
    [$rubricSet, $version] = createDraftRubric();

    $this->actingAs(rubricManager())
        ->post(route('admin.rubrics.versions.publish', [$rubricSet, $version]))
        ->assertRedirect(route('admin.rubrics.index'));

    expect($version->fresh()->publication_status)->toBe('published');
    $version->refresh();
    expect(fn () => $version->update(['publication_status' => 'draft']))
        ->toThrow(LogicException::class);

    $publishedCriterion = $version->criteria()->firstOrFail();
    expect(fn () => $publishedCriterion->update(['weight_pct' => 99]))
        ->toThrow(LogicException::class);

    $this->put(route('admin.rubrics.update', $rubricSet), [
        'name' => 'Nama tidak boleh mengubah versi terbit',
        'criteria' => [[
            'code' => 'GOALS',
            'name' => 'Tujuan',
            'description' => 'Kejelasan tujuan',
            'weight_pct' => '100.00',
        ]],
    ])->assertForbidden();

    $this->post(route('admin.rubrics.versions.store', $rubricSet))
        ->assertRedirect(route('admin.rubrics.index'));

    $nextVersion = $rubricSet->versions()->where('version_number', 2)->with('criteria')->firstOrFail();

    expect($nextVersion->publication_status)->toBe('draft')
        ->and($nextVersion->criteria)->toHaveCount(1)
        ->and((float) $nextVersion->criteria->first()->weight_pct)->toBe(100.0);

    $this->get(route('admin.rubrics.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('canPublish', true)
            ->has('rubrics.0.versions', 2)
            ->where('rubrics.0.versions.0.version_number', 2)
            ->where('rubrics.0.versions.1.version_number', 1));
});

test('rubric publication requires the dedicated publish permission', function () {
    [$rubricSet, $version] = createDraftRubric();

    $this->actingAs(rubricManager(canPublish: false))
        ->post(route('admin.rubrics.versions.publish', [$rubricSet, $version]))
        ->assertForbidden();

    expect($version->fresh()->publication_status)->toBe('draft');
});
