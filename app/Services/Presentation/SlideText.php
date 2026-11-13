<?php

namespace App\Services\Presentation;

/**
 * Proposal text for slides: paragraphs from the small Markdown subset the portal writes
 * (see resources/js/lib/rich-text.ts: "- " bullets, **bold**, *italic*), their estimated
 * size on a slide, splitting where a box runs out of room, and the DrawingML to write them.
 *
 * Sizes are estimated on the generous side (average Calibri glyph about half an em, words
 * wrapped whole), so text fits its box in PowerPoint and never runs past it.
 */
class SlideText
{
    public const EMU_PER_INCH = 914400;

    /** Average glyph width as a share of the font size, kept wide for a safe estimate. */
    private const GLYPH_WIDTH = 0.53;

    private const LINE_SPACING = 1.2;

    /** Space after each paragraph, in points. */
    public const PARAGRAPH_GAP = 3;

    /** Bullet indent, in inches. */
    public const BULLET_INDENT = 0.22;

    /**
     * @return list<array{text: string, bullet: bool}>
     */
    public static function paragraphs(?string $text): array
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", (string) $text));
        $text = (string) preg_replace("/\n{3,}/", "\n\n", $text);

        if ($text === '') {
            return [['text' => '-', 'bullet' => false]];
        }

        return array_map(function (string $line): array {
            if (preg_match('/^\s*[-•]\s+(.*)$/u', $line, $bullet) === 1) {
                return ['text' => trim($bullet[1]), 'bullet' => true];
            }

            return ['text' => rtrim($line), 'bullet' => false];
        }, explode("\n", $text));
    }

    /**
     * Height in inches of the paragraphs set at $size points in a box $width inches wide.
     *
     * @param  list<array{text: string, bullet: bool}>  $paragraphs
     */
    public static function height(array $paragraphs, float $width, float $size): float
    {
        $height = 0.0;

        foreach ($paragraphs as $paragraph) {
            $height += self::lineCount($paragraph, $width, $size) * self::lineHeight($size) + self::PARAGRAPH_GAP / 72;
        }

        return $height;
    }

    /**
     * Split the paragraphs at the point where $maxHeight inches is used up. Paragraphs that do
     * not fit whole are broken between words, keeping bold and italic marks balanced.
     *
     * @param  list<array{text: string, bullet: bool}>  $paragraphs
     * @return array{0: list<array{text: string, bullet: bool}>, 1: list<array{text: string, bullet: bool}>}
     */
    public static function split(array $paragraphs, float $width, float $size, float $maxHeight): array
    {
        $kept = [];
        $used = 0.0;

        foreach ($paragraphs as $index => $paragraph) {
            $height = self::lineCount($paragraph, $width, $size) * self::lineHeight($size) + self::PARAGRAPH_GAP / 72;

            if ($used + $height <= $maxHeight) {
                $kept[] = $paragraph;
                $used += $height;

                continue;
            }

            $rest = array_slice($paragraphs, $index + 1);
            $roomLines = (int) floor(($maxHeight - $used - self::PARAGRAPH_GAP / 72) / self::lineHeight($size));

            if ($roomLines >= 1 && $paragraph['text'] !== '') {
                [$head, $tail] = self::splitParagraph($paragraph, $width, $size, $roomLines);

                if ($head !== null) {
                    $kept[] = $head;
                }

                array_unshift($rest, $tail);
            } else {
                array_unshift($rest, $paragraph);
            }

            return [self::trimBlank($kept), self::trimBlank($rest)];
        }

        return [self::trimBlank($kept), []];
    }

    public static function lineHeight(float $size): float
    {
        return $size * self::LINE_SPACING / 72;
    }

    /**
     * Paragraph XML for one paragraph, with bold and italic runs and an optional bullet.
     *
     * @param  array{text: string, bullet: bool}  $paragraph
     */
    public static function paragraphXml(array $paragraph, float $size, string $color, string $align = 'l'): string
    {
        $spacing = '<a:spcAft><a:spcPts val="'.(self::PARAGRAPH_GAP * 100).'"/></a:spcAft>';
        $properties = $paragraph['bullet']
            ? '<a:pPr marL="'.self::emu(self::BULLET_INDENT).'" indent="-'.self::emu(self::BULLET_INDENT).'" algn="'.$align.'">'.$spacing.'<a:buFont typeface="Arial"/><a:buChar char="•"/></a:pPr>'
            : '<a:pPr algn="'.$align.'">'.$spacing.'<a:buNone/></a:pPr>';

        $runs = '';

        foreach (preg_split('/(\*\*[^*\n]+\*\*|\*[^*\n]+\*)/u', $paragraph['text'], -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            $bold = false;
            $italic = false;

            if (preg_match('/^\*\*(.+)\*\*$/u', $part, $match) === 1) {
                [$part, $bold] = [$match[1], true];
            } elseif (preg_match('/^\*(.+)\*$/u', $part, $match) === 1) {
                [$part, $italic] = [$match[1], true];
            }

            $runs .= '<a:r>'.self::runProperties($size, $color, $bold, $italic).'<a:t xml:space="preserve">'.self::escape($part).'</a:t></a:r>';
        }

        return '<a:p>'.$properties.$runs.'<a:endParaRPr lang="id-ID" sz="'.(int) round($size * 100).'" dirty="0"/></a:p>';
    }

    public static function runProperties(float $size, string $color, bool $bold = false, bool $italic = false, int $spacing = 0): string
    {
        return '<a:rPr lang="id-ID" sz="'.(int) round($size * 100).'" b="'.($bold ? '1' : '0').'" i="'.($italic ? '1' : '0').'"'
            .($spacing !== 0 ? ' spc="'.$spacing.'"' : '').' dirty="0"><a:solidFill><a:srgbClr val="'.$color.'"/></a:solidFill>'
            .'<a:latin typeface="Calibri"/><a:cs typeface="Calibri"/></a:rPr>';
    }

    /**
     * Text without the bold and italic marks.
     */
    public static function plain(string $text): string
    {
        return (string) preg_replace('/\*\*([^*\n]+)\*\*|\*([^*\n]+)\*/u', '$1$2', $text);
    }

    public static function escape(string $value): string
    {
        $value = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    public static function emu(float $inches): int
    {
        return (int) round($inches * self::EMU_PER_INCH);
    }

    /**
     * Lines a paragraph takes, wrapping whole words like PowerPoint does.
     *
     * @param  array{text: string, bullet: bool}  $paragraph
     */
    public static function lineCount(array $paragraph, float $width, float $size): int
    {
        return count(self::wrap(self::plain($paragraph['text']), self::charactersPerLine($paragraph, $width, $size)));
    }

    /**
     * @param  array{text: string, bullet: bool}  $paragraph
     */
    private static function charactersPerLine(array $paragraph, float $width, float $size): int
    {
        $usable = $width - ($paragraph['bullet'] ? self::BULLET_INDENT : 0);

        return max(1, (int) floor($usable / ($size / 72 * self::GLYPH_WIDTH)));
    }

    /**
     * @return list<string>
     */
    private static function wrap(string $text, int $perLine): array
    {
        if (trim($text) === '') {
            return [''];
        }

        $lines = [];
        $line = '';

        foreach (preg_split('/\s+/u', trim($text)) ?: [] as $word) {
            while (mb_strlen($word) > $perLine) {
                if ($line !== '') {
                    $lines[] = $line;
                    $line = '';
                }

                $lines[] = mb_substr($word, 0, $perLine);
                $word = mb_substr($word, $perLine);
            }

            $candidate = $line === '' ? $word : $line.' '.$word;

            if (mb_strlen($candidate) <= $perLine) {
                $line = $candidate;
            } else {
                $lines[] = $line;
                $line = $word;
            }
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * The words of a paragraph that fill $lines lines, and the rest as a continuing paragraph.
     *
     * @param  array{text: string, bullet: bool}  $paragraph
     * @return array{0: array{text: string, bullet: bool}|null, 1: array{text: string, bullet: bool}}
     */
    private static function splitParagraph(array $paragraph, float $width, float $size, int $lines): array
    {
        $words = preg_split('/\s+/u', trim($paragraph['text'])) ?: [];
        $taken = [];

        foreach ($words as $word) {
            $candidate = ['text' => implode(' ', [...$taken, $word]), 'bullet' => $paragraph['bullet']];

            if (self::lineCount($candidate, $width, $size) > $lines) {
                break;
            }

            $taken[] = $word;
        }

        if ($taken === []) {
            return [null, $paragraph];
        }

        $head = implode(' ', $taken);
        $tail = implode(' ', array_slice($words, count($taken)));

        // A bold or italic span cut in two is closed in the first part and reopened in the second.
        foreach (['**', '*'] as $mark) {
            $count = $mark === '**'
                ? substr_count($head, '**')
                : substr_count(str_replace('**', '', $head), '*');

            if ($count % 2 === 1) {
                $head .= $mark;
                $tail = $mark.$tail;
            }
        }

        return [
            ['text' => $head, 'bullet' => $paragraph['bullet']],
            // The rest of a bullet continues the same item, so it is not bulleted again.
            ['text' => $tail, 'bullet' => false],
        ];
    }

    /**
     * @param  list<array{text: string, bullet: bool}>  $paragraphs
     * @return list<array{text: string, bullet: bool}>
     */
    private static function trimBlank(array $paragraphs): array
    {
        while ($paragraphs !== [] && trim($paragraphs[0]['text']) === '') {
            array_shift($paragraphs);
        }

        while ($paragraphs !== [] && trim($paragraphs[array_key_last($paragraphs)]['text']) === '') {
            array_pop($paragraphs);
        }

        return array_values($paragraphs);
    }
}
