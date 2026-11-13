<?php

namespace App\Console\Commands;

use App\Enums\ProposalStatus;
use App\Models\ActionItem;
use App\Models\BiweeklyReport;
use App\Models\MentoringReport;
use App\Models\Project;
use App\Models\ProjectRisk;
use App\Models\QccBatchSchedule;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\ProjectActivityNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[Signature('qcc:notifications:send-overdue')]
#[Description('Kirim pengingat tenggat tindakan dan risiko proyek')]
class SendOverdueReminders extends Command
{
    public function handle(): int
    {
        $this->applyNotificationSettings();

        if (! ProjectActivityNotification::enabled()) {
            $this->info('Notifikasi in-app dan email dinonaktifkan.');

            return self::SUCCESS;
        }

        $today = Carbon::today(config('app.timezone'));
        $sent = 0;

        foreach ($this->reminderDays() as $daysAhead) {
            $sent += $this->sendDueSoonActions($today, $daysAhead);
            $sent += $this->sendDueSoonRisks($today, $daysAhead);
            $sent += $this->sendDueSoonBiweeklyReports($today, $daysAhead);
            $sent += $this->sendDueSoonMentoringReports($today, $daysAhead);
            $sent += $this->sendBatchScheduleReminders($today, $daysAhead);
        }

        $sent += $this->sendOverdueActions($today)
            + $this->sendOverdueRisks($today)
            + $this->sendOverdueBiweeklyReports($today)
            + $this->sendOverdueMentoringReports($today)
            + $this->sendBatchScheduleReminders($today, null);

        $this->info("Pengingat tenggat terkirim: {$sent}.");

        return self::SUCCESS;
    }

    private function applyNotificationSettings(): void
    {
        $settings = SystemSetting::query()
            ->whereIn('key', ['notifications_in_app', 'notifications_email', 'notifications_reminder_early_days', 'notifications_reminder_final_days'])
            ->pluck('value', 'key');

        config([
            'app.notifications.channels.in_app' => filter_var(
                $settings->get('notifications_in_app', (string) config('app.notifications.channels.in_app', true)),
                FILTER_VALIDATE_BOOLEAN,
            ),
            'app.notifications.channels.email' => filter_var(
                $settings->get('notifications_email', (string) config('app.notifications.channels.email', false)),
                FILTER_VALIDATE_BOOLEAN,
            ),
            'qcc.notifications.reminder_early_days' => (int) $settings->get('notifications_reminder_early_days', '3'),
            'qcc.notifications.reminder_final_days' => (int) $settings->get('notifications_reminder_final_days', '1'),
        ]);
    }

    /** @return list<int> */
    private function reminderDays(): array
    {
        $early = max(1, min(30, (int) config('qcc.notifications.reminder_early_days', 3)));
        $final = max(1, min(30, (int) config('qcc.notifications.reminder_final_days', 1)));

        return array_values(array_unique([$early, $final]));
    }

    private function sendDueSoonActions(Carbon $today, int $daysAhead): int
    {
        $dueDate = $today->copy()->addDays($daysAhead);
        $sent = 0;

        ActionItem::query()
            ->where('due_date', $dueDate->toDateString())
            ->whereNotIn('status', ['COMPLETED', 'CANCELLED'])
            ->whereHas('project', fn (Builder $project): Builder => $project->where('proposal_status', ProposalStatus::Approved))
            ->with(['pic:id,name,email,is_active', 'project:id,title'])
            ->chunkById(100, function ($items) use ($today, $daysAhead, $dueDate, &$sent): void {
                foreach ($items as $item) {
                    if ($item->pic === null || ! $item->pic->is_active || $item->project === null) {
                        continue;
                    }

                    $when = $daysAhead === 1 ? 'besok' : "{$daysAhead} hari lagi";
                    $sent += (int) $this->deliver(
                        $item->pic,
                        $item->project,
                        'action_item',
                        $item->id,
                        $item->due_date->toDateString(),
                        $today,
                        "Tindakan jatuh tempo {$when}",
                        "Tindakan {$item->title} pada proyek {$item->project->title} jatuh tempo pada {$this->formatDueDate($dueDate)}.",
                        route('projects.risks.index', ['project' => $item->project, 'tab' => 'actions'], false),
                        'DUE_SOON_'.$daysAhead,
                        'DUE_SOON',
                        $dueDate->toDateString(),
                    );
                }
            });

        return $sent;
    }

    private function sendDueSoonRisks(Carbon $today, int $daysAhead): int
    {
        $dueDate = $today->copy()->addDays($daysAhead);
        $sent = 0;

        ProjectRisk::query()
            ->whereDate('due_date', $dueDate->toDateString())
            ->whereNotIn('status', ['RESOLVED', 'CLOSED'])
            ->whereHas('project', fn (Builder $project): Builder => $project->where('proposal_status', ProposalStatus::Approved))
            ->with(['pic:id,name,email,is_active', 'project:id,title'])
            ->chunkById(100, function ($risks) use ($today, $daysAhead, $dueDate, &$sent): void {
                foreach ($risks as $risk) {
                    if ($risk->pic === null || ! $risk->pic->is_active || $risk->project === null) {
                        continue;
                    }

                    $when = $daysAhead === 1 ? 'besok' : "{$daysAhead} hari lagi";
                    $kind = $risk->type->label();
                    $sent += (int) $this->deliver(
                        $risk->pic,
                        $risk->project,
                        'project_risk',
                        $risk->id,
                        $risk->due_date->toDateString(),
                        $today,
                        "{$kind} jatuh tempo {$when}",
                        "{$kind} {$risk->title} pada proyek {$risk->project->title} jatuh tempo pada {$this->formatDueDate($dueDate)}.",
                        route('projects.risks.index', ['project' => $risk->project, 'tab' => 'risks'], false),
                        'DUE_SOON_'.$daysAhead,
                        'DUE_SOON',
                        $dueDate->toDateString(),
                    );
                }
            });

        return $sent;
    }

    private function sendOverdueActions(Carbon $today): int
    {
        $sent = 0;

        ActionItem::query()
            ->where('due_date', '<', $today->toDateString())
            ->whereNotIn('status', ['COMPLETED', 'CANCELLED'])
            ->whereHas('project', fn (Builder $project): Builder => $project->where('proposal_status', ProposalStatus::Approved))
            ->with(['pic:id,name,email,is_active', 'project:id,title'])
            ->chunkById(100, function ($items) use ($today, &$sent): void {
                foreach ($items as $item) {
                    if ($item->pic === null || ! $item->pic->is_active || $item->project === null) {
                        continue;
                    }

                    $sent += (int) $this->deliver(
                        $item->pic,
                        $item->project,
                        'action_item',
                        $item->id,
                        $item->due_date->toDateString(),
                        $today,
                        'Tindakan melewati tenggat',
                        "Tindakan {$item->title} pada proyek {$item->project->title} belum selesai dan melewati tenggat.",
                        route('projects.risks.index', ['project' => $item->project, 'tab' => 'actions'], false),
                        'OVERDUE',
                        'overdue',
                        $item->due_date->toDateString(),
                    );
                }
            });

        return $sent;
    }

    private function sendOverdueRisks(Carbon $today): int
    {
        $sent = 0;

        ProjectRisk::query()
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today->toDateString())
            ->whereNotIn('status', ['RESOLVED', 'CLOSED'])
            ->whereHas('project', fn (Builder $project): Builder => $project->where('proposal_status', ProposalStatus::Approved))
            ->with(['pic:id,name,email,is_active', 'project:id,title'])
            ->chunkById(100, function ($risks) use ($today, &$sent): void {
                foreach ($risks as $risk) {
                    if ($risk->pic === null || ! $risk->pic->is_active || $risk->project === null) {
                        continue;
                    }

                    $sent += (int) $this->deliver(
                        $risk->pic,
                        $risk->project,
                        'project_risk',
                        $risk->id,
                        $risk->due_date->toDateString(),
                        $today,
                        'Risiko melewati tenggat',
                        "Tindak lanjut risiko {$risk->title} pada proyek {$risk->project->title} melewati tenggat.",
                        route('projects.risks.index', ['project' => $risk->project, 'tab' => 'risks'], false),
                        'OVERDUE',
                        'overdue',
                        $risk->due_date->toDateString(),
                    );
                }
            });

        return $sent;
    }

    private function sendDueSoonBiweeklyReports(Carbon $today, int $daysAhead): int
    {
        $dueDate = $today->copy()->addDays($daysAhead);
        $sent = 0;

        BiweeklyReport::query()
            ->whereDate('ends_on', $dueDate->toDateString())
            ->whereIn('status', ['DRAFT', 'REVISION_REQUIRED'])
            ->whereHas('project', fn (Builder $project): Builder => $project->where('proposal_status', ProposalStatus::Approved))
            ->with(['creator:id,name,email,is_active', 'project:id,title'])
            ->chunkById(100, function ($reports) use ($today, $daysAhead, $dueDate, &$sent): void {
                foreach ($reports as $report) {
                    if ($report->creator === null || ! $report->creator->is_active || $report->project === null) {
                        continue;
                    }

                    $when = $daysAhead === 1 ? 'besok' : "{$daysAhead} hari lagi";
                    $sent += (int) $this->deliver(
                        $report->creator,
                        $report->project,
                        'biweekly_report',
                        $report->id,
                        $report->ends_on->toDateString(),
                        $today,
                        "Laporan bi-weekly jatuh tempo {$when}",
                        "Laporan bi-weekly periode {$report->period_no} untuk proyek {$report->project->title} perlu diajukan paling lambat {$this->formatDueDate($dueDate)}.",
                        route('projects.biweekly.index', $report->project, false),
                        'DUE_SOON_'.$daysAhead,
                        'DUE_SOON',
                        $dueDate->toDateString(),
                    );
                }
            });

        return $sent;
    }

    private function sendDueSoonMentoringReports(Carbon $today, int $daysAhead): int
    {
        $dueDate = $today->copy()->addDays($daysAhead);
        $sent = 0;

        MentoringReport::query()
            ->whereDate('session_at', $dueDate->toDateString())
            ->where('status', 'DRAFT')
            ->whereHas('project', fn (Builder $project): Builder => $project->where('proposal_status', ProposalStatus::Approved))
            ->with(['creator:id,name,email,is_active', 'project:id,title'])
            ->chunkById(100, function ($reports) use ($today, $daysAhead, $dueDate, &$sent): void {
                foreach ($reports as $report) {
                    if ($report->creator === null || ! $report->creator->is_active || $report->project === null) {
                        continue;
                    }

                    $when = $daysAhead === 1 ? 'besok' : "{$daysAhead} hari lagi";
                    $sent += (int) $this->deliver(
                        $report->creator,
                        $report->project,
                        'mentoring_report',
                        $report->id,
                        $report->session_at->toDateString(),
                        $today,
                        "Sesi mentoring {$when}",
                        "Sesi mentoring \"{$report->topic}\" untuk proyek {$report->project->title} dijadwalkan pada {$this->formatDueDate($dueDate)}. Lengkapi laporan sesi.",
                        route('projects.mentoring.index', $report->project, false),
                        'DUE_SOON_'.$daysAhead,
                        'DUE_SOON',
                        $dueDate->toDateString(),
                    );
                }
            });

        return $sent;
    }

    private function sendOverdueBiweeklyReports(Carbon $today): int
    {
        $sent = 0;

        BiweeklyReport::query()
            ->whereDate('ends_on', '<', $today->toDateString())
            ->whereIn('status', ['DRAFT', 'REVISION_REQUIRED'])
            ->whereHas('project', fn (Builder $project): Builder => $project->where('proposal_status', ProposalStatus::Approved))
            ->with(['creator:id,name,email,is_active', 'project:id,title'])
            ->chunkById(100, function ($reports) use ($today, &$sent): void {
                foreach ($reports as $report) {
                    if ($report->creator === null || ! $report->creator->is_active || $report->project === null) {
                        continue;
                    }

                    $sent += (int) $this->deliver(
                        $report->creator,
                        $report->project,
                        'biweekly_report',
                        $report->id,
                        $report->ends_on->toDateString(),
                        $today,
                        'Laporan bi-weekly melewati tenggat',
                        "Laporan bi-weekly periode {$report->period_no} untuk proyek {$report->project->title} belum diajukan dan melewati tenggat.",
                        route('projects.biweekly.index', $report->project, false),
                        'OVERDUE',
                        'overdue',
                        $report->ends_on->toDateString(),
                    );
                }
            });

        return $sent;
    }

    private function sendOverdueMentoringReports(Carbon $today): int
    {
        $sent = 0;

        MentoringReport::query()
            ->whereDate('session_at', '<', $today->toDateString())
            ->where('status', 'DRAFT')
            ->whereHas('project', fn (Builder $project): Builder => $project->where('proposal_status', ProposalStatus::Approved))
            ->with(['creator:id,name,email,is_active', 'project:id,title'])
            ->chunkById(100, function ($reports) use ($today, &$sent): void {
                foreach ($reports as $report) {
                    if ($report->creator === null || ! $report->creator->is_active || $report->project === null) {
                        continue;
                    }

                    $sent += (int) $this->deliver(
                        $report->creator,
                        $report->project,
                        'mentoring_report',
                        $report->id,
                        $report->session_at->toDateString(),
                        $today,
                        'Laporan mentoring melewati jadwal',
                        "Laporan sesi mentoring \"{$report->topic}\" untuk proyek {$report->project->title} belum dilengkapi setelah jadwal sesi.",
                        route('projects.mentoring.index', $report->project, false),
                        'OVERDUE',
                        'overdue',
                        $report->session_at->toDateString(),
                    );
                }
            });

        return $sent;
    }

    private function sendBatchScheduleReminders(Carbon $today, ?int $daysAhead): int
    {
        $dueDate = $daysAhead === null ? null : $today->copy()->addDays($daysAhead);
        $sent = 0;

        QccBatchSchedule::query()
            ->when(
                $dueDate !== null,
                fn (Builder $query): Builder => $query->whereDate('scheduled_on', $dueDate->toDateString()),
                fn (Builder $query): Builder => $query->whereDate('scheduled_on', '<', $today->toDateString()),
            )
            ->chunkById(100, function ($schedules) use ($today, $daysAhead, &$sent): void {
                foreach ($schedules as $schedule) {
                    $projects = Project::query()
                        ->where('batch_id', $schedule->batch_id)
                        ->where('proposal_status', ProposalStatus::Approved);

                    if ($schedule->schedule_type === 'BIWEEKLY') {
                        $projects->whereDoesntHave('biweeklyReports', fn (Builder $reports): Builder => $reports
                            ->where('period_no', $schedule->sequence_no));
                    } else {
                        $projects->whereDoesntHave('mentoringReports', fn (Builder $reports): Builder => $reports
                            ->whereDate('session_at', $schedule->scheduled_on->toDateString()));
                    }

                    $projects->chunkById(100, function ($projects) use ($schedule, $today, $daysAhead, &$sent): void {
                        foreach ($projects as $project) {
                            $recipients = $project->members()
                                ->wherePivot('status', 'ACTIVE')
                                ->wherePivotNull('left_at')
                                ->wherePivot('role_in_project', 'LEADER')
                                ->get();

                            if ($schedule->schedule_type === 'MENTORING') {
                                $mentors = $project->mentorAssignments()
                                    ->where('status', 'ACTIVE')
                                    ->with('mentor')
                                    ->get()
                                    ->pluck('mentor')
                                    ->filter();
                                $recipients = $recipients->concat($mentors)->unique('id');
                            }

                            $isBiweekly = $schedule->schedule_type === 'BIWEEKLY';
                            $subject = $isBiweekly
                                ? "Laporan bi-weekly periode {$schedule->sequence_no}"
                                : "Sesi mentoring {$schedule->sequence_no}";
                            $when = $daysAhead === null ? 'melewati jadwal' : "dalam {$daysAhead} hari";

                            foreach ($recipients as $recipient) {
                                if (! $recipient->is_active) {
                                    continue;
                                }

                                $sent += (int) $this->deliver(
                                    $recipient,
                                    $project,
                                    ($isBiweekly ? 'batch_biweekly_' : 'batch_mentoring_').$schedule->sequence_no,
                                    $project->id,
                                    $schedule->scheduled_on->toDateString(),
                                    $today,
                                    "{$subject} {$when}",
                                    "{$subject} untuk proyek {$project->title} dijadwalkan pada {$this->formatDueDate(Carbon::parse($schedule->scheduled_on))}. Siapkan laporan sesuai tanggung jawab tim.",
                                    route($isBiweekly ? 'projects.biweekly.index' : 'projects.mentoring.index', $project, false),
                                    $daysAhead === null ? 'OVERDUE' : 'DUE_SOON_'.$daysAhead,
                                    $daysAhead === null ? 'overdue' : 'DUE_SOON',
                                    $schedule->scheduled_on->toDateString(),
                                );
                            }
                        }
                    });
                }
            });

        return $sent;
    }

    private function deliver(
        User $recipient,
        Project $project,
        string $workType,
        int $workId,
        string $dueDate,
        Carbon $today,
        string $title,
        string $message,
        string $url,
        string $reminderType,
        string $decision,
        ?string $dueDateLabel = null,
    ): bool {
        if (! $recipient->can('projects.view') || ! Project::query()->visibleTo($recipient)->whereKey($project->id)->exists()) {
            return false;
        }

        return DB::transaction(function () use ($recipient, $project, $workType, $workId, $dueDate, $today, $title, $message, $url, $reminderType, $decision, $dueDateLabel): bool {
            $inserted = DB::table('overdue_reminder_deliveries')->insertOrIgnore([
                'recipient_id' => $recipient->id,
                'work_type' => $workType,
                'work_id' => $workId,
                'reminder_type' => $reminderType,
                'due_date' => $dueDate,
                'scheduled_for' => $today->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted === 0) {
                return false;
            }

            $recipient->notify(new ProjectActivityNotification(
                $project->id,
                $title,
                $message,
                $url,
                $decision,
                $dueDateLabel,
            ));

            return true;
        });
    }

    private function formatDueDate(Carbon $dueDate): string
    {
        return $dueDate->copy()->settings(['locale' => 'id'])->translatedFormat('j M Y');
    }
}
