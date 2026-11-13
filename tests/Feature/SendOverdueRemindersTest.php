<?php

use App\Enums\ProposalStatus;
use App\Models\ActionItem;
use App\Models\BiweeklyReport;
use App\Models\MentoringReport;
use App\Models\Project;
use App\Models\ProjectMentor;
use App\Models\ProjectRisk;
use App\Models\QccBatchSchedule;
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

it('reminds report creators about upcoming bi-weekly and mentoring deadlines with deduplication', function () {
    $mentor = overdueReminderMember($this->project, 'Mentor');
    $assignment = ProjectMentor::query()->create([
        'project_id' => $this->project->id,
        'mentor_id' => $mentor->id,
        'assigned_by' => $this->leader->id,
        'status' => 'ACTIVE',
        'assigned_at' => now(),
    ]);
    $biweekly = BiweeklyReport::query()->create([
        'project_id' => $this->project->id,
        'period_no' => 1,
        'starts_on' => '2026-10-12',
        'ends_on' => '2026-10-25',
        'status' => 'DRAFT',
        'created_by' => $this->leader->id,
    ]);
    $mentoring = MentoringReport::query()->create([
        'project_id' => $this->project->id,
        'project_mentor_id' => $assignment->id,
        'session_at' => '2026-10-25 10:00:00',
        'topic' => 'Pemetaan proses',
        'status' => 'DRAFT',
        'created_by' => $this->leader->id,
    ]);

    $this->artisan('qcc:notifications:send-overdue')->assertSuccessful();
    $this->artisan('qcc:notifications:send-overdue')->assertSuccessful();

    expect($this->leader->notifications()->count())->toBe(2)
        ->and($mentor->notifications()->count())->toBe(0)
        ->and(DB::table('overdue_reminder_deliveries')->whereIn('work_id', [$biweekly->id, $mentoring->id])->count())->toBe(2);

    $this->travelTo(now()->addDays(2));
    $this->artisan('qcc:notifications:send-overdue')->assertSuccessful();

    expect($this->leader->notifications()->count())->toBe(4)
        ->and(DB::table('overdue_reminder_deliveries')->where('reminder_type', 'DUE_SOON_1')->whereIn('work_id', [$biweekly->id, $mentoring->id])->count())->toBe(2);
});

it('reminds project leaders and assigned mentors from admin batch dates before any report exists', function () {
    $mentor = overdueReminderMember($this->project, 'Mentor');
    ProjectMentor::query()->create([
        'project_id' => $this->project->id,
        'mentor_id' => $mentor->id,
        'assigned_by' => $this->leader->id,
        'status' => 'ACTIVE',
        'assigned_at' => now(),
    ]);
    foreach (['BIWEEKLY', 'MENTORING'] as $type) {
        QccBatchSchedule::factory()->create([
            'batch_id' => $this->project->batch_id,
            'schedule_type' => $type,
            'sequence_no' => 1,
            'scheduled_on' => '2026-10-25',
        ]);
    }

    $this->artisan('qcc:notifications:send-overdue')->assertSuccessful();
    $this->artisan('qcc:notifications:send-overdue')->assertSuccessful();

    expect($this->leader->notifications()->count())->toBe(2)
        ->and($mentor->notifications()->count())->toBe(1)
        ->and(DB::table('overdue_reminder_deliveries')->where('work_type', 'batch_biweekly_1')->count())->toBe(1)
        ->and(DB::table('overdue_reminder_deliveries')->where('work_type', 'batch_mentoring_1')->count())->toBe(2);
});

it('uses report reminders when a report already exists for an admin schedule', function () {
    QccBatchSchedule::factory()->create([
        'batch_id' => $this->project->batch_id,
        'schedule_type' => 'BIWEEKLY',
        'sequence_no' => 1,
        'scheduled_on' => '2026-10-25',
    ]);
    BiweeklyReport::query()->create([
        'project_id' => $this->project->id,
        'period_no' => 1,
        'starts_on' => '2026-10-12',
        'ends_on' => '2026-10-25',
        'status' => 'DRAFT',
        'created_by' => $this->leader->id,
    ]);

    $this->artisan('qcc:notifications:send-overdue')->assertSuccessful();

    expect($this->leader->notifications()->count())->toBe(1)
        ->and(DB::table('overdue_reminder_deliveries')->where('work_type', 'batch_biweekly_1')->count())->toBe(0);
});

it('keeps batch reminders separate for two projects led by the same person', function () {
    $secondProject = Project::factory()->status(ProposalStatus::Approved)->create([
        'batch_id' => $this->project->batch_id,
    ]);
    DB::table('project_members')->insert([
        'project_id' => $secondProject->id,
        'user_id' => $this->leader->id,
        'role_in_project' => 'LEADER',
        'status' => 'ACTIVE',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    QccBatchSchedule::factory()->create([
        'batch_id' => $this->project->batch_id,
        'schedule_type' => 'BIWEEKLY',
        'sequence_no' => 1,
        'scheduled_on' => '2026-10-25',
    ]);

    $this->artisan('qcc:notifications:send-overdue')->assertSuccessful();

    expect($this->leader->notifications()->count())->toBe(2)
        ->and(DB::table('overdue_reminder_deliveries')
            ->where('work_type', 'batch_biweekly_1')
            ->distinct()->count('work_id'))->toBe(2);
});

it('uses configured reminder days and reminds a bi-weekly report requiring revision', function () {
    SystemSetting::query()->create(['key' => 'notifications_reminder_early_days', 'value' => '4']);
    SystemSetting::query()->create(['key' => 'notifications_reminder_final_days', 'value' => '2']);
    BiweeklyReport::query()->create([
        'project_id' => $this->project->id,
        'period_no' => 1,
        'starts_on' => '2026-10-13',
        'ends_on' => '2026-10-26',
        'status' => 'REVISION_REQUIRED',
        'created_by' => $this->leader->id,
    ]);

    $this->artisan('qcc:notifications:send-overdue')->assertSuccessful();
    expect(DB::table('overdue_reminder_deliveries')->where('reminder_type', 'DUE_SOON_4')->count())->toBe(1);

    $this->travelTo(now()->addDays(2));
    $this->artisan('qcc:notifications:send-overdue')->assertSuccessful();
    expect(DB::table('overdue_reminder_deliveries')->where('reminder_type', 'DUE_SOON_2')->count())->toBe(1)
        ->and($this->leader->notifications()->count())->toBe(2);
});

it('does not remind for submitted reports or reports whose creator lost project access', function () {
    $submitted = BiweeklyReport::query()->create([
        'project_id' => $this->project->id,
        'period_no' => 2,
        'starts_on' => '2026-10-12',
        'ends_on' => '2026-10-25',
        'status' => 'SUBMITTED',
        'created_by' => $this->leader->id,
    ]);
    $removed = overdueReminderMember($this->project, 'Team Leader');
    $report = BiweeklyReport::query()->create([
        'project_id' => $this->project->id,
        'period_no' => 3,
        'starts_on' => '2026-10-12',
        'ends_on' => '2026-10-25',
        'status' => 'DRAFT',
        'created_by' => $removed->id,
    ]);
    DB::table('project_members')->where('project_id', $this->project->id)->where('user_id', $removed->id)->update([
        'status' => 'REMOVED',
        'left_at' => now(),
    ]);

    $this->artisan('qcc:notifications:send-overdue')->assertSuccessful();

    expect($this->leader->notifications()->count())->toBe(0)
        ->and($removed->notifications()->count())->toBe(0)
        ->and(DB::table('overdue_reminder_deliveries')->whereIn('work_id', [$submitted->id, $report->id])->count())->toBe(0);
});

it('does not remind a report creator without project view permission', function () {
    $this->pic->revokePermissionTo(Permission::findOrCreate('projects.view', 'web'));
    BiweeklyReport::query()->create([
        'project_id' => $this->project->id,
        'period_no' => 4,
        'starts_on' => '2026-10-12',
        'ends_on' => '2026-10-25',
        'status' => 'DRAFT',
        'created_by' => $this->pic->id,
    ]);

    $this->artisan('qcc:notifications:send-overdue')->assertSuccessful();

    expect($this->pic->notifications()->count())->toBe(0)
        ->and(DB::table('overdue_reminder_deliveries')->count())->toBe(0);
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

    $actionsUrl = route('projects.risks.index', ['project' => $this->project, 'tab' => 'actions'], false);
    $risksUrl = route('projects.risks.index', ['project' => $this->project, 'tab' => 'risks'], false);
    $actionNotice = $this->pic->notifications()->where('data->url', $actionsUrl)->first();
    expect($actionNotice->data['title'])->toBe('Tindakan jatuh tempo 3 hari lagi')
        ->and($actionNotice->data['message'])->toContain('Kirim hasil analisis')
        ->and($actionNotice->data['message'])->toContain($this->project->title)
        ->and($actionNotice->data['message'])->toContain('25 Okt 2026')
        ->and($actionNotice->data['due_date'])->toBe('2026-10-25');

    $riskNotice = $this->pic->notifications()->where('data->url', $risksUrl)->first();
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

it('opens due-soon and overdue reminders on the matching actions and risks tabs', function () {
    overdueReminderAction($this->project, $this->pic, ['due_date' => '2026-10-25']);
    overdueReminderRisk($this->project, $this->pic, ['due_date' => '2026-10-25']);
    overdueReminderAction($this->project, $this->pic, ['due_date' => '2026-10-21']);
    overdueReminderRisk($this->project, $this->pic, ['due_date' => '2026-10-21']);

    $this->artisan('qcc:notifications:send-overdue')->assertSuccessful();

    $expectedRedirects = [
        ['Tindakan jatuh tempo 3 hari lagi', 'actions'],
        ['Risiko jatuh tempo 3 hari lagi', 'risks'],
        ['Tindakan melewati tenggat', 'actions'],
        ['Risiko melewati tenggat', 'risks'],
    ];

    foreach ($expectedRedirects as [$title, $tab]) {
        $notification = $this->pic->notifications()->where('data->title', $title)->firstOrFail();
        $expectedUrl = route('projects.risks.index', ['project' => $this->project, 'tab' => $tab]);

        $this->actingAs($this->pic)->get(route('notifications.open', $notification))->assertRedirect($expectedUrl);
    }
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
    $absoluteActionsUrl = route('projects.risks.index', ['project' => $this->project, 'tab' => 'actions']);
    expect($emails)->toHaveCount(1)
        ->and((string) $emails[0]->getSubject())->toContain('Tindakan jatuh tempo 3 hari lagi')
        ->and((string) $emails[0]->getHtmlBody())->toContain('Tenggat')
        ->and((string) $emails[0]->getHtmlBody())->toContain('25 Okt 2026')
        ->and((string) $emails[0]->getHtmlBody())->toContain($absoluteActionsUrl)
        ->and($this->pic->notifications()->count())->toBe(0);
});

it('schedules the reminder command daily at 08:00 in the application timezone', function () {
    $event = collect(Schedule::events())->first(fn ($event): bool => str_contains($event->command, 'qcc:notifications:send-overdue'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 8 * * *')
        ->and($event->timezone)->toBe('Asia/Jakarta');
});
