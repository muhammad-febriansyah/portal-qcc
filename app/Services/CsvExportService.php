<?php

namespace App\Services;

use BackedEnum;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExportService
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     */
    public function download(
        string $type,
        string $filename,
        array $headers,
        iterable $rows,
        int $rowCount,
        array $filters,
        AuditService $audit,
    ): StreamedResponse {
        $audit->record('data.exported', after: [
            'type' => $type,
            'filters' => $filters,
            'row_count' => $rowCount,
        ], subjectLabel: "Ekspor CSV · {$type}");

        return new StreamedResponse(function () use ($headers, $rows): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers, ',', '"', '\\');

            foreach ($rows as $row) {
                fputcsv($output, array_map($this->cell(...), $row), ',', '"', '\\');
            }

            fclose($output);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function cell(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        } elseif ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s');
        } elseif (is_array($value)) {
            $value = implode(', ', array_map(static fn (mixed $item): string => (string) $item, $value));
        } elseif (is_bool($value)) {
            $value = $value ? 'Ya' : 'Tidak';
        }

        $cell = (string) ($value ?? '');

        return preg_match('/^[=+\-@]/u', $cell) === 1 ? "'{$cell}" : $cell;
    }
}
