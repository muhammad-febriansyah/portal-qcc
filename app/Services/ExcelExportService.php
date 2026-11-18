<?php

namespace App\Services;

use BackedEnum;
use DateTimeInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use ZipArchive;

class ExcelExportService
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     */
    public function download(
        string $type,
        string $filename,
        string $title,
        string $subtitle,
        array $headers,
        iterable $rows,
        int $rowCount,
        array $filters,
        AuditService $audit,
        string $sheetName = 'Bi-weekly Report',
    ): BinaryFileResponse {
        $path = tempnam(sys_get_temp_dir(), 'qcc-excel-');

        if ($path === false) {
            throw new RuntimeException('Unable to create temporary Excel export.');
        }

        try {
            $archive = new ZipArchive;

            if ($archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to open temporary Excel export.');
            }

            foreach ($this->files($title, $subtitle, $headers, $rows, $sheetName) as $name => $contents) {
                if (! $archive->addFromString($name, $contents)) {
                    throw new RuntimeException('Unable to write Excel export.');
                }
            }

            if (! $archive->close()) {
                throw new RuntimeException('Unable to finalize Excel export.');
            }
        } catch (Throwable $exception) {
            @unlink($path);

            throw $exception;
        }

        $audit->record('data.exported', after: [
            'type' => $type,
            'format' => 'xlsx',
            'filters' => $filters,
            'row_count' => $rowCount,
        ], subjectLabel: "Ekspor Excel · {$type}");

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, private',
        ])->deleteFileAfterSend(true);
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     * @return array<string, string>
     */
    private function files(string $title, string $subtitle, array $headers, iterable $rows, string $sheetName): array
    {
        return [
            '[Content_Types].xml' => $this->contentTypesXml(),
            '_rels/.rels' => $this->rootRelationshipsXml(),
            'xl/workbook.xml' => $this->workbookXml($sheetName),
            'xl/_rels/workbook.xml.rels' => $this->workbookRelationshipsXml(),
            'xl/styles.xml' => $this->stylesXml(),
            'xl/worksheets/sheet1.xml' => $this->worksheetXml($title, $subtitle, $headers, $rows),
        ];
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     */
    private function worksheetXml(string $title, string $subtitle, array $headers, iterable $rows): string
    {
        $lastColumn = $this->columnName(count($headers));
        $sheetRows = [
            '<row r="1" ht="32" customHeight="1">'.$this->cell('A1', $title, 1).'</row>',
            '<row r="2" ht="30" customHeight="1">'.$this->cell('A2', $subtitle, 2).'</row>',
            '<row r="3" ht="8" customHeight="1"></row>',
        ];
        $headerCells = '';

        foreach ($headers as $index => $header) {
            $headerCells .= $this->cell($this->columnName($index + 1).'4', $header, 3);
        }

        $sheetRows[] = '<row r="4" ht="32" customHeight="1">'.$headerCells.'</row>';
        $rowNumber = 5;
        $contentLengths = [];
        $numberColumn = array_search('No.', $headers, true);

        foreach ($rows as $row) {
            $cells = '';

            foreach (array_values($row) as $index => $value) {
                $cells .= $this->cell(
                    $this->columnName($index + 1).$rowNumber,
                    $value,
                    ($rowNumber % 2 === 1 ? 4 : 5) + ($index === $numberColumn ? 2 : 0),
                );
                $contentLengths[$index] = max($contentLengths[$index] ?? 0, $this->displayLength($value));
            }

            $sheetRows[] = '<row r="'.$rowNumber.'">'.$cells.'</row>';
            $rowNumber++;
        }

        $lastRow = max($rowNumber - 1, 4);
        $columnDefinitions = '';

        foreach ($headers as $index => $header) {
            $width = $this->columnWidth($header, $contentLengths[$index] ?? 0);
            $column = $index + 1;
            $columnDefinitions .= '<col min="'.$column.'" max="'.$column.'" width="'.$width.'" customWidth="1"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<dimension ref="A1:'.$lastColumn.$lastRow.'"/>'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="4" topLeftCell="A5" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A5" sqref="A5"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="22"/><cols>'.$columnDefinitions.'</cols><sheetData>'.implode('', $sheetRows).'</sheetData>'
            .'<autoFilter ref="A4:'.$lastColumn.$lastRow.'"/>'
            .'<mergeCells count="2"><mergeCell ref="A1:'.$lastColumn.'1"/><mergeCell ref="A2:'.$lastColumn.'2"/></mergeCells>'
            .'<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
            .'<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/></worksheet>';
    }

    private function cell(string $reference, mixed $value, int $style): string
    {
        $styleAttribute = ' s="'.$style.'"';

        if ($value === null) {
            return '<c r="'.$reference.'"'.$styleAttribute.'/>';
        }

        if (is_bool($value)) {
            return '<c r="'.$reference.'"'.$styleAttribute.' t="b"><v>'.($value ? '1' : '0').'</v></c>';
        }

        if (is_int($value) || is_float($value)) {
            return '<c r="'.$reference.'"'.$styleAttribute.'><v>'.(string) $value.'</v></c>';
        }

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        } elseif ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s');
        } elseif (is_array($value)) {
            $value = implode(', ', array_map(static fn (mixed $item): string => (string) $item, $value));
        }

        $text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $value);
        $text = htmlspecialchars($text, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<c r="'.$reference.'"'.$styleAttribute.' t="inlineStr"><is><t xml:space="preserve">'.$text.'</t></is></c>';
    }

    /**
     * Wide enough for the header and the longest value, so most cells stay on one line;
     * very long text wraps inside a 50-character column instead of stretching the sheet.
     */
    private function columnWidth(string $header, int $contentLength): int
    {
        $width = min(50, max(mb_strlen($header) + 4, (int) ceil($contentLength * 1.2) + 3, 8));

        if (preg_match('/agenda|ringkasan|rencana|realisasi|isu|keputusan|action item/i', $header) === 1) {
            return max(34, $width);
        }

        return $width;
    }

    /**
     * Characters of the longest line a value shows in its cell.
     */
    private function displayLength(mixed $value): int
    {
        if ($value === null) {
            return 0;
        }

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        } elseif ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s');
        } elseif (is_array($value)) {
            $value = implode(', ', array_map(static fn (mixed $item): string => (string) $item, $value));
        } elseif (is_bool($value)) {
            return 5;
        }

        return max(array_map(mb_strlen(...), explode("\n", (string) $value)));
    }

    private function columnName(int $number): string
    {
        $name = '';

        while ($number > 0) {
            $remainder = ($number - 1) % 26;
            $name = chr(65 + $remainder).$name;
            $number = intdiv($number - 1, 26);
        }

        return $name;
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    /**
     * Excel refuses sheet names longer than 31 characters or holding any of : \ / ? * [ ].
     */
    private function workbookXml(string $sheetName): string
    {
        $name = trim(mb_substr((string) preg_replace('/[:\\\\\/?*\[\]]/', ' ', $sheetName), 0, 31));
        $name = htmlspecialchars($name === '' ? 'Data' : $name, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$name.'" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    /**
     * Title and column headers use the portal's primary color (#0179A3) with white text;
     * every second data row gets a light tint of it so long lists stay easy to follow.
     * Styles 6 and 7 are the centered "No." column of styles 4 and 5.
     */
    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="4">'
            .'<font><sz val="10"/><color rgb="FF172033"/><name val="Aptos"/></font>'
            .'<font><b/><sz val="16"/><color rgb="FFFFFFFF"/><name val="Aptos Display"/></font>'
            .'<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Aptos"/></font>'
            .'<font><sz val="10"/><color rgb="FF64748B"/><name val="Aptos"/></font>'
            .'</fonts>'
            .'<fills count="4">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF0179A3"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFEEF7FB"/><bgColor indexed="64"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="3">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left style="thin"><color rgb="FFE2E8F0"/></left><right style="thin"><color rgb="FFE2E8F0"/></right><top style="thin"><color rgb="FFE2E8F0"/></top><bottom style="thin"><color rgb="FFE2E8F0"/></bottom><diagonal/></border>'
            .'<border><left style="thin"><color rgb="FF01698E"/></left><right style="thin"><color rgb="FF01698E"/></right><top style="thin"><color rgb="FF01698E"/></top><bottom style="thin"><color rgb="FF01698E"/></bottom><diagonal/></border>'
            .'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="8">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="left" vertical="center" indent="1"/></xf>'
            .'<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="2" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }
}
