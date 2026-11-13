<?php

namespace App\Console\Commands;

use App\Actions\Proposals\InspectProposalAttachment;
use App\Models\ProjectAttachment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('qcc:proposals:inspect-attachments')]
#[Description('Periksa format lampiran proposal lama yang masih menunggu pemeriksaan.')]
class InspectPendingProposalAttachments extends Command
{
    public function handle(InspectProposalAttachment $inspector): int
    {
        $counts = ['PASSED' => 0, 'REJECTED' => 0, 'FAILED' => 0];

        ProjectAttachment::query()
            ->where('scan_status', 'PENDING_SCAN')
            ->chunkById(100, function ($attachments) use ($inspector, &$counts): void {
                foreach ($attachments as $attachment) {
                    $status = $inspector->handle($attachment);
                    $counts[$status]++;
                }
            });

        $this->components->info("Format valid: {$counts['PASSED']}; ditolak: {$counts['REJECTED']}; gagal diperiksa: {$counts['FAILED']}.");

        return self::SUCCESS;
    }
}
