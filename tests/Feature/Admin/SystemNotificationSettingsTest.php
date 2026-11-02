<?php

use App\Models\SystemSetting;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

/** @return array<string, mixed> */
function notificationPolicyPayload(array $overrides = []): array
{
    return [
        'notifications_in_app' => true,
        'notifications_email' => false,
        'notifications_reminder_early_days' => 4,
        'notifications_reminder_final_days' => 2,
        'attachments_allowed_types' => ['pdf'],
        'attachments_storage_disk' => 'local',
        'attachments_max_size_mb' => 50,
        'attachments_max_files' => 5,
        'attachments_retention_days' => 365,
        'ai_enabled' => false,
        'ai_model' => null,
        ...$overrides,
    ];
}

test('admin saves reminder timing and sees the saved values', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo([
        Permission::findOrCreate('system-settings.manage', 'web'),
        Permission::findOrCreate('system-settings.view', 'web'),
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.settings.policies.update'), notificationPolicyPayload())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(SystemSetting::query()->where('key', 'notifications_reminder_early_days')->value('value'))->toBe('4')
        ->and(SystemSetting::query()->where('key', 'notifications_reminder_final_days')->value('value'))->toBe('2');

    $this->get(route('admin.settings.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('policies.notifications.reminderEarlyDays', 4)
            ->where('policies.notifications.reminderFinalDays', 2));
});

test('admin cannot save reminder timing in the wrong order', function () {
    $admin = User::factory()->create();
    $admin->givePermissionTo(Permission::findOrCreate('system-settings.manage', 'web'));

    $this->actingAs($admin)
        ->patch(route('admin.settings.policies.update'), notificationPolicyPayload([
            'notifications_reminder_early_days' => 1,
            'notifications_reminder_final_days' => 2,
        ]))
        ->assertSessionHasErrors('notifications_reminder_early_days');

    expect(SystemSetting::query()->where('key', 'notifications_reminder_early_days')->exists())->toBeFalse();
});
