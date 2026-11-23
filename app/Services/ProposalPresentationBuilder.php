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
 * Builds the proposal deck on the "QCC Batch 4 New Concept" template
 * (resources/templates/proposal-presentation.pptx): a cover, two proposal slides laid out as in
 * the template (title and theme above the background & baseline data; target beside the scope
 * and the cost projection), the planned QCC steps 1–5 as a weekly gantt table, the approval
 * slide and a closing slide.
 *
 * Nothing is ever cut short: text that does not fit its card continues on a following
 * "(lanjutan)" slide, where cards flow over two columns, and a schedule too tall for one slide
 * continues on the next, with its header repeated.
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

    private const CARD_BORDER = 'D6DEE6';

    /** Corner radius of every card, in inches, whatever its size. */
    private const CARD_CORNER = 0.1;

    /** Content area of a frame slide, in inches. */
    private const LEFT = 0.6;

    private const WIDTH = 12.13;

    /** Cards start under the title; the schedule under its subtitle. */
    private const TOP = 1.6;

    private const TABLE_TOP = 1.75;

    private const BOTTOM = 6.86;

    private const COLUMN_GAP = 0.3;

    private const CARD_GAP = 0.25;

    /** The title and theme cards above the background: at least this tall, and at most. */
    private const HEADLINE_MIN = 1.28;

    private const HEADLINE_MAX = 2.4;

    private const CARD_PADDING_X = 0.2;

    private const CARD_PADDING_Y = 0.16;

    private const LABEL_SIZE = 12;

    private const LABEL_GAP = 6;

    private const BODY_SIZE = 12;

    /** A card continued elsewhere still shows at least this many lines where it starts. */
    private const MIN_LINES = 3;

    /** Months the schedule shows, four week columns each: from its first month, at least two and at most seven. */
    private const MIN_SCHEDULE_MONTHS = 2;

    private const MAX_SCHEDULE_MONTHS = 7;

    private const TABLE_SIZE = 10;

    private const WEEK_SIZE = 8;

    /** Schedule columns: step, one per week, start, finish, deliverable (inches). */
    private const STEP_COLUMN = 3.1;

    private const MAX_WEEK_COLUMN = 0.35;

    private const DATE_COLUMN = 1.0;

    private const MIN_DELIVERABLE_COLUMN = 2.6;

    private const HEADER_ROW = 0.32;

    private const MIN_ROW = 0.45;

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

        foreach ([$this->overviewPage($project), $this->planPage($project)] as $page) {
            $pages[] = ['title' => 'Proposal Proyek', 'subtitle' => '', 'body' => $page['body']];

            foreach ($this->sectionPages($page['overflow']) as $cards) {
                $pages[] = ['title' => 'Proposal Proyek (lanjutan)', 'subtitle' => '', 'body' => $cards];
            }
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
     * The first proposal slide: the title and the theme side by side, and below them the
     * background & baseline data in a wide card whose text runs over two columns.
     *
     * @return array{body: string, overflow: list<array{label: string, paragraphs: list<array{text: string, bullet: bool}>}>}
     */
    private function overviewPage(Project $project): array
    {
        $columnWidth = $this->columnWidth();
        $right = self::LEFT + $columnWidth + self::COLUMN_GAP;
        $title = SlideText::paragraphs($project->title);
        $theme = SlideText::paragraphs($project->theme);
        $height = min(self::HEADLINE_MAX, max(self::HEADLINE_MIN, $this->cardHeight($title, $columnWidth), $this->cardHeight($theme, $columnWidth)));
        $overflow = [];
        $body = $this->fittedCard('Judul Proyek', $title, self::LEFT, self::TOP, $columnWidth, $height, $overflow)
            .$this->fittedCard('Tema Improvement', $theme, $right, self::TOP, $columnWidth, $height, $overflow);

        $top = self::TOP + $height + self::CARD_GAP;
        $label = 'Latar Belakang & Baseline Data';
        $textWidth = $this->textColumnWidth();
        $room = self::BOTTOM - $top - $this->cardChrome();
        $paragraphs = SlideText::paragraphs($project->background);

        // The text fills the left column first and runs on into the right one only when it is
        // longer, so a short background is never broken mid-sentence.
        [$first, $rest] = SlideText::split($paragraphs, $textWidth, self::BODY_SIZE, $room);
        [$second, $rest] = SlideText::split($rest, $textWidth, self::BODY_SIZE, $room);

        $body .= $this->twoColumnCard(Str::upper($label), $first, $second, self::LEFT, $top, self::WIDTH, self::BOTTOM - $top);

        if ($rest !== []) {
            $overflow[] = ['label' => Str::upper($label).' (LANJUTAN)', 'paragraphs' => $rest];
        }

        return ['body' => $body, 'overflow' => $overflow];
    }

    /**
     * The second proposal slide: the target down the left, the scope and the cost projection
     * stacked on the right. Older sections removed from the form follow on continuation slides
     * when a proposal still has them.
     *
     * @return array{body: string, overflow: list<array{label: string, paragraphs: list<array{text: string, bullet: bool}>}>}
     */
    private function planPage(Project $project): array
    {
        $columnWidth = $this->columnWidth();
        $right = self::LEFT + $columnWidth + self::COLUMN_GAP;
        $columnHeight = self::BOTTOM - self::TOP;
        $overflow = [];
        $body = $this->fittedCard('Target / Tujuan', SlideText::paragraphs($project->target), self::LEFT, self::TOP, $columnWidth, $columnHeight, $overflow);

        $scope = SlideText::paragraphs($project->scope);
        $cost = SlideText::paragraphs($project->cost_projection);
        $room = $columnHeight - self::CARD_GAP;
        $scopeNeeds = $this->cardHeight($scope, $columnWidth);
        $costNeeds = $this->cardHeight($cost, $columnWidth);

        if ($scopeNeeds + $costNeeds <= $room) {
            // Both fit: share the column, the longer card taking what the shorter one leaves.
            $scopeHeight = max($room / 2, $scopeNeeds);
            $scopeHeight = $room - max($room - $scopeHeight, $costNeeds);
        } else {
            $smallest = $this->cardChrome() + self::MIN_LINES * SlideText::lineHeight(self::BODY_SIZE);
            $scopeHeight = max($smallest, min($scopeNeeds, $room - max($smallest, min($costNeeds, $room / 2))));
        }

        $body .= $this->fittedCard('Ruang Lingkup Proyek', $scope, $right, self::TOP, $columnWidth, $scopeHeight, $overflow)
            .$this->fittedCard('Proyeksi Kebutuhan Biaya', $cost, $right, self::TOP + $scopeHeight + self::CARD_GAP, $columnWidth, $room - $scopeHeight, $overflow);

        $legacy = [
            'Pernyataan Masalah' => $project->problem_statement,
            'Root Cause Analysis (RCA)' => $project->rca,
            'Manfaat Bisnis' => $project->business_benefit,
        ];

        foreach (array_filter($legacy, 'filled') as $label => $text) {
            $overflow[] = ['label' => Str::upper($label), 'paragraphs' => SlideText::paragraphs($text)];
        }

        return ['body' => $body, 'overflow' => $overflow];
    }

    /**
     * A card of a fixed size holding as much of the text as fits; the rest is queued for the
     * continuation slides.
     *
     * @param  list<array{text: string, bullet: bool}>  $paragraphs
     * @param  list<array{label: string, paragraphs: list<array{text: string, bullet: bool}>}>  $overflow
     */
    private function fittedCard(string $label, array $paragraphs, float $x, float $y, float $width, float $height, array &$overflow): string
    {
        $label = Str::upper($label);
        [$fits, $rest] = SlideText::split($paragraphs, $width - 2 * self::CARD_PADDING_X, self::BODY_SIZE, $height - $this->cardChrome());

        if ($rest !== []) {
            $overflow[] = ['label' => $label.' (LANJUTAN)', 'paragraphs' => $rest];
        }

        return $this->card($label, $fits === [] ? [['text' => '', 'bullet' => false]] : $fits, $x, $y, $width, $height);
    }

    /**
     * @param  list<array{text: string, bullet: bool}>  $paragraphs
     */
    private function cardHeight(array $paragraphs, float $width): float
    {
        return $this->cardChrome() + SlideText::height($paragraphs, $width - 2 * self::CARD_PADDING_X, self::BODY_SIZE);
    }

    /** Height of a card without its text: padding and the label. */
    private function cardChrome(): float
    {
        return 2 * self::CARD_PADDING_Y + SlideText::lineHeight(self::LABEL_SIZE) + self::LABEL_GAP / 72;
    }

    private function columnWidth(): float
    {
        return (self::WIDTH - self::COLUMN_GAP) / 2;
    }

    /** Width of each text column inside the wide background card. */
    private function textColumnWidth(): float
    {
        return (self::WIDTH - 2 * self::CARD_PADDING_X - 2 * self::COLUMN_GAP) / 2;
    }

    /**
     * Lay the text that did not fit its card out as cards over two columns, column by column
     * and slide by slide. A section that does not fit where it starts is split, and continues
     * at the top of the next column with "(lanjutan)" after its label.
     *
     * @param  list<array{label: string, paragraphs: list<array{text: string, bullet: bool}>}>  $sections
     * @return list<string> The card shapes of each slide.
     */
    private function sectionPages(array $sections): array
    {
        $columnWidth = $this->columnWidth();
        $textWidth = $columnWidth - 2 * self::CARD_PADDING_X;
        $chrome = $this->cardChrome();
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
            $paragraphs = $section['paragraphs'];
            $label = $section['label'];

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
                        $label = str_ends_with($section['label'], ' (LANJUTAN)') ? $section['label'] : $section['label'].' (LANJUTAN)';
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
     * A white card with a thin border, holding a label and its paragraphs.
     *
     * @param  list<array{text: string, bullet: bool}>  $paragraphs
     */
    private function card(string $label, array $paragraphs, float $x, float $y, float $width, float $height): string
    {
        return $this->shape('Card', $x, $y, $width, $height, $this->cardFrame(), $this->labelXml($label).$this->bodyXml($paragraphs));
    }

    /**
     * The wide background card: one label above two text columns split by a thin line.
     *
     * @param  list<array{text: string, bullet: bool}>  $first
     * @param  list<array{text: string, bullet: bool}>  $second
     */
    private function twoColumnCard(string $label, array $first, array $second, float $x, float $y, float $width, float $height): string
    {
        $textTop = $y + self::CARD_PADDING_Y + SlideText::lineHeight(self::LABEL_SIZE) + self::LABEL_GAP / 72;
        $textHeight = $y + $height - self::CARD_PADDING_Y - $textTop;
        $columnWidth = $this->textColumnWidth();
        $secondX = $x + self::CARD_PADDING_X + $columnWidth + 2 * self::COLUMN_GAP;
        $noInset = '<a:bodyPr wrap="square" lIns="0" tIns="0" rIns="0" bIns="0" anchor="t" rtlCol="0"><a:noAutofit/></a:bodyPr>';
        $labelHeight = SlideText::lineHeight(self::LABEL_SIZE) + self::LABEL_GAP / 72;
        // The label is a text box of its own, so it lines up with the text columns below it.
        $xml = $this->shape('Card', $x, $y, $width, $height, $this->cardFrame(), '')
            .$this->shape('Text', $x + self::CARD_PADDING_X, $y + self::CARD_PADDING_Y, $width - 2 * self::CARD_PADDING_X, $labelHeight, '<a:noFill/><a:ln><a:noFill/></a:ln>', $this->labelXml($label), $noInset)
            .$this->shape('Text', $x + self::CARD_PADDING_X, $textTop, $columnWidth, $textHeight, '<a:noFill/><a:ln><a:noFill/></a:ln>', $this->bodyXml($first === [] ? [['text' => '-', 'bullet' => false]] : $first), $noInset);

        if ($second !== []) {
            $id = $this->shapeId++;
            $xml .= '<p:cxnSp><p:nvCxnSpPr><p:cNvPr id="'.$id.'" name="Divider '.$id.'"/><p:cNvCxnSpPr/><p:nvPr/></p:nvCxnSpPr>'
                .'<p:spPr><a:xfrm><a:off x="'.SlideText::emu($x + $width / 2).'" y="'.SlideText::emu($textTop).'"/><a:ext cx="0" cy="'.SlideText::emu($textHeight).'"/></a:xfrm>'
                .'<a:prstGeom prst="line"><a:avLst/></a:prstGeom><a:ln w="9525"><a:solidFill><a:srgbClr val="'.self::CARD_BORDER.'"/></a:solidFill></a:ln></p:spPr></p:cxnSp>'
                .$this->shape('Text', $secondX, $textTop, $columnWidth, $textHeight, '<a:noFill/><a:ln><a:noFill/></a:ln>', $this->bodyXml($second), $noInset);
        }

        return $xml;
    }

    /**
     * The rounded-rectangle adjustment giving a card the same corner radius at any size; it is
     * a share (in 1/100000) of the card's shorter side.
     */
    private function cornerAdjustment(float $width, float $height): int
    {
        return (int) min(50000, round(100000 * self::CARD_CORNER / max(0.01, min($width, $height))));
    }

    private function cardFrame(): string
    {
        // White card with the template's thin border and no shadow.
        return '<a:solidFill><a:srgbClr val="FFFFFF"/></a:solidFill><a:ln w="9525"><a:solidFill><a:srgbClr val="'.self::CARD_BORDER.'"/></a:solidFill></a:ln>';
    }

    private function labelXml(string $label): string
    {
        return '<a:p><a:pPr algn="l"><a:spcAft><a:spcPts val="'.(self::LABEL_GAP * 100).'"/></a:spcAft><a:buNone/></a:pPr>'
            .'<a:r>'.SlideText::runProperties(self::LABEL_SIZE, self::PRIMARY, true).'<a:t>'.SlideText::escape($label).'</a:t></a:r></a:p>';
    }

    /**
     * @param  list<array{text: string, bullet: bool}>  $paragraphs
     */
    private function bodyXml(array $paragraphs): string
    {
        return implode('', array_map(
            fn (array $paragraph): string => SlideText::paragraphXml($paragraph, self::BODY_SIZE, self::TEXT),
            $paragraphs,
        ));
    }

    private function shape(string $name, float $x, float $y, float $width, float $height, string $frame, string $paragraphs, ?string $bodyProperties = null): string
    {
        $id = $this->shapeId++;
        $bodyProperties ??= '<a:bodyPr wrap="square" lIns="'.SlideText::emu(self::CARD_PADDING_X).'" tIns="'.SlideText::emu(self::CARD_PADDING_Y).'" rIns="'.SlideText::emu(self::CARD_PADDING_X).'" bIns="'.SlideText::emu(self::CARD_PADDING_Y).'" anchor="t" rtlCol="0"><a:noAutofit/></a:bodyPr>';
        $isTextBox = $name === 'Text' ? ' txBox="1"' : '';

        return '<p:sp><p:nvSpPr><p:cNvPr id="'.$id.'" name="'.$name.' '.$id.'"/><p:cNvSpPr'.$isTextBox.'/><p:nvPr/></p:nvSpPr>'
            .'<p:spPr><a:xfrm><a:off x="'.SlideText::emu($x).'" y="'.SlideText::emu($y).'"/><a:ext cx="'.SlideText::emu($width).'" cy="'.SlideText::emu($height).'"/></a:xfrm>'
            .'<a:prstGeom prst="'.($name === 'Text' ? 'rect' : 'roundRect').'"><a:avLst>'.($name === 'Text' ? '' : '<a:gd name="adj" fmla="val '.$this->cornerAdjustment($width, $height).'"/>').'</a:avLst></a:prstGeom>'
            .$frame.'</p:spPr>'
            .'<p:txBody>'.$bodyProperties.'<a:lstStyle/>'.($paragraphs === '' ? '<a:p><a:endParaRPr lang="id-ID" dirty="0"/></a:p>' : $paragraphs).'</p:txBody></p:sp>';
    }

    /**
     * The schedule as gantt tables, one per slide, each with the month and week header.
     *
     * @return list<string>
     */
    private function schedulePages(Project $project): array
    {
        $schedule = $this->schedule($project);
        $weeks = count($schedule['months']) * 4;
        $weekWidth = min(self::MAX_WEEK_COLUMN, (self::WIDTH - self::STEP_COLUMN - 2 * self::DATE_COLUMN - self::MIN_DELIVERABLE_COLUMN) / $weeks);
        $deliverableWidth = self::WIDTH - self::STEP_COLUMN - $weeks * $weekWidth - 2 * self::DATE_COLUMN;
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
                $pages[] = $this->scheduleTable($schedule['months'], $rows, $used, $weekWidth, $deliverableWidth);
                $rows = '';
                $used = 0.0;
            }

            $rows .= $this->scheduleRow($step, $height);
            $used += $height;
        }

        $pages[] = $this->scheduleTable($schedule['months'], $rows, $used, $weekWidth, $deliverableWidth);

        return $pages;
    }

    /**
     * @param  list<string>  $months
     */
    private function scheduleTable(array $months, string $rows, float $rowsHeight, float $weekWidth, float $deliverableWidth): string
    {
        $id = $this->shapeId++;
        $weeks = count($months) * 4;
        $grid = '<a:gridCol w="'.SlideText::emu(self::STEP_COLUMN).'"/>'
            .str_repeat('<a:gridCol w="'.SlideText::emu($weekWidth).'"/>', $weeks)
            .str_repeat('<a:gridCol w="'.SlideText::emu(self::DATE_COLUMN).'"/>', 2)
            .'<a:gridCol w="'.SlideText::emu($deliverableWidth).'"/>';

        $monthRow = $this->cell('Step QCC', header: true, align: 'l', rowSpan: 2);

        foreach ($months as $month) {
            $monthRow .= $this->cell($month, header: true, gridSpan: 4).str_repeat($this->cell('', header: true, hMerge: true, narrow: true), 3);
        }

        $monthRow .= $this->cell('Start Date', header: true, rowSpan: 2).$this->cell('Finish Date', header: true, rowSpan: 2).$this->cell('Deliverable Result', header: true, align: 'l', rowSpan: 2);

        $weekRow = $this->cell('', header: true, vMerge: true);

        for ($week = 0; $week < $weeks; $week++) {
            $weekRow .= $this->cell((string) ($week % 4 + 1), header: true, narrow: true, size: self::WEEK_SIZE);
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
        int $size = self::TABLE_SIZE,
    ): string {
        if ($header) {
            [$fill, $color, $bold] = [self::TABLE_HEADER, self::NAVY, true];
        }

        $attributes = ($gridSpan > 1 ? ' gridSpan="'.$gridSpan.'"' : '').($rowSpan > 1 ? ' rowSpan="'.$rowSpan.'"' : '')
            .($hMerge ? ' hMerge="1"' : '').($vMerge ? ' vMerge="1"' : '');
        $paragraph = $text === ''
            ? '<a:p><a:pPr algn="'.$align.'"/><a:endParaRPr lang="id-ID" sz="'.($size * 100).'" dirty="0"/></a:p>'
            : '<a:p><a:pPr algn="'.$align.'"/><a:r>'.SlideText::runProperties($size, $color, $bold).'<a:t>'.SlideText::escape($text).'</a:t></a:r></a:p>';
        $border = fn (string $side): string => '<a:'.$side.' w="6350"><a:solidFill><a:srgbClr val="'.self::TABLE_LINE.'"/></a:solidFill></a:'.$side.'>';

        return '<a:tc'.$attributes.'><a:txBody><a:bodyPr/><a:lstStyle/>'.$paragraph.'</a:txBody>'
            // Week cells are narrow: no side margins, so viewers keep them at their set width.
            .'<a:tcPr marL="'.($narrow ? 0 : 54864).'" marR="'.($narrow ? 0 : 54864).'" marT="36576" marB="36576" anchor="ctr">'
            .$border('lnL').$border('lnR').$border('lnT').$border('lnB')
            .'<a:solidFill><a:srgbClr val="'.$fill.'"/></a:solidFill></a:tcPr></a:tc>';
    }

    /**
     * The QCC steps planned in the proposal (1–5) with their dates and the weeks they run
     * through, and the months shown: from the month of the first date to the month of the last.
     *
     * @return array{months: list<string>, steps: list<array{label: string, start: string, finish: string, deliverable: string, bars: list<bool>}>}
     */
    private function schedule(Project $project): array
    {
        $timeline = collect((array) $project->step_timeline_json)->keyBy('step_number');
        $steps = [];
        $previousFinish = null;

        foreach (QccStep::proposalSteps() as $step) {
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

        $dates = collect($steps)->flatMap(fn (array $step): array => [$step['start'], $step['finish']])->filter();
        $firstDate = $dates->min() ?? CarbonImmutable::parse($project->batch?->execution_starts_on ?? $project->submitted_at ?? now());
        $firstMonth = CarbonImmutable::parse($firstDate)->startOfMonth();
        $lastMonth = CarbonImmutable::parse($dates->max() ?? $firstMonth)->startOfMonth();
        $monthCount = max(self::MIN_SCHEDULE_MONTHS, min(self::MAX_SCHEDULE_MONTHS, (int) $firstMonth->diffInMonths($lastMonth) + 1));

        $months = [];

        for ($month = 0; $month < $monthCount; $month++) {
            $months[] = $firstMonth->addMonths($month)->format('M-y');
        }

        return [
            'months' => $months,
            'steps' => array_map(function (array $step) use ($firstMonth, $monthCount): array {
                $bars = [];

                for ($column = 0; $column < $monthCount * 4; $column++) {
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
