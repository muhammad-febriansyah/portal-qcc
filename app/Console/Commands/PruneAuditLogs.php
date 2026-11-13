<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\AuditService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

#[Signature('qcc:audit:prune {--days= : Jumlah hari audit log tetap ada di tabel}')]
#[Description('Arsipkan audit log yang lebih lama dari masa retensi, lalu hapus dari tabel.')]
class PruneAuditLogs extends Command
{
    private const ARCHIVE_DIRECTORY = 'audit-archive';

    public function handle(AuditService $audit): int
    {
        $days = (int) ($this->option('days') ?? config('qcc.audit.retention_days'));

        if ($days < 1) {
            $this->components->error('Masa retensi audit log minimal 1 hari.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $archivePath = self::ARCHIVE_DIRECTORY.'/audit-logs-'.now()->format('Ymd-His').'.jsonl';
        $archived = 0;

        AuditLog::query()
            ->where('occurred_at', '<', $cutoff)
            ->chunkById(500, function (Collection $logs) use ($archivePath, &$archived): void {
                $lines = $logs
                    ->map(fn (AuditLog $log): string => json_encode($log->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR))
                    ->implode("\n");

                Storage::disk('local')->append($archivePath, $lines."\n");

                // Query builder delete skips the model's deleting guard on purpose: this is
                // the only place audit rows are removed, and only after they are archived.
                AuditLog::query()->whereKey($logs->modelKeys())->delete();

                $archived += $logs->count();
            });

        if ($archived === 0) {
            $this->components->info('Tidak ada audit log yang perlu diarsipkan.');

            return self::SUCCESS;
        }

        $audit->record(
            event: 'audit.pruned',
            after: ['archived' => $archived, 'older_than' => $cutoff->toDateTimeString(), 'file' => $archivePath],
            reason: "Retensi {$days} hari",
        );

        $this->components->info("Diarsipkan dan dihapus: {$archived} audit log lebih lama dari {$days} hari.");

        return self::SUCCESS;
    }
}
