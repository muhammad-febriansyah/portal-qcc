<?php

namespace App\Services;

use App\Enums\ProposalStatus;
use App\Enums\QccStep;
use App\Models\Project;
use App\Models\ProjectMentor;
use App\Models\ReviewDecision;
use App\Services\Presentation\SlidePackage;
use App\Services\Presentation\SlideText;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Builds the proposal deck from the template (resources/templates/proposal-presentation.pptx):
 * a cover, the proposal's sections as bordered white cards flowing over two columns, the QCC
 * step schedule as a weekly gantt table, the approval slide and a closing slide.
 *
 * Nothing is ever cut short: a card that does not fit continues in the next column or on the
 * next slide, and a schedule too tall for one slide continues on the next, with its header
 * repeated.
 */
class ProposalPresentationBuilder
{
    public const TEMPLATE = 'templates/proposal-presentation.pptx';

    /** Template slides, by file number. */
    private const COVER = 1;

    private const FRAME = 2;

    private const APPROVAL = 3;

    private const CLOSING = 4;

    /** Colours of the QCC Batch 4 template: labels and gantt bars, navy headings, card border. */
    public const PRIMARY = '1F4E79';

    private const NAVY = '17365D';

    private const TEXT = '2B3440';

    private const MUTED = '64707D';

    private const TABLE_HEADER = 'D9E1F2';

    private const TABLE_LINE = 'A9B6C6';

    /** Content area of a frame slide, in inches. */
    private const LEFT = 0.6;

    private const WIDTH = 12.13;

    /** Cards start under the title; the schedule under its subtitle. */
    private const TOP = 1.6;

    private const TABLE_TOP = 1.75;

    private const BOTTOM = 6.95;

    private const COLUMN_GAP = 0.3;

    private const CARD_GAP = 0.2;

    private const CARD_PADDING_X = 0.2;

    private const CARD_PADDING_Y = 0.16;

    private const LABEL_SIZE = 12;

    private const LABEL_GAP = 6;

    private const BODY_SIZE = 12;

    /** A card continued elsewhere still shows at least this many lines where it starts. */
    private const MIN_LINES = 3;

    /** Months the schedule shows, four week columns each. */
    private const SCHEDULE_MONTHS = 7;

    private const TABLE_SIZE = 8;

    /** Schedule columns: step, one per week, start, finish, deliverable (inches). */
    private const STEP_COLUMN = 2.55;

    private const WEEK_COLUMN = 0.2;

    private const DATE_COLUMN = 0.82;

    private const HEADER_ROW = 0.28;

    private const MIN_ROW = 0.3;

    /**
     * Single values on the cover and approval slides that shrink to fit their box:
     * width and height in inches, largest and smallest font in points.
     *
     * @var array<string, array{0: float, 1: float, 2: int, 3: int}>
     */
    private const TEXT_BOXES = [
        'judul_proposal' => [9.7, 0.85, 22, 14],
        'nama_approver' => [3.3, 0.45, 15, 10],
    ];

    private int $shapeId = 100;

    /**
     * Build the presentation into a temporary file. The caller deletes it.
     */
    public function build(Project $project): string
    {
        $path = tempnam(sys_get_temp_dir(), 'qcc-proposal-');

        if ($path === false || ! copy(resource_path(self::TEMPLATE), $path)) {
            throw new RuntimeException('Tidak dapat menyiapkan file presentasi.');
        }

        try {
            $archive = new ZipArchive;

            if ($archive->open($path) !== true) {
                throw new RuntimeException('Template presentasi tidak dapat dibuka.');
            }

            $project->loadMissing(['batch', 'proposer.organization.mentors', 'reviewDecisions', 'mentorAssignments.mentor']);
            $package = new SlidePackage($archive);
            $package->replaceSlides($this->slides($project, $package));

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
     * @return list<array{xml: string, rels: string}>
     */
    private function slides(Project $project, SlidePackage $package): array
    {
        $values = $this->values($project);
        $frame = $package->templateSlide(self::FRAME);
        $frameRels = $package->templateRelationships(self::FRAME);
        $pages = [];

        foreach ($this->sectionPages($this->sections($project)) as $index => $cards) {
            $pages[] = ['title' => $index === 0 ? 'Proposal Proyek' : 'Proposal Proyek (lanjutan)', 'subtitle' => '', 'body' => $cards];
        }

        foreach ($this->schedulePages($project) as $index => $table) {
            $pages[] = ['title' => 'Jadwal Proyek', 'subtitle' => $index === 0 ? 'Rencana aktivitas dan target waktu pelaksanaan proyek.' : 'Rencana aktivitas (lanjutan).', 'body' => $table];
        }

        $slides = [['xml' => $this->fill($package->templateSlide(self::COVER), $values), 'rels' => $package->templateRelationships(self::COVER)]];
        $number = 1;

        foreach ($pages as $page) {
            $xml = $this->fill($frame, [
                ...$values,
                'slide_title' => $page['title'],
                'slide_subtitle' => $page['subtitle'],
                'page_number' => sprintf('%02d', $number++),
            ]);
            $slides[] = ['xml' => str_replace('</p:spTree>', $page['body'].'</p:spTree>', $xml), 'rels' => $frameRels];
        }

        $slides[] = ['xml' => $this->fill($package->templateSlide(self::APPROVAL), [...$values, 'page_number' => sprintf('%02d', $number)]), 'rels' => $package->templateRelationships(self::APPROVAL)];
        $slides[] = ['xml' => $this->fill($package->templateSlide(self::CLOSING), $values), 'rels' => $package->templateRelationships(self::CLOSING)];

        return $slides;
    }

    /**
     * @return array<string, string>
     */
    private function values(Project $project): array
    {
        $proposer = $project->proposer;
        $batch = $project->batch;
        $approval = $project->reviewDecisions
            ->where('decision', 'APPROVE')
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
            'nama_pengusul' => $proposer?->name ?? '-',
            'jabatan_pengusul' => $proposer?->position ?? '-',
            'tanggal_pengajuan' => $project->submitted_at === null ? 'Belum diajukan' : 'Diajukan '.$this->date($project->submitted_at),
            ...$this->mentorValues($project, $approval),
        ];
    }

    /**
     * The mentor card: the LOB/Div mentors who review the proposal. The proposal status is not
     * shown; only the approval date appears once the proposal is approved.
     *
     * @return array<string, string>
     */
    private function mentorValues(Project $project, ?ReviewDecision $approval): array
    {
        $organization = $project->proposer?->organization;
        $mentors = $project->mentorAssignments
            ->where('status', 'ACTIVE')
            ->map(fn (ProjectMentor $assignment): ?string => $assignment->mentor?->name)
            ->filter()
            ->values();

        if ($mentors->isEmpty()) {
            $mentors = ($organization?->mentors ?? collect())->pluck('name');
        }

        $approved = $project->proposal_status === ProposalStatus::Approved && $approval !== null;

        return [
            'judul_keputusan' => 'MENTOR',
            'nama_approver' => $mentors->isEmpty() ? 'Belum ditentukan' : $mentors->unique()->implode("\n"),
            // The mentor heads the proposer's LOB/Div, e.g. "LOB JAGOTI HEAD".
            'jabatan_approver' => $organization === null ? 'LOB/DIV HEAD' : Str::upper($organization->name).' HEAD',
            'tanggal_keputusan' => $approved ? 'Disetujui '.$this->date($approval->decided_at) : '',
        ];
    }

    /**
     * The proposal's sections in reading order, each a label and its text.
     *
     * @return list<array{label: string, text: string}>
     */
    private function sections(Project $project): array
    {
        $learning = array_values(array_filter((array) $project->learning_goals_json, 'filled'));

        $sections = [
            ['label' => 'Judul Proyek', 'text' => (string) $project->title],
            ['label' => 'Tema Improvement', 'text' => (string) $project->theme],
            ['label' => 'Latar Belakang', 'text' => (string) $project->background],
            ['label' => 'Situasi Saat Ini / Baseline', 'text' => (string) $project->current_situation],
            // Sections removed from the form; still shown for proposals written before the change.
            ['label' => 'Pernyataan Masalah', 'text' => (string) $project->problem_statement, 'legacy' => true],
            ['label' => 'Root Cause Analysis (RCA)', 'text' => (string) $project->rca, 'legacy' => true],
            ['label' => 'Rencana Perbaikan / Arah Solusi', 'text' => (string) $project->improvement_plan],
            ['label' => 'Ruang Lingkup Proyek', 'text' => (string) $project->scope],
            ['label' => 'Target / Tujuan', 'text' => (string) $project->target],
            ['label' => 'Manfaat Bisnis', 'text' => (string) $project->business_benefit, 'legacy' => true],
            ['label' => 'Informasi Data Pelengkap', 'text' => (string) $project->supplementary_information],
            ['label' => 'Proyeksi Kebutuhan Biaya', 'text' => (string) $project->cost_projection],
            ['label' => '3 Hal yang Akan Dipelajari', 'text' => implode("\n", array_map(
                fn (int $index): string => ($index + 1).'. '.($learning[$index] ?? '-'),
                [0, 1, 2],
            ))],
        ];

        return array_values(array_map(
            fn (array $section): array => ['label' => $section['label'], 'text' => $section['text']],
            array_filter($sections, fn (array $section): bool => ! ($section['legacy'] ?? false) || filled($section['text'])),
        ));
    }

    /**
     * Lay the sections out as cards over two columns, column by column and slide by slide.
     * A section that does not fit where it starts is split, and continues at the top of the
     * next column with "(lanjutan)" after its label.
     *
     * @param  list<array{label: string, text: string}>  $sections
     * @return list<string> The card shapes of each slide.
     */
    private function sectionPages(array $sections): array
    {
        $columnWidth = (self::WIDTH - self::COLUMN_GAP) / 2;
        $textWidth = $columnWidth - 2 * self::CARD_PADDING_X;
        $chrome = 2 * self::CARD_PADDING_Y + SlideText::lineHeight(self::LABEL_SIZE) + self::LABEL_GAP / 72;
        $pages = [];
        $page = '';
        $column = 0;
        $y = self::TOP;

        $nextColumn = function () use (&$pages, &$page, &$column, &$y): void {
            if ($column === 0) {
                $column = 1;
            } else {
                $pages[] = $page;
                $page = '';
                $column = 0;
            }

            $y = self::TOP;
        };

        foreach ($sections as $section) {
            $paragraphs = SlideText::paragraphs($section['text']);
            $label = Str::upper($section['label']);

            while ($paragraphs !== []) {
                $x = self::LEFT + $column * ($columnWidth + self::COLUMN_GAP);
                $room = self::BOTTOM - $y;
                $height = $chrome + SlideText::height($paragraphs, $textWidth, self::BODY_SIZE);

                if ($height <= $room) {
                    $page .= $this->card($label, $paragraphs, $x, $y, $columnWidth, $height);
                    $y += $height + self::CARD_GAP;
                    $paragraphs = [];

                    continue;
                }

                $columnIsEmpty = $y === self::TOP;

                if ($room >= $chrome + self::MIN_LINES * SlideText::lineHeight(self::BODY_SIZE) || $columnIsEmpty) {
                    [$fits, $rest] = SlideText::split($paragraphs, $textWidth, self::BODY_SIZE, $room - $chrome);

                    if ($fits === [] && $columnIsEmpty) {
                        // Cannot happen with real text (a column holds over 20 lines), but never loop.
                        [$fits, $rest] = [$paragraphs, []];
                    }

                    if ($fits !== []) {
                        $page .= $this->card($label, $fits, $x, $y, $columnWidth, $room);
                        $paragraphs = $rest;
                        $label = Str::upper($section['label']).' (LANJUTAN)';
                    }
                }

                $nextColumn();
            }
        }

        if ($page !== '') {
            $pages[] = $page;
        }

        return $pages;
    }

    /**
     * A bordered white card holding a label and its paragraphs.
     *
     * @param  list<array{text: string, bullet: bool}>  $paragraphs
     */
    private function card(string $label, array $paragraphs, float $x, float $y, float $width, float $height): string
    {
        $id = $this->shapeId++;
        $label = '<a:p><a:pPr algn="l"><a:spcAft><a:spcPts val="'.(self::LABEL_GAP * 100).'"/></a:spcAft><a:buNone/></a:pPr>'
            .'<a:r>'.SlideText::runProperties(self::LABEL_SIZE, self::PRIMARY, true).'<a:t>'.SlideText::escape($label).'</a:t></a:r></a:p>';
        $body = implode('', array_map(
            fn (array $paragraph): string => SlideText::paragraphXml($paragraph, self::BODY_SIZE, self::TEXT),
            $paragraphs,
        ));

        return '<p:sp><p:nvSpPr><p:cNvPr id="'.$id.'" name="Card '.$id.'"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr>'
            .'<p:spPr><a:xfrm><a:off x="'.SlideText::emu($x).'" y="'.SlideText::emu($y).'"/><a:ext cx="'.SlideText::emu($width).'" cy="'.SlideText::emu($height).'"/></a:xfrm>'
            .'<a:prstGeom prst="roundRect"><a:avLst><a:gd name="adj" fmla="val 8000"/></a:avLst></a:prstGeom>'
            // Flat white card: no border and no shadow.
            .'<a:solidFill><a:srgbClr val="FFFFFF"/></a:solidFill><a:ln><a:noFill/></a:ln></p:spPr>'
            .'<p:txBody><a:bodyPr wrap="square" lIns="'.SlideText::emu(self::CARD_PADDING_X).'" tIns="'.SlideText::emu(self::CARD_PADDING_Y).'" rIns="'.SlideText::emu(self::CARD_PADDING_X).'" bIns="'.SlideText::emu(self::CARD_PADDING_Y).'" anchor="t" rtlCol="0"><a:noAutofit/></a:bodyPr><a:lstStyle/>'
            .$label.$body.'</p:txBody></p:sp>';
    }

    /**
     * The schedule as gantt tables, one per slide, each with the month and week header.
     *
     * @return list<string>
     */
    private function schedulePages(Project $project): array
    {
        $schedule = $this->schedule($project);
        $deliverableWidth = self::WIDTH - self::STEP_COLUMN - self::SCHEDULE_MONTHS * 4 * self::WEEK_COLUMN - 2 * self::DATE_COLUMN;
        $room = self::BOTTOM - self::TABLE_TOP - 2 * self::HEADER_ROW;
        $pages = [];
        $rows = '';
        $used = 0.0;

        foreach ($schedule['steps'] as $step) {
            $lines = max(
                SlideText::lineCount(['text' => $step['label'], 'bullet' => false], self::STEP_COLUMN - 0.12, self::TABLE_SIZE),
                SlideText::lineCount(['text' => $step['deliverable'], 'bullet' => false], $deliverableWidth - 0.12, self::TABLE_SIZE),
            );
            $height = max(self::MIN_ROW, $lines * SlideText::lineHeight(self::TABLE_SIZE) + 0.1);

            if ($rows !== '' && $used + $height > $room) {
                $pages[] = $this->scheduleTable($schedule['months'], $rows, $used, $deliverableWidth);
                $rows = '';
                $used = 0.0;
            }

            $rows .= $this->scheduleRow($step, $height);
            $used += $height;
        }

        $pages[] = $this->scheduleTable($schedule['months'], $rows, $used, $deliverableWidth);

        return $pages;
    }

    /**
     * @param  list<string>  $months
     */
    private function scheduleTable(array $months, string $rows, float $rowsHeight, float $deliverableWidth): string
    {
        $id = $this->shapeId++;
        $weeks = self::SCHEDULE_MONTHS * 4;
        $grid = '<a:gridCol w="'.SlideText::emu(self::STEP_COLUMN).'"/>'
            .str_repeat('<a:gridCol w="'.SlideText::emu(self::WEEK_COLUMN).'"/>', $weeks)
            .str_repeat('<a:gridCol w="'.SlideText::emu(self::DATE_COLUMN).'"/>', 2)
            .'<a:gridCol w="'.SlideText::emu($deliverableWidth).'"/>';

        $monthRow = $this->cell('Step QCC', header: true, align: 'l', rowSpan: 2);

        foreach ($months as $month) {
            $monthRow .= $this->cell($month, header: true, gridSpan: 4).str_repeat($this->cell('', header: true, hMerge: true, narrow: true), 3);
        }

        $monthRow .= $this->cell('Mulai', header: true, rowSpan: 2).$this->cell('Selesai', header: true, rowSpan: 2).$this->cell('Deliverable', header: true, align: 'l', rowSpan: 2);

        $weekRow = $this->cell('', header: true, vMerge: true);

        for ($week = 0; $week < $weeks; $week++) {
            $weekRow .= $this->cell((string) ($week % 4 + 1), header: true, narrow: true);
        }

        $weekRow .= str_repeat($this->cell('', header: true, vMerge: true), 3);
        $header = SlideText::emu(self::HEADER_ROW);
        $height = 2 * self::HEADER_ROW + $rowsHeight;

        return '<p:graphicFrame><p:nvGraphicFramePr><p:cNvPr id="'.$id.'" name="Jadwal '.$id.'"/><p:cNvGraphicFramePr><a:graphicFrameLocks noGrp="1"/></p:cNvGraphicFramePr><p:nvPr/></p:nvGraphicFramePr>'
            .'<p:xfrm><a:off x="'.SlideText::emu(self::LEFT).'" y="'.SlideText::emu(self::TABLE_TOP).'"/><a:ext cx="'.SlideText::emu(self::WIDTH).'" cy="'.SlideText::emu($height).'"/></p:xfrm>'
            .'<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/table"><a:tbl><a:tblPr firstRow="1"/><a:tblGrid>'.$grid.'</a:tblGrid>'
            .'<a:tr h="'.$header.'">'.$monthRow.'</a:tr><a:tr h="'.$header.'">'.$weekRow.'</a:tr>'.$rows
            .'</a:tbl></a:graphicData></a:graphic></p:graphicFrame>';
    }

    /**
     * @param  array{label: string, start: string, finish: string, deliverable: string, bars: list<bool>}  $step
     */
    private function scheduleRow(array $step, float $height): string
    {
        $cells = $this->cell($step['label'], align: 'l', color: self::TEXT);

        foreach ($step['bars'] as $active) {
            $cells .= $this->cell('', fill: $active ? self::PRIMARY : 'FFFFFF', narrow: true);
        }

        $cells .= $this->cell($step['start']).$this->cell($step['finish']).$this->cell($step['deliverable'], align: 'l', color: self::TEXT);

        return '<a:tr h="'.SlideText::emu($height).'">'.$cells.'</a:tr>';
    }

    private function cell(
        string $text,
        bool $header = false,
        string $align = 'ctr',
        string $fill = 'FFFFFF',
        string $color = self::MUTED,
        bool $bold = false,
        int $gridSpan = 1,
        int $rowSpan = 1,
        bool $hMerge = false,
        bool $vMerge = false,
        bool $narrow = false,
    ): string {
        if ($header) {
            [$fill, $color, $bold] = [self::TABLE_HEADER, self::NAVY, true];
        }

        $attributes = ($gridSpan > 1 ? ' gridSpan="'.$gridSpan.'"' : '').($rowSpan > 1 ? ' rowSpan="'.$rowSpan.'"' : '')
            .($hMerge ? ' hMerge="1"' : '').($vMerge ? ' vMerge="1"' : '');
        $paragraph = $text === ''
            ? '<a:p><a:pPr algn="'.$align.'"/><a:endParaRPr lang="id-ID" sz="'.(self::TABLE_SIZE * 100).'" dirty="0"/></a:p>'
            : '<a:p><a:pPr algn="'.$align.'"/><a:r>'.SlideText::runProperties(self::TABLE_SIZE, $color, $bold).'<a:t>'.SlideText::escape($text).'</a:t></a:r></a:p>';
        $border = fn (string $side): string => '<a:'.$side.' w="6350"><a:solidFill><a:srgbClr val="'.self::TABLE_LINE.'"/></a:solidFill></a:'.$side.'>';

        return '<a:tc'.$attributes.'><a:txBody><a:bodyPr/><a:lstStyle/>'.$paragraph.'</a:txBody>'
            // Week cells are 0.2" wide: no side margins, so viewers keep them at their set width.
            .'<a:tcPr marL="'.($narrow ? 0 : 54864).'" marR="'.($narrow ? 0 : 54864).'" marT="36576" marB="36576" anchor="ctr">'
            .$border('lnL').$border('lnR').$border('lnT').$border('lnB')
            .'<a:solidFill><a:srgbClr val="'.$fill.'"/></a:solidFill></a:tcPr></a:tc>';
    }

    /**
     * The eight QCC steps with their dates and the weeks they run through, and the months shown.
     *
     * @return array{months: list<string>, steps: list<array{label: string, start: string, finish: string, deliverable: string, bars: list<bool>}>}
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

            $steps[] = ['step' => $step, 'start' => $start, 'finish' => $finish, 'deliverable' => trim((string) ($row['deliverable'] ?? ''))];
            $previousFinish = $finish ?? $previousFinish;
        }

        $firstDate = collect($steps)->flatMap(fn (array $step): array => [$step['start'], $step['finish']])->filter()->min()
            ?? CarbonImmutable::parse($project->batch?->execution_starts_on ?? $project->submitted_at ?? now());
        $firstMonth = CarbonImmutable::parse($firstDate)->startOfMonth();

        $months = [];

        for ($month = 0; $month < self::SCHEDULE_MONTHS; $month++) {
            $months[] = $firstMonth->addMonths($month)->format('M-y');
        }

        return [
            'months' => $months,
            'steps' => array_map(function (array $step) use ($firstMonth): array {
                $bars = [];

                for ($column = 0; $column < self::SCHEDULE_MONTHS * 4; $column++) {
                    $month = $firstMonth->addMonths(intdiv($column, 4));
                    $week = $column % 4;
                    $weekStart = $month->addDays($week * 7);
                    $weekEnd = $week === 3 ? $month->endOfMonth()->startOfDay() : $weekStart->addDays(6);

                    $bars[] = $step['start'] !== null && $step['finish'] !== null
                        && $step['start']->lte($weekEnd) && $step['finish']->gte($weekStart);
                }

                return [
                    'label' => $step['step']->value.'. '.$step['step']->label(),
                    'start' => $step['start'] === null ? '-' : $this->date($step['start']),
                    'finish' => $step['finish'] === null ? '-' : $this->date($step['finish']),
                    'deliverable' => $step['deliverable'] === '' ? '-' : $step['deliverable'],
                    'bars' => $bars,
                ];
            }, $steps),
        ];
    }

    /**
     * Replace every {{token}} of a template slide. A value with its own text box (see
     * TEXT_BOXES) gets one paragraph per line, in the largest font that fits the box.
     *
     * @param  array<string, string>  $values
     */
    private function fill(string $xml, array $values): string
    {
        $xml = (string) preg_replace_callback(
            '#<a:p>((?:(?!</a:p>).)*?)<a:r>(<a:rPr\b[^>]*?(?:/>|>(?:(?!</a:rPr>).)*</a:rPr>))<a:t>\{\{([a-z0-9_]+)\}\}</a:t></a:r>((?:(?!</a:p>).)*?)</a:p>#s',
            function (array $match) use ($values): string {
                [$whole, $before, $runStyle, $key, $after] = $match;

                if (! array_key_exists($key, self::TEXT_BOXES) || preg_match('#<a:t>#', $before.$after) === 1) {
                    return $whole;
                }

                [$width, $height, $largest, $smallest] = self::TEXT_BOXES[$key];
                $lines = preg_split('/\R/u', trim($values[$key] ?? '') === '' ? '-' : trim($values[$key])) ?: ['-'];
                $paragraphs = array_map(fn (string $line): array => ['text' => $line, 'bullet' => false], $lines);
                $size = $smallest;

                for ($candidate = $largest; $candidate >= $smallest; $candidate--) {
                    if (SlideText::height($paragraphs, $width, $candidate) <= $height) {
                        $size = $candidate;

                        break;
                    }
                }

                $runStyle = str_contains($runStyle, ' sz="')
                    ? (string) preg_replace('/ sz="\d+"/', ' sz="'.($size * 100).'"', $runStyle, 1)
                    : (string) preg_replace('/^<a:rPr/', '<a:rPr sz="'.($size * 100).'"', $runStyle, 1);

                return implode('', array_map(
                    fn (string $line): string => '<a:p>'.$before.'<a:r>'.$runStyle.'<a:t xml:space="preserve">'.SlideText::escape(SlideText::plain($line)).'</a:t></a:r>'.$after.'</a:p>',
                    $lines,
                ));
            },
            $xml,
        );

        return (string) preg_replace_callback(
            '#\{\{([a-z0-9_]+)\}\}#',
            fn (array $match): string => SlideText::escape(SlideText::plain($values[$match[1]] ?? '')),
            $xml,
        );
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
