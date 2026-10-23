<?php

namespace App\Services\Spreadsheet;

use SimpleXMLElement;
use ZipArchive;

/**
 * Reads the first sheet of an .xlsx file as rows of text, without a spreadsheet package.
 * Formulas are not evaluated: the cached value stored in the file is returned.
 */
class XlsxReader
{
    public const MAX_ROWS = 2000;

    private const MAX_UNCOMPRESSED_BYTES = 20 * 1024 * 1024;

    private const MAX_ENTRIES = 200;

    private const MAIN_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const REL_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /**
     * @return list<array{row: int, cells: list<string>}> Rows that hold at least one value, with their sheet row number.
     *
     * @throws InvalidSpreadsheetException
     */
    public function firstSheetRows(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new InvalidSpreadsheetException('Not a valid .xlsx file.');
        }

        try {
            $this->guardArchiveSize($zip);

            $sheetPath = $this->firstSheetPath($zip);
            $sharedStrings = $this->sharedStrings($zip);
            $sheet = $this->xml($this->entry($zip, $sheetPath));

            return $this->rows($sheet, $sharedStrings);
        } finally {
            $zip->close();
        }
    }

    private function guardArchiveSize(ZipArchive $zip): void
    {
        if ($zip->numFiles > self::MAX_ENTRIES) {
            throw new InvalidSpreadsheetException('The workbook has too many parts.');
        }

        $total = 0;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $total += (int) ($zip->statIndex($index)['size'] ?? 0);
        }

        if ($total > self::MAX_UNCOMPRESSED_BYTES) {
            throw new InvalidSpreadsheetException('The workbook is too large.');
        }
    }

    private function entry(ZipArchive $zip, string $name): string
    {
        $contents = $zip->getFromName($name);

        if ($contents === false) {
            throw new InvalidSpreadsheetException("Missing workbook part {$name}.");
        }

        return $contents;
    }

    private function xml(string $contents): SimpleXMLElement
    {
        if (preg_match('/<!(DOCTYPE|ENTITY)/i', $contents) === 1) {
            throw new InvalidSpreadsheetException('The workbook contains unsupported XML declarations.');
        }

        $xml = simplexml_load_string($contents, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOBLANKS);

        if (! $xml instanceof SimpleXMLElement) {
            throw new InvalidSpreadsheetException('The workbook XML is not valid.');
        }

        $xml->registerXPathNamespace('m', self::MAIN_NS);

        return $xml;
    }

    private function firstSheetPath(ZipArchive $zip): string
    {
        $workbook = $this->xml($this->entry($zip, 'xl/workbook.xml'));
        $first = $workbook->xpath('//m:sheets/m:sheet[1]');
        $relationshipId = $first === false || $first === null || $first === []
            ? null
            : (string) $first[0]->attributes(self::REL_NS)->id;

        if ($relationshipId === null || $relationshipId === '') {
            throw new InvalidSpreadsheetException('The workbook has no sheet.');
        }

        $relationships = $this->xml($this->entry($zip, 'xl/_rels/workbook.xml.rels'));

        foreach ($relationships->children() as $relationship) {
            if ((string) $relationship['Id'] === $relationshipId) {
                $target = (string) $relationship['Target'];

                return str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
            }
        }

        throw new InvalidSpreadsheetException('The first sheet could not be located.');
    }

    /**
     * @return list<string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $contents = $zip->getFromName('xl/sharedStrings.xml');

        if ($contents === false) {
            return [];
        }

        $strings = [];

        foreach ($this->xml($contents)->children(self::MAIN_NS) as $item) {
            if ($item->getName() === 'si') {
                $strings[] = $this->richText($item);
            }
        }

        return $strings;
    }

    /**
     * @param  list<string>  $sharedStrings
     * @return list<array{row: int, cells: list<string>}>
     */
    private function rows(SimpleXMLElement $sheet, array $sharedStrings): array
    {
        $rows = [];
        $fallbackRow = 0;

        foreach ($sheet->xpath('//m:sheetData/m:row') ?: [] as $row) {
            $fallbackRow++;
            $rowNumber = (int) ($row['r'] ?? 0) ?: $fallbackRow;
            $fallbackRow = $rowNumber;
            $cells = [];
            $fallbackColumn = -1;

            foreach ($row->children(self::MAIN_NS) as $cell) {
                $fallbackColumn++;
                $reference = (string) ($cell->attributes()->r ?? '');
                $column = $reference !== '' ? $this->columnIndex($reference) : $fallbackColumn;
                $fallbackColumn = $column;
                $cells[$column] = $this->cellValue($cell, $sharedStrings);
            }

            if ($cells === [] || collect($cells)->every(static fn (string $value): bool => $value === '')) {
                continue;
            }

            if (count($rows) >= self::MAX_ROWS) {
                throw new InvalidSpreadsheetException('The sheet has too many rows.');
            }

            $list = [];

            for ($column = 0; $column <= max(array_keys($cells)); $column++) {
                $list[] = $cells[$column] ?? '';
            }

            $rows[] = ['row' => $rowNumber, 'cells' => $list];
        }

        return $rows;
    }

    /**
     * @param  list<string>  $sharedStrings
     */
    private function cellValue(SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) ($cell->attributes()->t ?? '');

        return match ($type) {
            's' => $sharedStrings[(int) $cell->children(self::MAIN_NS)->v] ?? '',
            'inlineStr' => $this->richText($cell->children(self::MAIN_NS)->is),
            'e' => '',
            default => trim((string) $cell->children(self::MAIN_NS)->v),
        };
    }

    /**
     * Text of a shared string or an inline string: plain text plus rich text runs,
     * without the phonetic hints Excel may add for some languages.
     */
    private function richText(SimpleXMLElement $node): string
    {
        $text = '';

        foreach ($node->children(self::MAIN_NS) as $child) {
            if ($child->getName() === 't') {
                $text .= (string) $child;
            } elseif ($child->getName() === 'r') {
                $text .= (string) $child->children(self::MAIN_NS)->t;
            }
        }

        return trim($text);
    }

    private function columnIndex(string $reference): int
    {
        preg_match('/^([A-Za-z]+)/', $reference, $match);
        $index = 0;

        foreach (str_split(strtoupper($match[1] ?? 'A')) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }
}
