<?php

use App\Models\AuditLog;
use App\Models\QccBatch;
use App\Models\QccBatchSchedule;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

test('admin sets batch reminder dates without generating unapproved dates', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::findOrCreate('batches.manage', 'web'));
    $batch = QccBatch::factory()->create();
    $url = route('admin.batches.schedules.store', $batch);

    $this->actingAs($admin)->get(route('admin.batches.schedules.index', $batch))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/batches/schedules')
            ->has('schedules', 0));

    $this->post($url, [
        'schedule_type' => 'BIWEEKLY', 'sequence_no' => 1, 'scheduled_on' => '2026-11-02',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $schedule = QccBatchSchedule::query()->sole();
    expect($schedule->batch_id)->toBe($batch->id)
        ->and($schedule->scheduled_on->toDateString())->toBe('2026-11-02')
        ->and(AuditLog::query()->where('event', 'batch_schedule.created')->exists())->toBeTrue();

    $this->post($url, [
        'schedule_type' => 'BIWEEKLY', 'sequence_no' => 1, 'scheduled_on' => '2026-11-03',
    ])->assertSessionHasErrors('sequence_no');

    $this->patch(route('admin.batches.schedules.update', [$batch, $schedule]), [
        'scheduled_on' => '2026-11-04',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($schedule->fresh()->scheduled_on->toDateString())->toBe('2026-11-04')
        ->and(AuditLog::query()->where('event', 'batch_schedule.updated')->exists())->toBeTrue();
});

test('batch schedule editing stays restricted to batch managers and parent batch', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::findOrCreate('batches.manage', 'web'));
    $outsider = User::factory()->create();
    $batch = QccBatch::factory()->create();
    $otherBatch = QccBatch::factory()->create();
    $schedule = QccBatchSchedule::factory()->create(['batch_id' => $batch->id]);

    $this->actingAs($outsider)->post(route('admin.batches.schedules.store', $batch), [
        'schedule_type' => 'MENTORING', 'sequence_no' => 1, 'scheduled_on' => '2026-11-02',
    ])->assertForbidden();

    $this->actingAs($admin)->patch(route('admin.batches.schedules.update', [$otherBatch, $schedule]), [
        'scheduled_on' => '2026-11-05',
    ])->assertNotFound();
});
