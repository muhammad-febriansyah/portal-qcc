<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

test('entries older than the retention window are archived and removed, recent ones stay', function () {
    Storage::fake('local');

    $old = AuditLog::factory()->create(['subject_label' => 'Lama', 'occurred_at' => now()->subDays(31)]);
    $recent = AuditLog::factory()->create(['subject_label' => 'Baru', 'occurred_at' => now()->subDays(29)]);

    $this->artisan('qcc:audit:prune')->assertSuccessful();

    expect(AuditLog::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->whereKey($recent->id)->exists())->toBeTrue();

    $archives = Storage::disk('local')->files('audit-archive');

    expect($archives)->toHaveCount(1);

    $archived = json_decode(trim(Storage::disk('local')->get($archives[0])), true);

    expect($archived['id'])->toBe($old->id)
        ->and($archived['subject_label'])->toBe('Lama');
});

test('a run with nothing to archive writes no file and no audit entry', function () {
    Storage::fake('local');

    AuditLog::factory()->create(['occurred_at' => now()->subDay()]);
    $countBefore = AuditLog::query()->count();

    $this->artisan('qcc:audit:prune')->assertSuccessful();

    expect(Storage::disk('local')->allFiles('audit-archive'))->toBeEmpty()
        ->and(AuditLog::query()->count())->toBe($countBefore)
        ->and(AuditLog::query()->where('event', 'audit.pruned')->exists())->toBeFalse();
});

test('the days option overrides the configured retention', function () {
    Storage::fake('local');

    config(['qcc.audit.retention_days' => 30]);
    $tenDaysOld = AuditLog::factory()->create(['occurred_at' => now()->subDays(10)]);

    $this->artisan('qcc:audit:prune', ['--days' => 7])->assertSuccessful();

    expect(AuditLog::query()->whereKey($tenDaysOld->id)->exists())->toBeFalse();
});

test('a successful run records how many entries were archived and where', function () {
    Storage::fake('local');

    AuditLog::factory()->count(2)->create(['occurred_at' => now()->subDays(40)]);

    $this->artisan('qcc:audit:prune')->assertSuccessful();

    $pruned = AuditLog::query()->where('event', 'audit.pruned')->firstOrFail();

    expect($pruned->after_json)->toMatchArray(['archived' => 2])
        ->and($pruned->after_json['file'])->toStartWith('audit-archive/')
        ->and($pruned->reason)->toBe('Retensi 30 hari');
});

test('a retention below one day is refused', function () {
    Storage::fake('local');

    AuditLog::factory()->create(['occurred_at' => now()->subYears(5)]);

    $this->artisan('qcc:audit:prune', ['--days' => 0])->assertFailed();

    expect(AuditLog::query()->count())->toBe(1);
});

test('the audit page shows the configured retention in an alert', function () {
    config(['qcc.audit.retention_days' => 45]);

    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Permission::findOrCreate('audit.view', 'web'));

    $this->actingAs($viewer)
        ->get(route('admin.audit.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/audit/index')
            ->where('retentionDays', 45));
});
