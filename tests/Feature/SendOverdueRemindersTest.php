<?php

use App\Enums\ProposalStatus;
use App\Models\ActionItem;
use App\Models\Project;
use App\Models\ProjectRisk;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\Mime\Email;

function overdueReminderMember(Project $project, string $role = 'SIMer / Member'): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate($role, 'web'));
    $user->givePermissionTo(Permission::findOrCreate('projects.view', 'web'));

    DB::table('project_members')->insert([
        'project_id' => $project->id,
        'user_id' => $user->id,
        'role_in_project' => $role === 'Team Leader' ? 'LEADER' : 'MEMBER',
        'status' => 'ACTIVE',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $user;
}

function overdueReminderAction(Project $project, User $pic, array $attributes = []): ActionItem
{
    return ActionItem::query()->create([
        'project_id' => $project->id,
        'source_type' => 'PROJECT',
        'title' => 'Kirim hasil analisis',
        'pic_id' => $pic->id,
        'due_date' => '2026-10-20',
        'status' => 'OPEN',
        'created_by' => $pic->id,
        ...$attributes,
    ]);
}

function overdueReminderRisk(Project $project, User $pic, array $attributes = []): ProjectRisk
{
    return ProjectRisk::query()->create([
        'project_id' => $project->id,
        'type' => 'RISK',
        'title' => 'Data survei belum lengkap',
        'description' => 'Respons masih kurang.',
        'impact' => 'Analisis dapat terlambat.',
        'likelihood' => 'MEDIUM',
        'priority' => 'HIGH',
        'pic_id' => $pic->id,
        'due_date' => '2026-10-20',
        'status' => 'OPEN',
        'created_by' => $pic->id,
        ...$attributes,
    ]);
}

/**
 * @return list<Email>
 */
function overdueReminderSentEmails(): array
{
    $transport = app('mail.manager')->mailer('array')->getSymfonyTransport();

    return array_map(fn ($sent) => $sent->getOriginalMessage(), $transport->messages()->all());
}

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 22)->setTime(8, 0));
    $this->project = Project::factory()->status(ProposalStatus::Approved)->create();
    $this->leader = overdueReminderMember($this->project, 'Team Leader');
    $this->pic = overdueReminderMember($this->project);
});

it('sends overdue action and risk reminders only to their active authorized PICs', function () {
    $action = overdueReminderAction($this->project, $this->pic);
    $risk = overdueReminderRisk($this->project, $this->pic);

    $this->artisan('qcc:notifications:send-overdue')->assertSuccessful();

    expect($this->pic->notifications()->count())->toBe(2)
        ->and($this->leader->notifications()->count())->toBe(0)
        ->and(DB::table('overdue_reminder_deliveries')->count())->toBe(2);

    $notification = $this->pic->notifications()->orderBy('created_at')->first();
    expect($notification->data['decision'])->toBe('overdue')
        ->and($notification->data['project_id'])->toBe($this->project->id)
        ->and($notification->data['message'])->not->toContain('score');

    expect(DB::table('overdue_reminder_deliveries')->where('work_id', $action->id)->exists())->toBeTrue()
        ->and(DB::table('overdue_reminder_deliveries')->where('work_id', $risk->id)->exists())->toBeTrue();
});

it('deduplicates reruns on the same day and sends the next scheduled day', function () {
    overdueReminderAction($this->project, $this->pic);

    $this->artisan('qcc:notifications:send-overdue')->assertSuccessful();
    $this->artisan('qcc:notifications:send-overdue')->assertSuccessful();

    expect($this->pic->notifications()->count())->toBe(1);

    $this->travelTo(now()->addDay());
    $this->artisan('qcc:notifications:send-overdue')->assertSuccessful();

    expect($this->pic->notifications()->count())->toBe(2)
        ->and(DB::table('overdue_reminder_deliveries')->count())->toBe(2);
});

it('skips future, finished, inactive-PIC, inaccessible and unapproved project records', function () {
    overdueReminderAction($this->project, $this->pic, ['due_date' => '2026-10-22']);
    overdueReminderAction($this->project, $this->pic, ['status' => 'COMPLETED']);
    $inactivePic = overdueReminderMember($this->project);
    DB::table('users')->where('id', $inactivePic->id)->update(['is_active' => false]);
    overdueReminderAction($this->project, $inactivePic);

    $removedPic = overdueReminderMember($this->project);
    overdueReminderAction($this->project, $removedPic);
    DB::table('project_members')->where('project_id', $this->project->id)->where('user_id', $removedPic->id)->update([
        'status' => 'REMOVED',
        'left_at' => now(),
    ]);

    $unapprovedProject = Project::factory()->create();
    $otherPic = overdueReminderMember($unapprovedProject);
    overdueReminderAction($unapprovedProject, $otherPic);

    $this->artisan('qcc:notifications:send-overdue')->assertSuccessful();

    expect(User::query()->whereKey($this->pic->id)->first()->notifications()->count())->toBe(0)
        ->and($inactivePic->notifications()->count())->toBe(0)
        ->and($removedPic->notifications()->count())->toBe(0)
        ->and($otherPic->notifications()->count())->toBe(0)
        ->and(DB::table('overdue_reminder_deliveries')->count())->toBe(0);
});

it('honors the persisted in-app notification setting', function () {
    overdueReminderAction($this->project, $this->pic);
    SystemSetting::query()->create(['key' => 'notifications_in_app', 'value' => '0']);

    $this->artisan('qcc:notifications:send-overdue')->assertSuccessful();

    expect($this->pic->notifications()->count())->toBe(0)
        ->and(DB::table('overdue_reminder_deliveries')->count())->toBe(0);
});

it('sends H-3 and H-1 reminders once per item and reminder type', function () {
    $action = overdueReminderAction($this->project, $this->pic, ['due_date' => '2026-10-25']);
    $risk = overdueReminderRisk($this->project, $this->pic, ['due_date' => '2026-10-25']);

    $this->artisan('qcc:notifications:send-overdue')->assertSuccessful();
    $this->artisan('qcc:notifications:send-overdue')->assertSuccessful();

    expect($this->pic->notifications()->count())->toBe(2)
        ->and(DB::table('overdue_reminder_deliveries')->where('reminder_type', 'DUE_SOON_3')->count())->toBe(2);

    $actionNotice = $this->pic->notifications()->where('data->url', route('projects.risks.index', $this->project).'?tab=actions')->first();
    expect($actionNotice->data['title'])->toBe('Tindakan jatuh tempo 3 hari lagi')
        ->and($actionNotice->data['message'])->toContain('Kirim hasil analisis')
        ->and($actionNotice->data['message'])->toContain($this->project->title)
        ->and($actionNotice->data['message'])->toContain('25 Okt 2026')
        ->and($actionNotice->data['due_date'])->toBe('2026-10-25');

    $riskNotice = $this->pic->notifications()->where('data->url', route('projects.risks.index', $this->project).'?tab=risks')->first();
    expect($riskNotice->data['title'])->toBe('Risiko jatuh tempo 3 hari lagi')
        ->and($riskNotice->data['message'])->toContain('Data survei belum lengkap');

    $this->travelTo(now()->addDays(2));
    $this->artisan('qcc:notifications:send-overdue')->assertSuccessful();
    $this->artisan('qcc:notifications:send-overdue')->assertSuccessful();

    expect($this->pic->notifications()->count())->toBe(4)
        ->and(DB::table('overdue_reminder_deliveries')->where('reminder_type', 'DUE_SOON_1')->count())->toBe(2)
        ->and($this->pic->notifications()->where('data->title', 'Tindakan jatuh tempo besok')->count())->toBe(1)
        ->and($this->pic->notifications()->where('data->title', 'Risiko jatuh tempo besok')->count())->toBe(1);
});

it('skips due-soon reminders for finished work, missing due date or PIC, and inactive PIC', function () {
    overdueReminderAction($this->project, $this->pic, ['due_date' => '2026-10-25', 'status' => 'COMPLETED']);
    overdueReminderAction($this->project, $this->pic, ['due_date' => '2026-10-25', 'status' => 'CANCELLED']);
    overdueReminderRisk($this->project, $this->pic, ['due_date' => '2026-10-25', 'status' => 'RESOLVED']);
    overdueReminderRisk($this->project, $this->pic, ['due_date' => '2026-10-25', 'status' => 'CLOSED']);
    overdueReminderRisk($this->project, $this->pic, ['due_date' => null]);

    ProjectRisk::query()->create([
        'project_id' => $this->project->id,
        'type' => 'ISSUE',
        'title' => 'Tindakan belum memiliki PIC',
        'description' => 'Deskripsi isu.',
        'impact' => 'Dampak isu.',
        'likelihood' => 'MEDIUM',
        'priority' => 'HIGH',
        'pic_id' => null,
        'due_date' => '2026-10-25',
        'status' => 'OPEN',
        'created_by' => $this->leader->id,
    ]);

    $inactivePic = overdueReminderMember($this->project);
    $inactivePic->forceFill(['is_active' => false])->save();
    overdueReminderAction($this->project, $inactivePic, ['due_date' => '2026-10-25']);

    $this->artisan('qcc:notifications:send-overdue')->assertSuccessful();

    expect($this->pic->notifications()->count())->toBe(0)
        ->and($inactivePic->notifications()->count())->toBe(0)
        ->and(DB::table('overdue_reminder_deliveries')->count())->toBe(0);
});

it('sends a new reminder after the due date changes', function () {
    $action = overdueReminderAction($this->project, $this->pic, ['due_date' => '2026-10-25']);

    $this->artisan('qcc:notifications:send-overdue')->assertSuccessful();
    expect($this->pic->notifications()->count())->toBe(1);

    $action->update(['due_date' => '2026-10-26']);
    $this->travelTo(now()->addDay());
    $this->artisan('qcc:notifications:send-overdue')->assertSuccessful();

    expect($this->pic->notifications()->count())->toBe(2)
        ->and(DB::table('overdue_reminder_deliveries')->where('reminder_type', 'DUE_SOON_3')->count())->toBe(2)
        ->and(DB::table('overdue_reminder_deliveries')->where('work_id', $action->id)->orderBy('due_date')->pluck('due_date')->all())
        ->toBe(['2026-10-25', '2026-10-26']);
});

it('emails due-soon reminders when the persisted email setting is active', function () {
    overdueReminderAction($this->project, $this->pic, ['due_date' => '2026-10-25']);
    SystemSetting::query()->create(['key' => 'notifications_in_app', 'value' => '0']);
    SystemSetting::query()->create(['key' => 'notifications_email', 'value' => '1']);

    $this->artisan('qcc:notifications:send-overdue')->assertSuccessful();

    $emails = overdueReminderSentEmails();
    expect($emails)->toHaveCount(1)
        ->and((string) $emails[0]->getSubject())->toContain('Tindakan jatuh tempo 3 hari lagi')
        ->and((string) $emails[0]->getHtmlBody())->toContain('Tenggat')
        ->and((string) $emails[0]->getHtmlBody())->toContain('25 Okt 2026')
        ->and($this->pic->notifications()->count())->toBe(0);
});

it('schedules the reminder command daily at 08:00 in the application timezone', function () {
    $event = collect(Schedule::events())->first(fn ($event): bool => str_contains($event->command, 'qcc:notifications:send-overdue'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 8 * * *')
        ->and($event->timezone)->toBe('Asia/Jakarta');
});
