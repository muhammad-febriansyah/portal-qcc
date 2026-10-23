<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use ZipArchive;

class EvidenceFileInspector
{
    /** @return array{valid: bool, error: ?string, metadata: array<string, int|string>} */
    public function inspectUpload(UploadedFile $file, string $extension): array
    {
        return $this->inspectPath($file->getRealPath() ?: $file->getPathname(), $extension);
    }

    /** @return array{valid: bool, error: ?string, metadata: array<string, int|string>} */
    public function inspectPath(string $path, string $extension): array
    {
        $header = file_get_contents($path, false, null, 0, 16);

        if ($header === false) {
            return $this->invalid('File tidak dapat dibaca.');
        }

        if ($extension === 'pdf') {
            $handle = fopen($path, 'rb');
            $tail = '';

            if ($handle !== false) {
                fseek($handle, max(0, filesize($path) - 2048));
                $tail = (string) fread($handle, 2048);
                fclose($handle);
            }

            if (! str_starts_with($header, '%PDF-') || ! str_contains($tail, '%%EOF')) {
                return $this->invalid('Isi file tidak sesuai dengan format PDF.');
            }

            return ['valid' => true, 'error' => null, 'metadata' => ['format' => 'PDF']];
        }

        if (in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            $image = @getimagesize($path);
            $expectedMime = in_array($extension, ['jpg', 'jpeg'], true) ? 'image/jpeg' : 'image/png';

            if ($image === false || $image['mime'] !== $expectedMime) {
                return $this->invalid('Isi file tidak sesuai dengan format gambar.');
            }

            return [
                'valid' => true,
                'error' => null,
                'metadata' => ['format' => $extension, 'width' => (int) $image[0], 'height' => (int) $image[1]],
            ];
        }

        if (in_array($extension, ['docx', 'xlsx'], true)) {
            $zip = new ZipArchive;
            $opened = $zip->open($path, ZipArchive::RDONLY);
            $requiredEntry = $extension === 'docx' ? 'word/document.xml' : 'xl/workbook.xml';
            $hasExpectedEntry = $opened === true && $zip->locateName($requiredEntry) !== false;
            $macroEntry = $extension === 'docx' ? 'word/vbaProject.bin' : 'xl/vbaProject.bin';
            $hasMacro = $opened === true && $zip->locateName($macroEntry) !== false;

            if ($opened === true) {
                $zip->close();
            }

            if (! str_starts_with($header, "PK\x03\x04") || ! $hasExpectedEntry || $hasMacro) {
                return $this->invalid('Isi file tidak sesuai dengan format dokumen yang diizinkan.');
            }

            return ['valid' => true, 'error' => null, 'metadata' => ['format' => strtoupper($extension)]];
        }

        return $this->invalid('Format file tidak didukung.');
    }

    /** @return array{valid: false, error: string, metadata: array{}} */
    private function invalid(string $message): array
    {
        return ['valid' => false, 'error' => $message, 'metadata' => []];
    }
}
