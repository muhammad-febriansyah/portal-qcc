<?php

namespace App\Services\Spreadsheet;

use ZipArchive;

/**
 * Writes a small, plain .xlsx workbook (text and numbers, a styled header row and
 * dropdown validation) without a spreadsheet package.
 *
 * @phpstan-type SheetDefinition array{
 *     name: string,
 *     rows: list<list<string|int|float|null>>,
 *     widths?: list<float|int>,
 *     validations?: list<array{sqref: string, values: list<string>}>,
 *     header_rows?: int
 * }
 */
class XlsxWriter
{
    private const MAIN_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const REL_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const STYLE_HEADER = 1;

    private const STYLE_WRAP = 2;

    /**
     * @param  list<SheetDefinition>  $sheets
     */
    public function write(string $path, array $sheets): void
    {
        if ($sheets === []) {
            throw new InvalidSpreadsheetException('Workbook needs at least one sheet.');
        }

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new InvalidSpreadsheetException('Could not create the workbook file.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes(count($sheets)));
        $zip->addFromString('_rels/.rels', $this->rootRelationships());
        $zip->addFromString('xl/workbook.xml', $this->workbook($sheets));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships(count($sheets)));
        $zip->addFromString('xl/styles.xml', $this->styles());

        foreach ($sheets as $index => $sheet) {
            $zip->addFromString('xl/worksheets/sheet'.($index + 1).'.xml', $this->worksheet($sheet, $index === 0));
        }

        $zip->close();
    }

    private function contentTypes(int $sheetCount): string
    {
        $sheets = '';

        for ($number = 1; $number <= $sheetCount; $number++) {
            $sheets .= '<Override PartName="/xl/worksheets/sheet'.$number.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .$sheets
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="'.self::REL_NS.'/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    /**
     * @param  list<SheetDefinition>  $sheets
     */
    private function workbook(array $sheets): string
    {
        $entries = '';

        foreach ($sheets as $index => $sheet) {
            $number = $index + 1;
            $entries .= '<sheet name="'.$this->attribute($this->sheetName($sheet['name'])).'" sheetId="'.$number.'" r:id="rId'.$number.'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="'.self::MAIN_NS.'" xmlns:r="'.self::REL_NS.'">'
            .'<bookViews><workbookView activeTab="0"/></bookViews>'
            .'<sheets>'.$entries.'</sheets>'
            .'</workbook>';
    }

    private function workbookRelationships(int $sheetCount): string
    {
        $relationships = '';

        for ($number = 1; $number <= $sheetCount; $number++) {
            $relationships .= '<Relationship Id="rId'.$number.'" Type="'.self::REL_NS.'/worksheet" Target="worksheets/sheet'.$number.'.xml"/>';
        }

        $relationships .= '<Relationship Id="rId'.($sheetCount + 1).'" Type="'.self::REL_NS.'/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relationships.'</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="'.self::MAIN_NS.'">'
            .'<fonts count="2">'
            .'<font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="3">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF0B5CF0"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="3">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    /**
     * @param  SheetDefinition  $sheet
     */
    private function worksheet(array $sheet, bool $selected): string
    {
        $headerRows = $sheet['header_rows'] ?? 1;
        $view = '<sheetView workbookViewId="0"'.($selected ? ' tabSelected="1"' : '').'>';

        if ($headerRows > 0) {
            $view .= '<pane ySplit="'.$headerRows.'" topLeftCell="A'.($headerRows + 1).'" activePane="bottomLeft" state="frozen"/>';
        }

        $view .= '</sheetView>';

        $columns = '';

        foreach (($sheet['widths'] ?? []) as $index => $width) {
            $columns .= '<col min="'.($index + 1).'" max="'.($index + 1).'" width="'.$width.'" customWidth="1"/>';
        }

        $rows = '';

        foreach ($sheet['rows'] as $rowIndex => $cells) {
            $rowNumber = $rowIndex + 1;
            $style = $rowNumber <= $headerRows ? self::STYLE_HEADER : self::STYLE_WRAP;
            $xml = '';

            foreach ($cells as $columnIndex => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                $reference = $this->columnLetters($columnIndex).$rowNumber;

                if (is_int($value) || is_float($value)) {
                    $xml .= '<c r="'.$reference.'" s="'.$style.'"><v>'.$value.'</v></c>';

                    continue;
                }

                $xml .= '<c r="'.$reference.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$this->text($value).'</t></is></c>';
            }

            $rows .= '<row r="'.$rowNumber.'">'.$xml.'</row>';
        }

        $validations = '';

        foreach ($sheet['validations'] ?? [] as $validation) {
            $validations .= '<dataValidation type="list" allowBlank="1" showErrorMessage="1" errorTitle="Nilai tidak valid" error="Pilih salah satu nilai dari daftar."'
                .' sqref="'.$this->attribute($validation['sqref']).'"><formula1>"'.$this->text(implode(',', $validation['values'])).'"</formula1></dataValidation>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="'.self::MAIN_NS.'">'
            .'<sheetViews>'.$view.'</sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>'
            .($columns === '' ? '' : '<cols>'.$columns.'</cols>')
            .'<sheetData>'.$rows.'</sheetData>'
            .($validations === '' ? '' : '<dataValidations count="'.count($sheet['validations'] ?? []).'">'.$validations.'</dataValidations>')
            .'</worksheet>';
    }

    private function columnLetters(int $index): string
    {
        $letters = '';

        for ($number = $index + 1; $number > 0; $number = intdiv($number - 1, 26)) {
            $letters = chr(65 + (($number - 1) % 26)).$letters;
        }

        return $letters;
    }

    private function sheetName(string $name): string
    {
        $clean = trim((string) preg_replace('/[\[\]:*?\/\\\\]/', ' ', $name));

        return mb_substr($clean === '' ? 'Sheet' : $clean, 0, 31);
    }

    private function text(string $value): string
    {
        $clean = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', mb_substr($value, 0, 32767));

        return htmlspecialchars($clean, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function attribute(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
