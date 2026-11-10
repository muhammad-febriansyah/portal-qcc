<?php

namespace App\Services;

use App\Enums\QccStep;
use App\Models\Project;
use App\Models\ReviewDecision;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Fills the proposal presentation template (resources/templates/proposal-presentation.pptx)
 * with one proposal. The template holds a {{token}} for every value; long texts get a smaller
 * font so they stay inside their card, and the schedule slide draws each QCC step as a bar.
 */
class ProposalPresentationBuilder
{
    public const TEMPLATE = 'templates/proposal-presentation.pptx';

    /** Months the schedule slide shows, four week columns each. */
    private const SCHEDULE_MONTHS = 7;

    private const BAR_COLOR = '1F4E79';

    /**
     * Space for each multi-line value: width and height in inches, largest and smallest font in points.
     *
     * @var array<string, array{0: float, 1: float, 2: int, 3: int}>
     */
    private const TEXT_BOXES = [
        'judul_proposal' => [9.7, 0.95, 22, 16],
        'judul_proyek' => [5.49, 0.42, 14, 10],
        'tema_improvement' => [5.49, 0.42, 14, 10],
        'latar_belakang' => [5.31, 3.0, 14, 10],
        'situasi_saat_ini_baseline' => [5.31, 3.0, 14, 10],
        'ruang_lingkup_proyek' => [5.31, 1.05, 14, 10],
        'rencana_perbaikan_arah_solusi' => [5.31, 4.3, 14, 10],
        'target_tujuan' => [5.31, 2.5, 14, 10],
        'proyeksi_kebutuhan_biaya' => [5.31, 1.85, 14, 10],
        'informasi_data_pelengkap' => [5.31, 4.3, 14, 10],
        'hal_yang_dipelajari_1' => [5.31, 0.5, 14, 9],
        'hal_yang_dipelajari_2' => [5.31, 0.5, 14, 9],
        'hal_yang_dipelajari_3' => [5.31, 0.5, 14, 9],
    ];

    /**
     * Build the presentation into a temporary file. The caller deletes it.
     */
    public function build(Project $project): string
    {
        $template = resource_path(self::TEMPLATE);
        $path = tempnam(sys_get_temp_dir(), 'qcc-proposal-');

        if ($path === false || ! copy($template, $path)) {
            throw new RuntimeException('Tidak dapat menyiapkan file presentasi.');
        }

        try {
            $archive = new ZipArchive;

            if ($archive->open($path) !== true) {
                throw new RuntimeException('Template presentasi tidak dapat dibuka.');
            }

            $project->loadMissing(['batch', 'proposer.organization', 'reviewDecisions.actor']);
            $values = $this->values($project);
            $schedule = $this->schedule($project);

            for ($index = 0; $index < $archive->numFiles; $index++) {
                $name = (string) $archive->getNameIndex($index);

                if (preg_match('#^ppt/slides/slide\d+\.xml$#', $name) !== 1) {
                    continue;
                }

                $xml = (string) $archive->getFromIndex($index);
                $xml = $this->fillSchedule($xml, $schedule);
                $xml = $this->fillText($xml, [...$values, ...$schedule['texts']]);
                $archive->addFromString($name, $xml);
            }

            if (! $archive->close()) {
                throw new RuntimeException('Presentasi tidak dapat disimpan.');
            }
        } catch (Throwable $exception) {
            @unlink($path);

            throw $exception;
        }

        return $path;
    }

    /**
     * Download name, e.g. "Proposal PRP-0027 - Digitalisasi absensi.pptx".
     */
    public function filename(Project $project): string
    {
        $title = Str::limit(trim((string) preg_replace('/[^\pL\pN ._-]+/u', ' ', (string) $project->title)), 80, '');

        return trim(sprintf('Proposal PRP-%04d - %s', $project->id, preg_replace('/\s+/', ' ', $title))).'.pptx';
    }

    /**
     * @return array<string, string>
     */
    private function values(Project $project): array
    {
        $proposer = $project->proposer;
        $batch = $project->batch;
        $learning = array_values(array_filter((array) $project->learning_goals_json, 'filled'));
        $decision = $project->reviewDecisions
            ->whereIn('decision', ['APPROVE', 'REJECT'])
            ->sortByDesc('decided_at')
            ->first();

        return [
            'batch_name' => Str::upper($batch?->name ?? 'QCC'),
            'batch_years' => $batch === null ? '' : collect([$batch->starts_on->year, $batch->ends_on->year])->unique()->implode('–'),
            'judul_proposal' => (string) $project->title,
            'nama' => $proposer?->name ?? '-',
            'sim_id' => $proposer?->sim_id === null ? '-' : 'SIM ID '.$proposer->sim_id,
            'lob_divisi' => $proposer?->organization?->name ?? '-',
            'kategori_tema' => $project->theme_category === null ? '' : 'Kategori tema: '.$project->theme_category->label(),
            'judul_proyek' => (string) $project->title,
            'tema_improvement' => $this->text($project->theme),
            'latar_belakang' => $this->text($project->background),
            'situasi_saat_ini_baseline' => $this->text($project->current_situation),
            'ruang_lingkup_proyek' => $this->text($project->scope),
            'rencana_perbaikan_arah_solusi' => $this->text($project->improvement_plan),
            'target_tujuan' => $this->text($project->target),
            'proyeksi_kebutuhan_biaya' => $this->text($project->cost_projection),
            'informasi_data_pelengkap' => $this->text($project->supplementary_information),
            'hal_yang_dipelajari_1' => '1. '.($learning[0] ?? '-'),
            'hal_yang_dipelajari_2' => '2. '.($learning[1] ?? '-'),
            'hal_yang_dipelajari_3' => '3. '.($learning[2] ?? '-'),
            'nama_pengusul' => $proposer?->name ?? '-',
            'jabatan_pengusul' => $proposer?->position ?? '-',
            'tanggal_pengajuan' => $project->submitted_at === null ? 'Belum diajukan' : 'Diajukan '.$this->date($project->submitted_at),
            'status_pengajuan' => $project->submitted_at === null ? 'DRAF' : 'SUDAH DIAJUKAN',
            ...$this->decisionValues($project, $decision),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function decisionValues(Project $project, ?ReviewDecision $decision): array
    {
        if ($decision === null) {
            return [
                'judul_keputusan' => 'KEPUTUSAN SELEKSI',
                'nama_approver' => 'Menunggu keputusan',
                'jabatan_approver' => 'Owner QCC',
                'tanggal_keputusan' => '-',
                'status_keputusan' => Str::upper($project->proposal_status->label()),
            ];
        }

        $approved = $decision->decision === 'APPROVE';

        return [
            'judul_keputusan' => $approved ? 'DISETUJUI OLEH' : 'DITOLAK OLEH',
            'nama_approver' => $decision->actor?->name ?? '-',
            'jabatan_approver' => $decision->actor?->position ?? '-',
            'tanggal_keputusan' => ($approved ? 'Disetujui ' : 'Ditolak ').$this->date($decision->decided_at),
            'status_keputusan' => $approved ? 'LOLOS SELEKSI' : 'TIDAK LOLOS',
        ];
    }

    /**
     * The eight QCC steps with their dates, and the months the schedule slide shows.
     *
     * @return array{texts: array<string, string>, bars: array<string, bool>}
     */
    private function schedule(Project $project): array
    {
        $timeline = collect((array) $project->step_timeline_json)->keyBy('step_number');
        $steps = [];
        $previousFinish = null;

        foreach (QccStep::cases() as $step) {
            $row = (array) $timeline->get($step->value, []);
            $finish = $this->parseDate($row['target_date'] ?? null);
            $start = $this->parseDate($row['start_date'] ?? null)
                ?? $previousFinish?->addDay()
                ?? $finish;

            if ($start !== null && $finish !== null && $start->gt($finish)) {
                $start = $finish;
            }

            $steps[$step->value] = ['step' => $step, 'start' => $start, 'finish' => $finish, 'deliverable' => (string) ($row['deliverable'] ?? '')];
            $previousFinish = $finish ?? $previousFinish;
        }

        $firstDate = collect($steps)->flatMap(fn (array $step): array => [$step['start'], $step['finish']])->filter()->min()
            ?? CarbonImmutable::parse($project->batch?->execution_starts_on ?? $project->submitted_at ?? now());
        $firstMonth = CarbonImmutable::parse($firstDate)->startOfMonth();

        $texts = [];
        $bars = [];

        for ($month = 1; $month <= self::SCHEDULE_MONTHS; $month++) {
            $texts["month_{$month}"] = $firstMonth->addMonths($month - 1)->format('M-y');
        }

        foreach ($steps as $number => $step) {
            $texts["step_label_{$number}"] = "{$number}. ".$step['step']->label();
            $texts["start_{$number}"] = $step['start'] === null ? '-' : $this->date($step['start']);
            $texts["finish_{$number}"] = $step['finish'] === null ? '-' : $this->date($step['finish']);
            $texts["deliverable_{$number}"] = $step['deliverable'] === '' ? '-' : $step['deliverable'];

            for ($column = 1; $column <= self::SCHEDULE_MONTHS * 4; $column++) {
                $month = $firstMonth->addMonths(intdiv($column - 1, 4));
                $week = ($column - 1) % 4;
                $weekStart = $month->addDays($week * 7);
                $weekEnd = $week === 3 ? $month->endOfMonth()->startOfDay() : $weekStart->addDays(6);

                $bars["bar_{$number}_{$column}"] = $step['start'] !== null && $step['finish'] !== null
                    && $step['start']->lte($weekEnd) && $step['finish']->gte($weekStart);
            }
        }

        return ['texts' => $texts, 'bars' => $bars];
    }

    /**
     * Colour the week cells a step runs through and empty the rest.
     *
     * @param  array{texts: array<string, string>, bars: array<string, bool>}  $schedule
     */
    private function fillSchedule(string $xml, array $schedule): string
    {
        if (! str_contains($xml, '{{bar_')) {
            return $xml;
        }

        return (string) preg_replace_callback(
            '#<a:tc>(?:(?!</a:tc>).)*?\{\{(bar_\d+_\d+)\}\}.*?</a:tc>#s',
            function (array $match) use ($schedule): string {
                $cell = str_replace('{{'.$match[1].'}}', '', $match[0]);

                if (! ($schedule['bars'][$match[1]] ?? false)) {
                    return $cell;
                }

                return str_replace('</a:tcPr>', '<a:solidFill><a:srgbClr val="'.self::BAR_COLOR.'"/></a:solidFill></a:tcPr>', $cell);
            },
            $xml,
        );
    }

    /**
     * Replace every token. A token alone in its paragraph becomes one paragraph per line,
     * in a font size that keeps the text inside its box.
     *
     * @param  array<string, string>  $values
     */
    private function fillText(string $xml, array $values): string
    {
        $xml = (string) preg_replace_callback(
            '#<a:p>((?:(?!</a:p>).)*?)<a:r>(<a:rPr\b[^>]*?(?:/>|>(?:(?!</a:rPr>).)*</a:rPr>))<a:t>\{\{([a-z0-9_]+)\}\}</a:t></a:r>((?:(?!</a:p>).)*?)</a:p>#s',
            function (array $match) use ($values): string {
                [$whole, $before, $runStyle, $key, $after] = $match;

                if (! array_key_exists($key, self::TEXT_BOXES) || preg_match('#<a:t>#', $before.$after) === 1) {
                    return $whole;
                }

                [$lines, $size] = $this->fit($values[$key] ?? '', ...self::TEXT_BOXES[$key]);
                $runStyle = $this->withSize($runStyle, $size);

                return implode('', array_map(
                    fn (string $line): string => $this->paragraph($line, $before, $runStyle, $after),
                    $lines,
                ));
            },
            $xml,
        );

        return (string) preg_replace_callback(
            '#\{\{([a-z0-9_]+)\}\}#',
            fn (array $match): string => $this->escape($this->plain($values[$match[1]] ?? '')),
            $xml,
        );
    }

    /**
     * One line of proposal text as a paragraph. Proposal text keeps a small Markdown subset
     * (see resources/js/lib/rich-text.ts): "- " starts a bullet, **bold** and *italic* style a run.
     */
    private function paragraph(string $line, string $before, string $runStyle, string $after): string
    {
        if (preg_match('/^\s*[-•]\s+(.*)$/u', $line, $bullet) === 1) {
            $line = $bullet[1];
            $before = (string) preg_replace(
                '#<a:pPr\b([^>]*?)\s*/>#',
                '<a:pPr$1 marL="285750" indent="-285750"><a:buFont typeface="Arial"/><a:buChar char="•"/></a:pPr>',
                $before,
                1,
            );
        }

        $runs = '';

        foreach (preg_split('/(\*\*[^*\n]+\*\*|\*[^*\n]+\*)/u', $line, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            $style = $runStyle;

            if (preg_match('/^\*\*(.+)\*\*$/u', $part, $bold) === 1) {
                [$part, $style] = [$bold[1], $this->withFlag($runStyle, 'b')];
            } elseif (preg_match('/^\*(.+)\*$/u', $part, $italic) === 1) {
                [$part, $style] = [$italic[1], $this->withFlag($runStyle, 'i')];
            }

            $runs .= '<a:r>'.$style.'<a:t xml:space="preserve">'.$this->escape($part).'</a:t></a:r>';
        }

        return '<a:p>'.$before.($runs === '' ? '' : $runs).$after.'</a:p>';
    }

    private function withFlag(string $runStyle, string $flag): string
    {
        return str_contains($runStyle, " {$flag}=\"")
            ? (string) preg_replace('/ '.$flag.'="\d"/', " {$flag}=\"1\"", $runStyle, 1)
            : (string) preg_replace('/^<a:rPr/', "<a:rPr {$flag}=\"1\"", $runStyle, 1);
    }

    /** Text as it reads, without the Markdown markers. */
    private function plain(string $text): string
    {
        $text = (string) preg_replace('/\*\*([^*\n]+)\*\*|\*([^*\n]+)\*/u', '$1$2', $text);

        return (string) preg_replace('/^\s*[-•]\s+/mu', '• ', $text);
    }

    /**
     * The largest font that fits the box, and the lines to write. Text too long even at the
     * smallest font is cut and ends with an ellipsis.
     *
     * @return array{0: list<string>, 1: int}
     */
    private function fit(string $text, float $width, float $height, int $largest, int $smallest): array
    {
        $lines = preg_split('/\R/u', $text === '' ? '-' : $text) ?: ['-'];

        for ($size = $largest; $size >= $smallest; $size--) {
            if ($this->height($lines, $width, $size) <= $height) {
                return [$lines, $size];
            }
        }

        $kept = [];

        foreach ($lines as $line) {
            $candidate = [...$kept, $line];

            if ($this->height($candidate, $width, $smallest) <= $height) {
                $kept = $candidate;

                continue;
            }

            $room = $this->charactersPerLine($width, $smallest)
                * max(0, (int) floor(($height - $this->height($kept, $width, $smallest)) / $this->lineHeight($smallest)));
            $kept[] = Str::limit($this->plain($line), max(0, $room - 1), '…');

            break;
        }

        return [$kept === [] ? ['…'] : $kept, $smallest];
    }

    /**
     * @param  list<string>  $lines
     */
    private function height(array $lines, float $width, int $size): float
    {
        $perLine = $this->charactersPerLine($width, $size);
        $count = array_sum(array_map(fn (string $line): int => max(1, (int) ceil(mb_strlen($this->plain($line)) / $perLine)), $lines));

        return $count * $this->lineHeight($size);
    }

    /** Average character is about half the font size wide. */
    private function charactersPerLine(float $width, int $size): int
    {
        return max(1, (int) floor($width / ($size / 72 * 0.52)));
    }

    private function lineHeight(int $size): float
    {
        return $size / 72 * 1.2;
    }

    private function withSize(string $runStyle, int $size): string
    {
        return str_contains($runStyle, ' sz="')
            ? (string) preg_replace('/ sz="\d+"/', ' sz="'.($size * 100).'"', $runStyle, 1)
            : (string) preg_replace('/^<a:rPr/', '<a:rPr sz="'.($size * 100).'"', $runStyle, 1);
    }

    private function text(?string $value): string
    {
        $value = trim(str_replace("\r\n", "\n", (string) $value));

        return $value === '' ? '-' : (string) preg_replace("/\n{3,}/", "\n\n", $value);
    }

    private function escape(string $value): string
    {
        $value = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function date(mixed $date): string
    {
        return CarbonImmutable::parse($date)->locale('id')->translatedFormat('j M Y');
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
