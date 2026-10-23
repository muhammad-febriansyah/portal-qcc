<?php

use App\Services\Spreadsheet\InvalidSpreadsheetException;
use App\Services\Spreadsheet\XlsxReader;
use App\Services\Spreadsheet\XlsxWriter;

/**
 * Builds a workbook the way Excel stores it: shared strings, gaps between cells,
 * numeric cells and an absolute sheet target.
 *
 * @param  array<string, string>  $overrides  Replaces or adds package parts by name.
 */
function excelStyleWorkbook(array $overrides = []): string
{
    $parts = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Data" sheetId="3" r:id="rId7"/><sheet name="Other" sheetId="4" r:id="rId8"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId7" Type="x" Target="/xl/worksheets/sheet9.xml"/><Relationship Id="rId8" Type="x" Target="worksheets/sheet2.xml"/></Relationships>',
        'xl/sharedStrings.xml' => '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="4" uniqueCount="4"><si><t>Kode</t></si><si><t>Nama</t></si><si><r><t>Rich </t></r><r><t>text</t></r></si><si><t>Amp &amp; &lt;tag&gt;</t></si></sst>',
        'xl/worksheets/sheet9.xml' => '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .'<row r="2"><c r="A2" t="s"><v>0</v></c><c r="C2" t="s"><v>1</v></c></row>'
            .'<row r="3"><c r="A3"><v>62</v></c><c r="B3" t="inlineStr"><is><t>inline</t></is></c><c r="C3" t="s"><v>2</v></c><c r="D3" t="s"><v>3</v></c></row>'
            .'<row r="4"><c r="A4" t="e"><v>#N/A</v></c></row>'
            .'<row r="6"><c r="B6" t="str"><v>formula result</v></c></row>'
            .'</sheetData></worksheet>',
        'xl/worksheets/sheet2.xml' => '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>wrong sheet</t></is></c></row></sheetData></worksheet>',
        ...$overrides,
    ];

    $path = tempnam(sys_get_temp_dir(), 'xlsx-reader-test-');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    foreach ($parts as $name => $contents) {
        $zip->addFromString($name, $contents);
    }

    $zip->close();

    return $path;
}

test('it reads the first sheet of an Excel style workbook with shared strings and gaps', function () {
    $path = excelStyleWorkbook();

    $rows = (new XlsxReader)->firstSheetRows($path);
    unlink($path);

    expect($rows)->toBe([
        ['row' => 2, 'cells' => ['Kode', '', 'Nama']],
        ['row' => 3, 'cells' => ['62', 'inline', 'Rich text', 'Amp & <tag>']],
        ['row' => 6, 'cells' => ['', 'formula result']],
    ]);
});

test('it round trips what the writer produces, including special characters', function () {
    $path = tempnam(sys_get_temp_dir(), 'xlsx-roundtrip-');
    (new XlsxWriter)->write($path, [[
        'name' => 'Data: [test]',
        'rows' => [['Kode', 'Teks'], ['C001', "Tom & \"Jerry\" <b>\nbaris dua"], ['C002', 12.5], ['', 'x']],
    ]]);

    $rows = (new XlsxReader)->firstSheetRows($path);
    unlink($path);

    expect($rows)->toBe([
        ['row' => 1, 'cells' => ['Kode', 'Teks']],
        ['row' => 2, 'cells' => ['C001', "Tom & \"Jerry\" <b>\nbaris dua"]],
        ['row' => 3, 'cells' => ['C002', '12.5']],
        ['row' => 4, 'cells' => ['', 'x']],
    ]);
});

test('it rejects a file that is not a zip archive', function () {
    $path = tempnam(sys_get_temp_dir(), 'xlsx-reader-test-');
    file_put_contents($path, 'plain text, not a workbook');

    expect(fn () => (new XlsxReader)->firstSheetRows($path))->toThrow(InvalidSpreadsheetException::class);

    unlink($path);
});

test('it rejects a workbook that declares XML entities', function () {
    $path = excelStyleWorkbook([
        'xl/sharedStrings.xml' => '<?xml version="1.0"?><!DOCTYPE sst [<!ENTITY a "aaaa">]><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>&a;</t></si></sst>',
    ]);

    expect(fn () => (new XlsxReader)->firstSheetRows($path))->toThrow(InvalidSpreadsheetException::class);

    unlink($path);
});

test('it rejects a workbook whose first sheet is missing', function () {
    $path = excelStyleWorkbook(['xl/worksheets/sheet9.xml' => '']);
    $zip = new ZipArchive;
    $zip->open($path);
    $zip->deleteName('xl/worksheets/sheet9.xml');
    $zip->close();

    expect(fn () => (new XlsxReader)->firstSheetRows($path))->toThrow(InvalidSpreadsheetException::class);

    unlink($path);
});

test('it rejects a sheet with more rows than the limit', function () {
    $rows = '';

    foreach (range(1, XlsxReader::MAX_ROWS + 1) as $number) {
        $rows .= '<row r="'.$number.'"><c r="A'.$number.'" t="inlineStr"><is><t>x</t></is></c></row>';
    }

    $path = excelStyleWorkbook([
        'xl/worksheets/sheet9.xml' => '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$rows.'</sheetData></worksheet>',
    ]);

    expect(fn () => (new XlsxReader)->firstSheetRows($path))->toThrow(InvalidSpreadsheetException::class);

    unlink($path);
});
