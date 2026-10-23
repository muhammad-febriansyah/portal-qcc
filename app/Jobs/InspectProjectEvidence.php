<?php

namespace App\Jobs;

use App\Models\ProjectEvidence;
use App\Services\EvidenceFileInspector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class InspectProjectEvidence implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly int $evidenceId) {}

    public function handle(EvidenceFileInspector $inspector): void
    {
        $evidence = ProjectEvidence::query()->findOrFail($this->evidenceId);

        if ($evidence->source_type !== 'FILE' || $evidence->scan_status === 'REJECTED' || ($evidence->scan_status === 'PASSED' && $evidence->processing_status === 'READY')) {
            return;
        }

        $disk = Storage::disk($evidence->storage_disk);
        $temporaryFile = tempnam(sys_get_temp_dir(), 'qcc-evidence-');

        if ($temporaryFile === false) {
            throw new RuntimeException('Temporary file tidak dapat dibuat.');
        }

        try {
            $source = $disk->readStream($evidence->storage_key);
            $destination = fopen($temporaryFile, 'wb');

            if (! is_resource($source) || $destination === false) {
                throw new RuntimeException('File evidence tidak dapat dibaca.');
            }

            stream_copy_to_stream($source, $destination);
            fclose($source);
            fclose($destination);

            $inspection = $inspector->inspectPath($temporaryFile, $evidence->file_extension);

            if (! $inspection['valid']) {
                $evidence->forceFill([
                    'scan_status' => 'REJECTED',
                    'scan_error' => $inspection['error'],
                    'processing_status' => 'FAILED',
                    'extraction_status' => 'NOT_APPLICABLE',
                    'processing_error' => 'Format file tidak valid.',
                ])->save();

                return;
            }

            $evidence->forceFill([
                'scan_status' => 'PASSED',
                'scan_error' => null,
                'processing_status' => 'PROCESSING',
                'processing_error' => null,
                'metadata_json' => array_merge(
                    is_array($evidence->metadata_json) ? $evidence->metadata_json : [],
                    $inspection['metadata'],
                    $evidence->file_extension === 'pdf' ? ['extraction_page_limit' => 50] : [],
                ),
            ])->save();

            $thumbnailPath = null;
            $extractedTextPath = null;
            $extractionStatus = 'NOT_APPLICABLE';

            if (in_array($evidence->file_extension, ['jpg', 'jpeg', 'png'], true)) {
                $thumbnailPath = $this->createImageThumbnail($evidence, $temporaryFile, $inspection['metadata']);
            }

            if ($evidence->file_extension === 'pdf') {
                [$extractionStatus, $extractedTextPath] = $this->extractPdfText($evidence, $temporaryFile);
            }

            $evidence->forceFill([
                'processing_status' => 'READY',
                'extraction_status' => $extractionStatus,
                'thumbnail_path' => $thumbnailPath,
                'extracted_text_path' => $extractedTextPath,
            ])->save();
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

    public function failed(Throwable $exception): void
    {
        $evidence = ProjectEvidence::query()->find($this->evidenceId);

        if ($evidence === null || $evidence->scan_status === 'REJECTED') {
            return;
        }

        $evidence->forceFill([
            'scan_status' => $evidence->scan_status === 'PASSED' ? 'PASSED' : 'FAILED',
            'processing_status' => 'FAILED',
            'extraction_status' => $evidence->file_extension === 'pdf' ? 'FAILED' : 'NOT_APPLICABLE',
            'processing_error' => 'Pemeriksaan atau pemrosesan file gagal. Unggah versi baru atau hubungi administrator.',
        ])->save();
    }

    /** @param array<string, int|string> $metadata */
    private function createImageThumbnail(ProjectEvidence $evidence, string $sourcePath, array $metadata): ?string
    {
        $width = (int) ($metadata['width'] ?? 0);
        $height = (int) ($metadata['height'] ?? 0);

        if ($width < 1 || $height < 1 || $width * $height > (int) config('qcc_evidence.max_image_pixels')) {
            return null;
        }

        $contents = file_get_contents($sourcePath);
        $sourceImage = $contents === false ? false : @imagecreatefromstring($contents);

        if ($sourceImage === false) {
            throw new RuntimeException('Gambar gagal didekode untuk pratinjau.');
        }

        $scale = min(1, 480 / max($width, $height));
        $thumbWidth = max(1, (int) round($width * $scale));
        $thumbHeight = max(1, (int) round($height * $scale));
        $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);

        if ($thumbnail === false) {
            imagedestroy($sourceImage);

            throw new RuntimeException('Thumbnail gambar gagal dibuat.');
        }

        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);
        ob_start();
        imagepng($thumbnail);
        $thumbnailBytes = ob_get_clean();
        imagedestroy($sourceImage);
        imagedestroy($thumbnail);

        if (! is_string($thumbnailBytes)) {
            throw new RuntimeException('Thumbnail gambar gagal disimpan.');
        }

        $thumbnailPath = 'projects/'.$evidence->project_id.'/thumbnails/'.$evidence->getKey().'.png';
        if (! Storage::disk($evidence->storage_disk)->put($thumbnailPath, $thumbnailBytes)) {
            throw new RuntimeException('Thumbnail gambar gagal disimpan.');
        }

        return $thumbnailPath;
    }

    /** @return array{0: string, 1: ?string} */
    private function extractPdfText(ProjectEvidence $evidence, string $sourcePath): array
    {
        $binary = (string) config('qcc_evidence.pdf_text_extractor');
        $executable = (new ExecutableFinder)->find($binary);

        if ($executable === null) {
            return ['UNAVAILABLE', null];
        }

        $temporaryText = tempnam(sys_get_temp_dir(), 'qcc-pdf-text-');

        if ($temporaryText === false) {
            throw new RuntimeException('Temporary text file tidak dapat dibuat.');
        }

        $process = new Process([$executable, '-f', '1', '-l', '50', '-layout', $sourcePath, $temporaryText]);
        $process->setTimeout((int) config('qcc_evidence.pdf_extraction_timeout'));
        try {
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('Ekstraksi teks PDF gagal.');
            }

            $maxBytes = max(0, (int) config('qcc_evidence.max_extracted_text_bytes'));
            $text = file_get_contents($temporaryText, false, null, 0, $maxBytes);

            if ($text === false) {
                throw new RuntimeException('Hasil ekstraksi teks tidak dapat dibaca.');
            }

            $extractedTextPath = 'projects/'.$evidence->project_id.'/extracted/'.$evidence->getKey().'.txt';

            if (! Storage::disk($evidence->storage_disk)->put($extractedTextPath, $text)) {
                throw new RuntimeException('Hasil ekstraksi teks gagal disimpan.');
            }

            return ['READY', $extractedTextPath];
        } finally {
            if (is_file($temporaryText)) {
                unlink($temporaryText);
            }
        }
    }
}
