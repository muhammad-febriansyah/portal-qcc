<?php

namespace App\Services;

use App\Models\Competency;
use App\Services\Spreadsheet\InvalidSpreadsheetException;
use App\Services\Spreadsheet\XlsxReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CompetencyImportService
{
    /** @var array<string, list<string>> Accepted header names for each import column. */
    private const HEADER_ALIASES = [
        'code' => ['code', 'kode', 'skill id', 'kode skill'],
        'name' => ['name', 'nama', 'nama kompetensi', 'nama skill'],
        'description' => ['description', 'deskripsi', 'definisi', 'deskripsi sederhana'],
    ];

    /** @var array<string, string> */
    private const COLUMN_LABELS = [
        'code' => 'Kode',
        'name' => 'Nama kompetensi',
        'description' => 'Definisi',
    ];

    public function __construct(
        private readonly AuditService $auditService,
        private readonly XlsxReader $reader,
    ) {}

    /**
     * Import an uploaded Excel workbook (first sheet).
     *
     * @return array{created: int, updated: int, total: int}
     */
    public function importXlsx(string $path, bool $recordAudit = true): array
    {
        try {
            $rows = $this->reader->firstSheetRows($path);
        } catch (InvalidSpreadsheetException) {
            throw ValidationException::withMessages([
                'file' => 'File Excel tidak dapat dibaca. Gunakan template yang disediakan dan simpan sebagai .xlsx.',
            ]);
        }

        return $this->persist($this->validate($rows), $recordAudit);
    }

    /**
     * Import the project's own CSV seed file. Used by the seeder, not by the upload form.
     *
     * @return array{created: int, updated: int, total: int}
     */
    public function importCsv(string $path, bool $recordAudit = true): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages(['file' => 'File CSV tidak dapat dibaca.']);
        }

        $rows = [];
        $rowNumber = 0;

        try {
            while (($values = fgetcsv($handle, null, ',', '"', '')) !== false) {
                $rowNumber++;

                if ($rowNumber === 1 && isset($values[0])) {
                    $values[0] = (string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $values[0]);
                }

                $rows[] = ['row' => $rowNumber, 'cells' => array_map(static fn (mixed $value): string => trim((string) $value), $values)];
            }
        } finally {
            fclose($handle);
        }

        return $this->persist($this->validate($rows), $recordAudit);
    }

    /**
     * @param  list<array{code: string, name: string, description: ?string}>  $rows
     * @return array{created: int, updated: int, total: int}
     */
    private function persist(array $rows, bool $recordAudit): array
    {
        return DB::transaction(function () use ($rows, $recordAudit): array {
            $existingCodes = DB::table('competency_master')
                ->whereIn('code', array_column($rows, 'code'))
                ->pluck('is_active', 'code')
                ->all();
            $created = 0;
            $updated = 0;
            $timestamp = now();

            $records = array_map(function (array $row) use (&$created, &$updated, $existingCodes, $timestamp): array {
                $isExisting = array_key_exists($row['code'], $existingCodes);

                if ($isExisting) {
                    $updated++;
                } else {
                    $created++;
                }

                return [
                    'code' => $row['code'],
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'is_active' => $isExisting ? $existingCodes[$row['code']] : true,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }, $rows);

            DB::table('competency_master')->upsert(
                $records,
                ['code'],
                ['name', 'description', 'updated_at'],
            );

            if ($recordAudit) {
                $this->auditService->record(
                    'competency.imported',
                    after: [
                        'created' => $created,
                        'updated' => $updated,
                        'total' => count($records),
                    ],
                    subjectLabel: 'Impor master kompetensi',
                );
            }

            return [
                'created' => $created,
                'updated' => $updated,
                'total' => count($records),
            ];
        });
    }

    /**
     * @param  list<array{row: int, cells: list<string>}>  $sheetRows  The first row holds the headers.
     * @return list<array{code: string, name: string, description: ?string}>
     */
    private function validate(array $sheetRows): array
    {
        if ($sheetRows === []) {
            throw ValidationException::withMessages(['file' => 'File kosong atau tidak memiliki header.']);
        }

        $columns = $this->mapColumns($sheetRows[0]['cells']);
        $rows = [];
        $seenCodes = [];
        $validationErrors = [];

        foreach (array_slice($sheetRows, 1) as $sheetRow) {
            $rowNumber = $sheetRow['row'];
            $data = [];

            foreach ($columns as $field => $index) {
                $data[$field] = trim($sheetRow['cells'][$index] ?? '');
            }

            if ($data['code'] === '' && $data['name'] === '' && $data['description'] === '') {
                continue;
            }

            $data['code'] = strtoupper($data['code']);
            $data['description'] = $data['description'] === '' ? null : $data['description'];

            $validator = Validator::make($data, [
                'code' => ['required', 'string', 'max:10', 'regex:'.Competency::CODE_PATTERN],
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
            ], [
                'code.regex' => Competency::CODE_HINT,
            ], self::COLUMN_LABELS);

            $errors = $validator->fails() ? $validator->errors()->all() : [];

            if ($data['code'] !== '') {
                if (isset($seenCodes[$data['code']])) {
                    $errors[] = "Kode {$data['code']} duplikat di dalam file (pertama pada baris {$seenCodes[$data['code']]}).";
                } else {
                    $seenCodes[$data['code']] = $rowNumber;
                }
            }

            if ($errors === []) {
                $rows[] = [
                    'code' => $data['code'],
                    'name' => $data['name'],
                    'description' => $data['description'],
                ];

                continue;
            }

            foreach ($errors as $error) {
                $validationErrors["row_{$rowNumber}"][] = "Baris {$rowNumber}: {$error}";
            }
        }

        if ($validationErrors !== []) {
            throw ValidationException::withMessages($validationErrors);
        }

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => 'File tidak memuat baris kompetensi untuk diimpor. Isi kolom Kode dan Nama kompetensi.',
            ]);
        }

        return $rows;
    }

    /**
     * @param  list<string>  $headerCells
     * @return array<string, int> Column index for every import field.
     */
    private function mapColumns(array $headerCells): array
    {
        $normalized = array_map(
            static fn (string $header): string => strtolower((string) preg_replace('/\s+/', ' ', trim(ltrim($header, "\xEF\xBB\xBF")))),
            $headerCells,
        );
        $columns = [];

        foreach (self::HEADER_ALIASES as $field => $aliases) {
            $matches = array_keys(array_filter($normalized, static fn (string $header): bool => in_array($header, $aliases, true)));

            if (count($matches) !== 1) {
                throw ValidationException::withMessages([
                    'file' => 'Header tidak sesuai template. Kolom wajib: Kode, Nama Kompetensi, dan Definisi (satu kali). Unduh ulang template jika perlu.',
                ]);
            }

            $columns[$field] = $matches[0];
        }

        return $columns;
    }
}
