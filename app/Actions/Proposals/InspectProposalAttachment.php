<?php

namespace App\Actions\Proposals;

use App\Models\ProjectAttachment;
use App\Services\EvidenceFileInspector;
use Illuminate\Support\Facades\Storage;
use Throwable;

class InspectProposalAttachment
{
    public function __construct(private readonly EvidenceFileInspector $inspector) {}

    public function handle(ProjectAttachment $attachment): string
    {
        if ($attachment->scan_status !== 'PENDING_SCAN') {
            return $attachment->scan_status;
        }

        $temporaryFile = tempnam(sys_get_temp_dir(), 'qcc-proposal-');

        if ($temporaryFile === false) {
            return $this->finish($attachment, 'FAILED');
        }

        try {
            $source = Storage::disk($attachment->disk)->readStream($attachment->path);
            $destination = fopen($temporaryFile, 'wb');

            if (! is_resource($source) || $destination === false) {
                return $this->finish($attachment, 'FAILED');
            }

            stream_copy_to_stream($source, $destination);
            fclose($source);
            fclose($destination);

            $extension = strtolower(pathinfo($attachment->original_name, PATHINFO_EXTENSION));
            $inspection = $this->inspector->inspectPath($temporaryFile, $extension);

            return $this->finish($attachment, $inspection['valid'] ? 'PASSED' : 'REJECTED');
        } catch (Throwable) {
            return $this->finish($attachment, 'FAILED');
        } finally {
            if (is_resource($source ?? null)) {
                fclose($source);
            }

            if (is_resource($destination ?? null)) {
                fclose($destination);
            }

            if (is_file($temporaryFile)) {
                unlink($temporaryFile);
            }
        }
    }

    private function finish(ProjectAttachment $attachment, string $status): string
    {
        $attachment->forceFill(['scan_status' => $status])->save();

        return $status;
    }
}
