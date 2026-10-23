<?php

namespace App\Console\Commands;

use App\Enums\ProposalStatus;
use App\Models\ActionItem;
use App\Models\Project;
use App\Models\ProjectRisk;
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

        foreach ([3, 1] as $daysAhead) {
            $sent += $this->sendDueSoonActions($today, $daysAhead);
            $sent += $this->sendDueSoonRisks($today, $daysAhead);
        }

        $sent += $this->sendOverdueActions($today) + $this->sendOverdueRisks($today);

        $this->info("Pengingat tenggat terkirim: {$sent}.");

        return self::SUCCESS;
    }

    private function applyNotificationSettings(): void
    {
        $settings = SystemSetting::query()
            ->whereIn('key', ['notifications_in_app', 'notifications_email'])
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
        ]);
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
                        route('projects.risks.index', $item->project).'?tab=actions',
                        $daysAhead === 3 ? 'DUE_SOON_3' : 'DUE_SOON_1',
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
                        route('projects.risks.index', $risk->project).'?tab=risks',
                        $daysAhead === 3 ? 'DUE_SOON_3' : 'DUE_SOON_1',
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
                        route('projects.risks.index', $item->project).'?tab=actions',
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
                        route('projects.risks.index', $risk->project).'?tab=risks',
                        'OVERDUE',
                        'overdue',
                        $risk->due_date->toDateString(),
                    );
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
        if (! Project::query()->visibleTo($recipient)->whereKey($project->id)->exists()) {
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
