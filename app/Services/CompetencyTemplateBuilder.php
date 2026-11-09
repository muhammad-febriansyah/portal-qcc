<?php

namespace App\Services;

use App\Services\Spreadsheet\XlsxWriter;
use Illuminate\Support\Facades\DB;

class CompetencyTemplateBuilder
{
    public function __construct(private readonly XlsxWriter $writer) {}

    /**
     * Build the import template with every skill already in the master, so the same file
     * works to edit existing skills or to add new ones at the bottom.
     *
     * @return string Path of a temporary .xlsx file. The caller deletes it.
     */
    public function build(): string
    {
        $rows = [['Kode', 'Nama Kompetensi', 'Definisi']];

        foreach (DB::table('competency_master')->orderBy('code')->get(['code', 'name', 'description']) as $competency) {
            $rows[] = [
                (string) $competency->code,
                (string) $competency->name,
                $competency->description === null ? null : (string) $competency->description,
            ];
        }

        $guide = [
            ['Panduan Mengisi Template'],
            [''],
            ['1. Setiap baris berisi satu skill: Kode, Nama Kompetensi, dan Definisi.'],
            ['2. Kode memakai H untuk hard skill (H01, H02, ...) dan S untuk soft skill (S01, S02, ...).'],
            ['3. Untuk menambah skill, isi baris baru di bawah dengan kode yang belum dipakai.'],
            ['4. Kode yang sudah ada akan diperbarui, kode baru akan dibuat.'],
            ['5. Baris yang Kode, Nama, dan Definisi-nya kosong akan dilewati.'],
            ['6. Simpan sebagai .xlsx lalu unggah di halaman Master Kompetensi.'],
        ];

        $path = tempnam(sys_get_temp_dir(), 'kompetensi-template-');

        if ($path === false) {
            throw new \RuntimeException('Tidak dapat membuat file template sementara.');
        }

        $this->writer->write($path, [
            [
                'name' => 'Kompetensi',
                'rows' => $rows,
                'widths' => [10, 46, 90],
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
