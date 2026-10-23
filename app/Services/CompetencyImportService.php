<?php

namespace App\Services;

use App\Services\Spreadsheet\InvalidSpreadsheetException;
use App\Services\Spreadsheet\XlsxReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CompetencyImportService
{
    /** @var array<string, list<string>> Accepted header names for each import column. */
    private const HEADER_ALIASES = [
        'code' => ['code', 'kode'],
        'group_code' => ['group_code', 'kelompok', 'kode kelompok'],
        'name' => ['name', 'nama', 'nama kompetensi'],
        'description' => ['description', 'deskripsi', 'definisi'],
    ];

    /** @var array<string, string> */
    private const COLUMN_LABELS = [
        'code' => 'Kode',
        'group_code' => 'Kelompok',
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
     * @param  list<array{code: string, group_id: int, name: string, description: ?string}>  $rows
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
                    'group_id' => $row['group_id'],
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
                ['group_id', 'name', 'description', 'updated_at'],
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
     * @return list<array{code: string, group_id: int, name: string, description: ?string}>
     */
    private function validate(array $sheetRows): array
    {
        if ($sheetRows === []) {
            throw ValidationException::withMessages(['file' => 'File kosong atau tidak memiliki header.']);
        }

        $columns = $this->mapColumns($sheetRows[0]['cells']);
        $groupsByCode = DB::table('competency_groups')->pluck('id', 'code')->all();
        $groupCodes = array_map('strval', array_keys($groupsByCode));
        $officialCodes = array_map(
            static fn (int $number): string => sprintf('C%03d', $number),
            range(1, 62),
        );
        $rows = [];
        $seenCodes = [];
        $validationErrors = [];

        foreach (array_slice($sheetRows, 1) as $sheetRow) {
            $rowNumber = $sheetRow['row'];
            $data = [];

            foreach ($columns as $field => $index) {
                $data[$field] = trim($sheetRow['cells'][$index] ?? '');
            }

            if ($data['group_code'] === '' && $data['name'] === '' && $data['description'] === '') {
                continue;
            }

            $data['code'] = strtoupper($data['code']);
            $data['group_code'] = $this->groupLetter($data['group_code']);
            $data['description'] = $data['description'] === '' ? null : $data['description'];

            $validator = Validator::make($data, [
                'code' => ['required', 'string', Rule::in($officialCodes)],
                'group_code' => ['required', 'string', Rule::in($groupCodes)],
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
            ], [
                'code.in' => 'Kode harus menggunakan format resmi C001–C062.',
                'group_code.in' => 'Kelompok harus salah satu dari: '.implode(', ', $groupCodes).'.',
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
                    'group_id' => (int) $groupsByCode[$data['group_code']],
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
                'file' => 'File tidak memuat baris kompetensi untuk diimpor. Isi kolom Kelompok dan Nama kompetensi.',
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
                    'file' => 'Header tidak sesuai template. Kolom wajib: Kode, Kelompok, Nama Kompetensi, dan Definisi (satu kali). Unduh ulang template jika perlu.',
                ]);
            }

            $columns[$field] = $matches[0];
        }

        return $columns;
    }

    /**
     * Accepts "A" as well as "A - Core QCC & Problem Solving".
     */
    private function groupLetter(string $value): string
    {
        $value = strtoupper(trim($value));

        return preg_match('/^([A-Z0-9]+)\s*[-–:]/', $value, $match) === 1 ? $match[1] : $value;
    }
}
