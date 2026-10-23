<?php

namespace App\Services;

use App\Services\Spreadsheet\XlsxWriter;
use Illuminate\Support\Facades\DB;

class CompetencyTemplateBuilder
{
    public function __construct(private readonly XlsxWriter $writer) {}

    /**
     * Build the import template. All 62 official codes are listed; rows already in the
     * master are filled in, so the same file also works to edit existing competencies.
     *
     * @return string Path of a temporary .xlsx file. The caller deletes it.
     */
    public function build(): string
    {
        $groups = DB::table('competency_groups')->orderBy('code')->get(['code', 'name']);
        $existing = DB::table('competency_master')
            ->join('competency_groups', 'competency_groups.id', '=', 'competency_master.group_id')
            ->get(['competency_master.code', 'competency_master.name', 'competency_master.description', 'competency_groups.code as group_code'])
            ->keyBy('code');

        $rows = [['Kode', 'Kelompok', 'Nama Kompetensi', 'Definisi']];

        foreach (range(1, 62) as $number) {
            $code = sprintf('C%03d', $number);
            $current = $existing->get($code);

            $rows[] = $current === null
                ? [$code, null, null, null]
                : [$code, (string) $current->group_code, (string) $current->name, $current->description === null ? null : (string) $current->description];
        }

        $guide = [
            ['Panduan Mengisi Template'],
            [''],
            ['1. Isi kolom Kelompok, Nama Kompetensi, dan Definisi pada sheet "Kompetensi".'],
            ['2. Kolom Kode sudah terisi C001–C062. Jangan diubah atau ditambah.'],
            ['3. Kolom Kelompok dipilih dari daftar (huruf A–F) sesuai pilihan di bawah.'],
            ['4. Baris yang kolom Kelompok, Nama, dan Definisi-nya kosong akan dilewati.'],
            ['5. Simpan sebagai .xlsx lalu unggah di halaman Master Kompetensi.'],
            ['6. Kode yang sudah ada akan diperbarui, kode baru akan dibuat.'],
            [''],
            ['Pilihan kelompok'],
            ...$groups->map(fn (object $group): array => ["{$group->code} = {$group->name}"])->values()->all(),
        ];

        $path = tempnam(sys_get_temp_dir(), 'kompetensi-template-');

        if ($path === false) {
            throw new \RuntimeException('Tidak dapat membuat file template sementara.');
        }

        $this->writer->write($path, [
            [
                'name' => 'Kompetensi',
                'rows' => $rows,
                'widths' => [10, 12, 42, 80],
                'validations' => [['sqref' => 'B2:B63', 'values' => array_values($groups->map(fn (object $group): string => (string) $group->code)->all())]],
            ],
            [
                'name' => 'Panduan',
                'rows' => $guide,
                'widths' => [90],
                'header_rows' => 0,
            ],
        ]);

        return $path;
    }
}
