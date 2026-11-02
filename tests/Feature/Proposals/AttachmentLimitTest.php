<?php

use App\Enums\ProposalStatus;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\QccBatch;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function attachmentLimitOwner(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('SIMer / Member', 'web'));
    $user->givePermissionTo([
        Permission::findOrCreate('projects.view', 'web'),
        Permission::findOrCreate('projects.update', 'web'),
    ]);

    return $user;
}

function attachmentLimitDraft(User $owner): Project
{
    return Project::factory()->create([
        'batch_id' => QccBatch::factory()->active()->create()->id,
        'proposer_id' => $owner->id,
        'proposal_status' => ProposalStatus::Draft,
    ]);
}

/** The limits come from system settings, which the middleware loads into config on every request. */
function attachmentLimitSetting(string $key, string $value): void
{
    SystemSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    Cache::forget('system-settings.global');
}

function attachmentLimitPdf(string $name, int $kilobytes): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n".str_repeat('a', $kilobytes * 1024)."\n%%EOF");
}

beforeEach(function () {
    Storage::fake('local');
});

it('refuses more files in one upload than the system setting allows', function () {
    attachmentLimitSetting('attachments_max_files', '2');
    $owner = attachmentLimitOwner();
    $project = attachmentLimitDraft($owner);

    $this->actingAs($owner)
        ->post(route('proposals.attachments.store', $project), [
            'files' => [attachmentLimitPdf('a.pdf', 1), attachmentLimitPdf('b.pdf', 1), attachmentLimitPdf('c.pdf', 1)],
        ])
        ->assertSessionHasErrors(['files' => 'Maksimal 2 lampiran per unggahan.']);

    expect(ProjectAttachment::query()->count())->toBe(0);
});

it('refuses a file larger than the size limit from the system setting', function () {
    attachmentLimitSetting('attachments_max_size_mb', '1');
    $owner = attachmentLimitOwner();
    $project = attachmentLimitDraft($owner);

    $this->actingAs($owner)
        ->post(route('proposals.attachments.store', $project), [
            'files' => [attachmentLimitPdf('besar.pdf', 2048)],
        ])
        ->assertSessionHasErrors(['files.0' => 'Ukuran setiap lampiran maksimal 1 MB.']);

    expect(ProjectAttachment::query()->count())->toBe(0);
});

it('hands the wizard the limits from the system settings', function () {
    attachmentLimitSetting('attachments_max_files', '3');
    attachmentLimitSetting('attachments_max_size_mb', '7');
    $owner = attachmentLimitOwner();
    $project = attachmentLimitDraft($owner);

    $this->actingAs($owner)
        ->get(route('proposals.edit', $project))
        ->assertInertia(fn ($page) => $page
            ->where('attachmentLimit.maxFiles', 3)
            ->where('attachmentLimit.maxSizeMb', 7));
});
