import {
    AlertCircle,
    CalendarCheck,
    Check,
    CheckCircle2,
    Search,
    Users,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { numberFormat, PanelEmpty } from '@/components/dashboard/dashboard-kit';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import {
    ColumnBars,
    HighlightCard,
    Panel,
    programColors,
    StatTiles,
} from './program-kit';
import type { ExecutionPhaseData, ExecutionProjectRow } from './types';

const ALL = 'all';
const PAGE_SIZE = 8;

/** Short step names for the eight-step overview. */
const STEP_SHORT_LABELS = [
    'Tema',
    'Target',
    'Penyebab',
    'Penyebab Dominan',
    'Rencana Perbaikan',
    'Implementasi',
    'Evaluasi',
    'Standardisasi',
];

function StepDots({ steps }: { steps: ExecutionProjectRow['steps'] }) {
    return (
        <ol className="flex items-center gap-1" aria-label="Progres 8 step">
            {steps.map((state, index) => (
                <li
                    key={index}
                    className={cn(
                        'flex size-5 items-center justify-center rounded-full text-[10px] font-semibold tabular-nums',
                        state === 'done' &&
                            'bg-primary text-primary-foreground',
                        state === 'current' &&
                            'border border-primary bg-card text-primary',
                        state === 'todo' && 'bg-muted text-muted-foreground',
                    )}
                    title={`Step ${index + 1}: ${state === 'done' ? 'selesai' : state === 'current' ? 'berjalan' : 'belum mulai'}`}
                >
                    {state === 'done' ? (
                        <Check className="size-3" aria-hidden="true" />
                    ) : (
                        index + 1
                    )}
                    <span className="sr-only">
                        {state === 'done'
                            ? 'selesai'
                            : state === 'current'
                              ? 'berjalan'
                              : 'belum mulai'}
                    </span>
                </li>
            ))}
        </ol>
    );
}

function StatusBadge({ status }: { status: ExecutionProjectRow['status'] }) {
    return status === 'overdue' ? (
        <Badge variant="destructive" className="gap-1">
            <AlertCircle className="size-3" aria-hidden="true" />
            Overdue
        </Badge>
    ) : (
        <Badge variant="success" className="gap-1">
            <CheckCircle2 className="size-3" aria-hidden="true" />
            On-Time
        </Badge>
    );
}

function FilterSelect({
    label,
    value,
    onChange,
    options,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: { value: string; label: string }[];
}) {
    return (
        <Select
            value={value}
            onValueChange={(next) => onChange(next ?? ALL)}
            items={options}
        >
            <SelectTrigger className="h-9 w-full sm:w-48" aria-label={label}>
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function ProjectList({ data }: { data: ExecutionPhaseData }) {
    const [search, setSearch] = useState('');
    const [organization, setOrganization] = useState(ALL);
    const [step, setStep] = useState(ALL);
    const [status, setStatus] = useState(ALL);
    const [page, setPage] = useState(0);

    const organizations = useMemo(
        () =>
            Array.from(
                new Set(
                    data.projects
                        .map((project) => project.organization)
                        .filter((name): name is string => Boolean(name)),
                ),
            ).sort(),
        [data.projects],
    );
    const term = search.trim().toLowerCase();
    const projects = data.projects.filter(
        (project) =>
            (term === '' ||
                project.code.toLowerCase().includes(term) ||
                project.title.toLowerCase().includes(term)) &&
            (organization === ALL || project.organization === organization) &&
            (step === ALL ||
                String(project.current_step?.number ?? 'done') === step) &&
            (status === ALL || project.status === status),
    );
    const pageCount = Math.max(1, Math.ceil(projects.length / PAGE_SIZE));
    const currentPage = Math.min(page, pageCount - 1);
    const firstRow = currentPage * PAGE_SIZE;
    const pageRows = projects.slice(firstRow, firstRow + PAGE_SIZE);
    const filterBy =
        (set: (value: string) => void) =>
        (value: string): void => {
            set(value);
            setPage(0);
        };

    return (
        <Panel
            title={
                data.projects_scope === 'own'
                    ? `Project Saya (${data.projects.length})`
                    : `Daftar Project & Progress Saat Ini (${data.projects.length} Project)`
            }
            description={
                data.projects_scope === 'own'
                    ? 'Proyek yang Anda pimpin atau ikuti di batch ini.'
                    : 'Step yang sedang berjalan dan status waktu setiap proyek.'
            }
        >
            <div className="grid gap-4">
                <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                    <div className="relative sm:w-64">
                        <Search
                            aria-hidden="true"
                            className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            value={search}
                            onChange={(event) =>
                                filterBy(setSearch)(event.target.value)
                            }
                            placeholder="Cari kode / judul project"
                            aria-label="Cari kode atau judul project"
                            className="h-9 pl-9"
                        />
                    </div>
                    <FilterSelect
                        label="Filter LOB/Div"
                        value={organization}
                        onChange={filterBy(setOrganization)}
                        options={[
                            { value: ALL, label: 'Semua LOB/Div' },
                            ...organizations.map((name) => ({
                                value: name,
                                label: name,
                            })),
                        ]}
                    />
                    <FilterSelect
                        label="Filter step"
                        value={step}
                        onChange={filterBy(setStep)}
                        options={[
                            { value: ALL, label: 'Semua Step' },
                            ...data.steps.map((item) => ({
                                value: String(item.number),
                                label: `Step ${item.number}`,
                            })),
                            { value: 'done', label: 'Semua step selesai' },
                        ]}
                    />
                    <FilterSelect
                        label="Filter status waktu"
                        value={status}
                        onChange={filterBy(setStatus)}
                        options={[
                            { value: ALL, label: 'Semua Status' },
                            { value: 'on_time', label: 'On-Time' },
                            { value: 'overdue', label: 'Overdue' },
                        ]}
                    />
                </div>

                {projects.length > 0 ? (
                    <div className="grid gap-3">
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-10">
                                            No
                                        </TableHead>
                                        <TableHead>Kode</TableHead>
                                        <TableHead className="min-w-44">
                                            Judul Project
                                        </TableHead>
                                        <TableHead>LOB/Div</TableHead>
                                        <TableHead>Team Leader</TableHead>
                                        <TableHead>Step Saat Ini</TableHead>
                                        <TableHead>
                                            Progres (Step 1–8)
                                        </TableHead>
                                        <TableHead>Status Waktu</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {pageRows.map((project, index) => (
                                        <TableRow key={project.id}>
                                            <TableCell className="tabular-nums">
                                                {firstRow + index + 1}
                                            </TableCell>
                                            <TableCell className="font-medium whitespace-nowrap">
                                                {project.code}
                                            </TableCell>
                                            <TableCell className="whitespace-normal">
                                                {project.title}
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap">
                                                {project.organization ?? '—'}
                                            </TableCell>
                                            <TableCell className="min-w-32 whitespace-normal">
                                                {project.leader ?? '—'}
                                            </TableCell>
                                            <TableCell className="min-w-28 whitespace-normal">
                                                {project.current_step
                                                    ? `Step ${project.current_step.number} · ${STEP_SHORT_LABELS[project.current_step.number - 1] ?? project.current_step.label}`
                                                    : 'Semua step selesai'}
                                            </TableCell>
                                            <TableCell>
                                                <StepDots
                                                    steps={project.steps}
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    status={project.status}
                                                />
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                        {pageCount > 1 && (
                            <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                                <span className="tabular-nums">
                                    {firstRow + 1}–{firstRow + pageRows.length}{' '}
                                    dari {projects.length} project
                                </span>
                                <div className="flex items-center gap-1">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={currentPage === 0}
                                        onClick={() => setPage(currentPage - 1)}
                                    >
                                        Sebelumnya
                                    </Button>
                                    <span className="px-2 tabular-nums">
                                        {currentPage + 1} / {pageCount}
                                    </span>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={currentPage >= pageCount - 1}
                                        onClick={() => setPage(currentPage + 1)}
                                    >
                                        Berikutnya
                                    </Button>
                                </div>
                            </div>
                        )}
                    </div>
                ) : (
                    <PanelEmpty
                        title={
                            data.projects.length === 0
                                ? 'Belum ada project'
                                : 'Tidak ada project yang cocok'
                        }
                        description={
                            data.projects.length === 0
                                ? 'Project tampil setelah proposal disetujui.'
                                : 'Ubah pencarian atau filter untuk melihat project lain.'
                        }
                    />
                )}
            </div>
        </Panel>
    );
}

/** Phase 3: step progress of the approved projects and whether reports keep up. */
export function ExecutionPhase({ data }: { data: ExecutionPhaseData }) {
    return (
        <div className="grid gap-4">
            <div className="grid gap-4 xl:grid-cols-[minmax(0,4fr)_minmax(0,8fr)]">
                <HighlightCard
                    title="Rata-rata progres project"
                    percent={data.average_progress}
                    caption="Rata-rata progres 8 step QCC dari semua project aktif."
                    rows={[
                        {
                            label: 'Project aktif',
                            value: numberFormat.format(data.total),
                        },
                        {
                            label: 'On-Time',
                            value: numberFormat.format(data.on_time.count),
                        },
                        {
                            label: 'Overdue',
                            value: numberFormat.format(data.on_time.overdue),
                        },
                    ]}
                />
                <div className="grid min-w-0 gap-4">
                    <StatTiles
                        tiles={[
                            {
                                label: 'Project on-time',
                                value: `${data.on_time.percent}%`,
                                helper: `${data.on_time.count} dari ${data.total} project`,
                                icon: CheckCircle2,
                                color: programColors.green,
                            },
                            {
                                label: 'Kepatuhan bi-weekly',
                                value: `${data.biweekly.percent}%`,
                                helper: `${data.biweekly.compliant} project tepat waktu`,
                                icon: CalendarCheck,
                                color: programColors.primary,
                            },
                            {
                                label: 'Kepatuhan mentoring',
                                value: `${data.mentoring.percent}%`,
                                helper: `${data.mentoring.compliant} project sesuai jadwal`,
                                icon: Users,
                                color: programColors.violet,
                            },
                            {
                                label: 'Project overdue',
                                value: numberFormat.format(
                                    data.on_time.overdue,
                                ),
                                helper: 'Ada step lewat target selesai',
                                icon: AlertCircle,
                                color: programColors.critical,
                            },
                        ]}
                    />
                    <Panel
                        title="Progress 8 step QCC"
                        description="Rata-rata progres tiap step. Angka di bawah: project yang sudah memulai step itu."
                    >
                        <ColumnBars
                            label="Rata-rata progres per step QCC"
                            max={100}
                            height={96}
                            columns={data.steps.map((step) => ({
                                key: String(step.number),
                                label: `${step.number}. ${STEP_SHORT_LABELS[step.number - 1] ?? step.label} · ${step.projects_started}`,
                                value: step.average_progress,
                                display: `${step.average_progress}%`,
                                title: `${step.label}: ${step.average_progress}% · ${step.projects_started} project`,
                            }))}
                        />
                    </Panel>
                </div>
            </div>

            <ProjectList data={data} />
        </div>
    );
}
