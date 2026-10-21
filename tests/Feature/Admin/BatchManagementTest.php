<?php

use App\Enums\BatchStatus;
use App\Models\QccBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function batchManager(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('batches.manage', 'web'));

    return $user;
}

function validBatchPayload(array $overrides = []): array
{
    return [
        'code' => 'BATCH-2026',
        'name' => 'Batch 2026',
        'starts_on' => '2026-01-05',
        'ends_on' => '2026-06-30',
        'status' => BatchStatus::Draft->value,
        ...$overrides,
    ];
}

test('guests are redirected to login', function () {
    $this->get(route('admin.batches.index'))->assertRedirect(route('login'));
});

test('users without the batches.manage permission cannot manage batches', function () {
    $owner = User::factory()->create();
    $owner->assignRole(Role::findOrCreate('Owner QCC', 'web'));
    $batch = QccBatch::factory()->create();

    $this->actingAs($owner);

    $this->get(route('admin.batches.index'))->assertForbidden();
    $this->post(route('admin.batches.store'), validBatchPayload())->assertForbidden();
    $this->put(route('admin.batches.update', $batch), validBatchPayload())->assertForbidden();
    $this->delete(route('admin.batches.destroy', $batch))->assertForbidden();

    expect(QccBatch::query()->count())->toBe(1);
});

test('super admin sees a paginated batch list', function () {
    QccBatch::factory()->count(12)->create();

    $this->actingAs(batchManager())
        ->get(route('admin.batches.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/batches/index')
            ->has('batches.data', 10)
            ->where('batches.total', 12)
            ->where('filters.per_page', 10)
            ->has('statusOptions', 3));
});

test('batch list can be searched and filtered by status', function () {
    QccBatch::factory()->create(['code' => 'ALPHA-1', 'name' => 'Program Alpha']);
    QccBatch::factory()->active()->create(['code' => 'BETA-1', 'name' => 'Program Beta']);
    QccBatch::factory()->closed()->create(['code' => 'GAMMA-1', 'name' => 'Program Gamma']);

    $this->actingAs(batchManager());

    $this->get(route('admin.batches.index', ['search' => 'beta']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('batches.data', 1)
            ->where('batches.data.0.code', 'BETA-1'));

    $this->get(route('admin.batches.index', ['status' => 'closed']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('batches.data', 1)
            ->where('batches.data.0.code', 'GAMMA-1'));
});

test('batch list sorts by an allowed column and rejects others', function () {
    QccBatch::factory()->create(['code' => 'B-2']);
    QccBatch::factory()->create(['code' => 'A-1']);

    $this->actingAs(batchManager());

    $this->get(route('admin.batches.index', ['sort' => 'code', 'direction' => 'asc']))
        ->assertInertia(fn (Assert $page) => $page->where('batches.data.0.code', 'A-1'));

    $this->get(route('admin.batches.index', ['sort' => 'configuration_json']))
        ->assertSessionHasErrors('sort');
});

test('batch list marks batches that are used by projects', function () {
    $used = QccBatch::factory()->create();
    QccBatch::factory()->create();
    $proposer = User::factory()->create();

    DB::table('projects')->insert([
        'batch_id' => $used->id,
        'proposer_id' => $proposer->id,
        'title' => 'Project',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs(batchManager())
        ->get(route('admin.batches.index', ['sort' => 'code', 'direction' => 'asc']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('batches.data', fn ($rows) => collect($rows)->where('is_used', true)->count() === 1));
});

test('super admin can create a batch and the code is normalized', function () {
    $this->actingAs(batchManager())
        ->post(route('admin.batches.store'), validBatchPayload(['code' => ' batch-2026 ']))
        ->assertRedirect();

    $batch = QccBatch::query()->firstOrFail();

    expect($batch->code)->toBe('BATCH-2026')
        ->and($batch->status)->toBe(BatchStatus::Draft)
        ->and($batch->starts_on->format('Y-m-d'))->toBe('2026-01-05');
});

test('creating a batch validates required fields, code format, uniqueness and dates', function () {
    QccBatch::factory()->create(['code' => 'TAKEN']);

    $this->actingAs(batchManager());

    $this->post(route('admin.batches.store'), [])
        ->assertSessionHasErrors(['code', 'name', 'starts_on', 'ends_on', 'status']);

    $this->post(route('admin.batches.store'), validBatchPayload(['code' => 'TAKEN']))
        ->assertSessionHasErrors('code');

    $this->post(route('admin.batches.store'), validBatchPayload(['code' => 'bad code!']))
        ->assertSessionHasErrors('code');

    $this->post(route('admin.batches.store'), validBatchPayload(['starts_on' => '2026-07-01', 'ends_on' => '2026-06-01']))
        ->assertSessionHasErrors('ends_on');

    $this->post(route('admin.batches.store'), validBatchPayload(['status' => 'archived']))
        ->assertSessionHasErrors('status');

    expect(QccBatch::query()->count())->toBe(1);
});

test('a batch can end on the day it starts', function () {
    $this->actingAs(batchManager())
        ->post(route('admin.batches.store'), validBatchPayload(['starts_on' => '2026-03-01', 'ends_on' => '2026-03-01']))
        ->assertSessionHasNoErrors();

    expect(QccBatch::query()->count())->toBe(1);
});

test('super admin can update a batch and keep its own code', function () {
    $batch = QccBatch::factory()->create(['code' => 'KEEP-1']);

    $this->actingAs(batchManager())
        ->put(route('admin.batches.update', $batch), validBatchPayload([
            'code' => 'KEEP-1',
            'name' => 'Renamed',
            'status' => BatchStatus::Active->value,
        ]))
        ->assertSessionHasNoErrors();

    expect($batch->fresh())
        ->name->toBe('Renamed')
        ->status->toBe(BatchStatus::Active);
});

test('updating a batch cannot take the code of another batch', function () {
    QccBatch::factory()->create(['code' => 'OTHER-1']);
    $batch = QccBatch::factory()->create(['code' => 'MINE-1']);

    $this->actingAs(batchManager())
        ->put(route('admin.batches.update', $batch), validBatchPayload(['code' => 'OTHER-1']))
        ->assertSessionHasErrors('code');

    expect($batch->fresh()->code)->toBe('MINE-1');
});

test('an unused batch can be deleted', function () {
    $batch = QccBatch::factory()->create();

    $this->actingAs(batchManager())
        ->delete(route('admin.batches.destroy', $batch))
        ->assertRedirect();

    expect(QccBatch::query()->count())->toBe(0);
});

test('a batch used by a project cannot be deleted', function () {
    $batch = QccBatch::factory()->create();

    DB::table('projects')->insert([
        'batch_id' => $batch->id,
        'proposer_id' => User::factory()->create()->id,
        'title' => 'Project',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs(batchManager())
        ->delete(route('admin.batches.destroy', $batch))
        ->assertRedirect();

    expect(QccBatch::query()->whereKey($batch->id)->exists())->toBeTrue();
});
