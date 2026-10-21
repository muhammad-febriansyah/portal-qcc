<?php

use App\Enums\BatchStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\QccBatch;
use App\Models\User;
use App\Services\AuditDescriber;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function auditViewer(): User
{
    $user = User::factory()->create(['name' => 'Rina Auditor']);
    $user->givePermissionTo(Permission::findOrCreate('audit.view', 'web'));

    return $user;
}

function adminWithManage(string ...$permissions): User
{
    $user = User::factory()->create(['name' => 'Admin Uji']);
    $user->assignRole(Role::findOrCreate('Super Admin', 'web'));

    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return $user;
}

test('guests are redirected to login', function () {
    $this->get(route('admin.audit.index'))->assertRedirect(route('login'));
});

test('users without audit.view cannot see the audit log', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.audit.index'))
        ->assertForbidden();
});

test('creating a batch is logged with who, what and the new values', function () {
    $admin = adminWithManage('batches.manage');

    $this->actingAs($admin)->post(route('admin.batches.store'), [
        'code' => 'BATCH-1',
        'name' => 'QCC 2026',
        'starts_on' => '2026-01-05',
        'ends_on' => '2026-06-30',
        'status' => BatchStatus::Draft->value,
    ]);

    $log = AuditLog::query()->where('event', 'batch.created')->firstOrFail();

    expect($log->actor_id)->toBe($admin->id)
        ->and($log->subject_label)->toBe('BATCH-1 - QCC 2026')
        ->and($log->after_json)->toMatchArray(['code' => 'BATCH-1', 'name' => 'QCC 2026'])
        ->and($log->trace_id)->toHaveLength(36);
});

test('an update logs only the fields that changed, before and after', function () {
    $admin = adminWithManage('batches.manage');
    $batch = QccBatch::factory()->create(['name' => 'Lama', 'status' => BatchStatus::Draft]);

    $this->actingAs($admin)->put(route('admin.batches.update', $batch), [
        'code' => $batch->code,
        'name' => 'Baru',
        'starts_on' => $batch->starts_on->format('Y-m-d'),
        'ends_on' => $batch->ends_on->format('Y-m-d'),
        'status' => BatchStatus::Draft->value,
    ]);

    $log = AuditLog::query()->where('event', 'batch.updated')->firstOrFail();

    expect($log->before_json)->toBe(['name' => 'Lama'])
        ->and($log->after_json)->toBe(['name' => 'Baru']);
});

test('saving without any change writes no log entry', function () {
    $batch = QccBatch::factory()->create();
    AuditLog::query()->delete();

    $batch->save();

    expect(AuditLog::query()->count())->toBe(0);
});

test('deleting a batch is logged with its last values', function () {
    $admin = adminWithManage('batches.manage');
    $batch = QccBatch::factory()->create(['code' => 'GONE-1']);

    $this->actingAs($admin)->delete(route('admin.batches.destroy', $batch));

    $log = AuditLog::query()->where('event', 'batch.deleted')->firstOrFail();

    expect($log->before_json['code'])->toBe('GONE-1')
        ->and($log->after_json)->toBeNull();
});

test('passwords and tokens never reach the log', function () {
    $admin = adminWithManage('users.manage');
    $target = User::factory()->create();

    $this->actingAs($admin)->put(route('admin.users.password.update', $target));
    $generated = session('inertia.flash_data.credential.password');

    $everything = AuditLog::query()->get()->toJson();

    expect($generated)->toBeString()
        ->and($everything)->not->toContain($generated)
        ->and($everything)->not->toContain('remember_token')
        ->and($everything)->not->toContain('"password"');
    expect(AuditLog::query()->where('event', 'user.password_reset')->exists())->toBeTrue();
});

test('user creation records the new account and its role without secrets', function () {
    $admin = adminWithManage('users.manage');
    Role::findOrCreate('Mentor', 'web');
    $organization = Organization::factory()->create(['is_active' => true]);

    $this->actingAs($admin)->post(route('admin.users.store'), [
        'name' => 'Budi Santoso',
        'sim_id' => 'SIM-1',
        'email' => 'budi@example.com',
        'organization_id' => $organization->id,
        'role' => 'Mentor',
    ]);

    $created = AuditLog::query()->where('event', 'user.created')->where('subject_label', 'like', 'Budi Santoso%')->firstOrFail();
    $role = AuditLog::query()->where('event', 'user.role_changed')->firstOrFail();

    expect($created->after_json)->toMatchArray(['email' => 'budi@example.com'])
        ->and($created->after_json)->not->toHaveKey('password')
        ->and($role->after_json)->toBe(['role' => 'Mentor']);
});

test('changing a role is logged and an unchanged role is not', function () {
    $admin = adminWithManage('users.manage');
    Role::findOrCreate('Mentor', 'web');
    Role::findOrCreate('Team Leader', 'web');
    $target = User::factory()->create(['organization_id' => Organization::factory()->create()->id]);
    $target->assignRole('Mentor');

    $payload = [
        'name' => $target->name,
        'sim_id' => 'SIM-9',
        'email' => $target->email,
        'organization_id' => $target->organization_id,
        'role' => 'Mentor',
    ];

    $this->actingAs($admin)->put(route('admin.users.update', $target), $payload);
    expect(AuditLog::query()->where('event', 'user.role_changed')->count())->toBe(0);

    $this->put(route('admin.users.update', $target), [...$payload, 'role' => 'Team Leader']);
    $log = AuditLog::query()->where('event', 'user.role_changed')->firstOrFail();

    expect($log->before_json)->toBe(['role' => 'Mentor'])
        ->and($log->after_json)->toBe(['role' => 'Team Leader']);
});

test('activating and deactivating get their own event names', function () {
    $admin = adminWithManage('users.manage');
    $target = User::factory()->create();

    $this->actingAs($admin);
    $this->patch(route('admin.users.status.update', $target), ['is_active' => false]);
    $this->patch(route('admin.users.status.update', $target), ['is_active' => true]);

    expect(AuditLog::query()->where('event', 'user.deactivated')->count())->toBe(1)
        ->and(AuditLog::query()->where('event', 'user.activated')->count())->toBe(1);
});

test('login, logout and a failed login are logged', function () {
    $user = User::factory()->create(['email' => 'rina@example.com']);

    $this->post(route('login.store'), ['email' => 'rina@example.com', 'password' => 'salah']);
    $failed = AuditLog::query()->where('event', 'auth.login_failed')->firstOrFail();
    expect($failed->subject_label)->toBe('rina@example.com');

    $this->post(route('login.store'), ['email' => 'rina@example.com', 'password' => 'password']);
    $login = AuditLog::query()->where('event', 'auth.login')->firstOrFail();
    expect($login->actor_id)->toBe($user->id);

    $this->post(route('logout'));
    expect(AuditLog::query()->where('event', 'auth.logout')->where('actor_id', $user->id)->exists())->toBeTrue();
});

test('audit entries cannot be edited or deleted', function () {
    $log = AuditLog::factory()->create();

    expect(fn () => $log->update(['event' => 'x']))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class);
    expect(AuditLog::query()->count())->toBe(1);
});

test('entries are described in plain Indonesian', function () {
    $actor = User::factory()->create(['name' => 'Rina Auditor']);

    $login = AuditLog::factory()->create(['actor_id' => $actor->id, 'event' => 'auth.login']);
    $created = AuditLog::factory()->create([
        'actor_id' => $actor->id,
        'event' => 'user.created',
        'subject_label' => 'Budi (budi@example.com)',
    ]);
    $role = AuditLog::factory()->create([
        'actor_id' => $actor->id,
        'event' => 'user.role_changed',
        'subject_label' => 'Budi',
        'before_json' => ['role' => 'Mentor'],
        'after_json' => ['role' => 'Team Leader'],
    ]);
    $update = AuditLog::factory()->create([
        'actor_id' => $actor->id,
        'event' => 'batch.updated',
        'subject_label' => 'B-1 - QCC',
        'before_json' => ['status' => 'draft'],
        'after_json' => ['status' => 'active'],
    ]);
    $system = AuditLog::factory()->create(['actor_id' => null, 'event' => 'auth.logout']);

    $describer = new AuditDescriber;

    expect($describer->describe($login->load('actor')))->toBe('Rina Auditor masuk ke Portal QCC')
        ->and($describer->describe($created->load('actor')))->toBe('Rina Auditor menambahkan anggota Budi (budi@example.com)')
        ->and($describer->describe($role->load('actor')))->toBe('Rina Auditor mengubah role Budi dari Mentor menjadi Team Leader')
        ->and($describer->describe($update->load('actor')))->toBe('Rina Auditor mengubah batch B-1 - QCC (status)')
        ->and($describer->describe($system->load('actor')))->toBe('Sistem keluar dari Portal QCC')
        ->and($describer->changes($update))->toBe([['label' => 'Status', 'before' => 'Draf', 'after' => 'Aktif']]);
});

test('changes show organization names instead of ids', function () {
    $organization = Organization::factory()->create(['name' => 'DIV IT']);
    $log = AuditLog::factory()->create([
        'event' => 'user.updated',
        'before_json' => ['organization_id' => null, 'is_active' => 1],
        'after_json' => ['organization_id' => $organization->id, 'is_active' => 0],
    ]);

    $changes = AuditDescriber::forLogs(collect([$log]))->changes($log);

    expect($changes)->toBe([
        ['label' => 'Organisasi', 'before' => '—', 'after' => 'DIV IT'],
        ['label' => 'Status', 'before' => 'Aktif', 'after' => 'Nonaktif'],
    ]);
});

test('the page lists readable entries newest first and loads actors without extra queries', function () {
    $actor = User::factory()->create(['name' => 'Rina Auditor']);
    $viewer = auditViewer();
    AuditLog::query()->delete();
    AuditLog::factory()->count(12)->create(['actor_id' => $actor->id, 'event' => 'auth.login']);

    $this->actingAs($viewer)
        ->get(route('admin.audit.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/audit/index')
            ->has('logs.data', 10)
            ->where('logs.total', 12)
            ->where('logs.data.0.description', 'Rina Auditor masuk ke Portal QCC')
            ->where('logs.data.0.actor_name', 'Rina Auditor')
            ->has('moduleOptions', count(AuditLog::MODULE_LABELS)));
});

test('the page can be filtered by module, search and date range', function () {
    $actor = User::factory()->create(['name' => 'Budi Pelaku']);
    $viewer = auditViewer();
    AuditLog::query()->delete();
    AuditLog::factory()->create(['actor_id' => $actor->id, 'event' => 'auth.login', 'occurred_at' => '2026-03-10 09:00:00']);
    AuditLog::factory()->create(['actor_id' => $actor->id, 'event' => 'batch.created', 'subject_label' => 'B-7 - Tujuh', 'occurred_at' => '2026-04-20 10:00:00']);

    $this->actingAs($viewer);

    $this->get(route('admin.audit.index', ['module' => 'batch']))
        ->assertInertia(fn (Assert $page) => $page->has('logs.data', 1)->where('logs.data.0.event_label', 'Batch ditambahkan'));

    $this->get(route('admin.audit.index', ['search' => 'Tujuh']))
        ->assertInertia(fn (Assert $page) => $page->has('logs.data', 1));

    $this->get(route('admin.audit.index', ['search' => 'Budi Pelaku']))
        ->assertInertia(fn (Assert $page) => $page->has('logs.data', 2));

    $this->get(route('admin.audit.index', ['date_from' => '2026-03-01', 'date_to' => '2026-03-31']))
        ->assertInertia(fn (Assert $page) => $page->has('logs.data', 1)->where('logs.data.0.event_label', 'Login berhasil'));

    $this->get(route('admin.audit.index', ['date_from' => '2026-05-01', 'date_to' => '2026-04-01']))
        ->assertSessionHasErrors('date_to');
    $this->get(route('admin.audit.index', ['module' => 'hacker']))
        ->assertSessionHasErrors('module');
});

test('the page can be sorted oldest first', function () {
    $viewer = auditViewer();
    AuditLog::query()->delete();
    AuditLog::factory()->create(['subject_label' => 'lama', 'occurred_at' => '2026-01-01 08:00:00']);
    AuditLog::factory()->create(['subject_label' => 'baru', 'occurred_at' => '2026-02-01 08:00:00']);

    $this->actingAs($viewer)
        ->get(route('admin.audit.index', ['direction' => 'asc']))
        ->assertInertia(fn (Assert $page) => $page->where('logs.data.0.subject_label', 'lama'));
});
