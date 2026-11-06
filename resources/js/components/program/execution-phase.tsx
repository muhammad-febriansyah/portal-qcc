import { AlertCircle, Check, CheckCircle2, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    DashboardPanel,
    numberFormat,
    PanelEmpty,
} from '@/components/dashboard/dashboard-kit';
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
import { Meter, programColors, StatStrip } from './program-kit';
import type { ExecutionPhaseData, ExecutionProjectRow } from './types';

const ALL = 'all';

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

    return (
        <DashboardPanel
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
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Cari kode / judul project"
                            aria-label="Cari kode atau judul project"
                            className="h-9 pl-9"
                        />
                    </div>
                    <FilterSelect
                        label="Filter LOB/Div"
                        value={organization}
                        onChange={setOrganization}
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
                        onChange={setStep}
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
                        onChange={setStatus}
                        options={[
                            { value: ALL, label: 'Semua Status' },
                            { value: 'on_time', label: 'On-Time' },
                            { value: 'overdue', label: 'Overdue' },
                        ]}
                    />
                </div>

                {projects.length > 0 ? (
                    <div className="overflow-x-auto rounded-lg border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-10">No</TableHead>
                                    <TableHead>Kode</TableHead>
                                    <TableHead className="min-w-44">
                                        Judul Project
                                    </TableHead>
                                    <TableHead>LOB/Div</TableHead>
                                    <TableHead>Team Leader</TableHead>
                                    <TableHead>Step Saat Ini</TableHead>
                                    <TableHead>Progres (Step 1–8)</TableHead>
                                    <TableHead>Status Waktu</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {projects.map((project, index) => (
                                    <TableRow key={project.id}>
                                        <TableCell className="tabular-nums">
                                            {index + 1}
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
                                            <StepDots steps={project.steps} />
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
        </DashboardPanel>
    );
}

/** Phase 3: step progress of the approved projects and whether reports keep up. */
export function ExecutionPhase({ data }: { data: ExecutionPhaseData }) {
    return (
        <div className="grid gap-5">
            <StatStrip
                stats={[
                    {
                        label: 'Total project aktif',
                        value: numberFormat.format(data.total),
                        helper: 'Proposal yang disetujui',
                    },
                    {
                        label: 'Rata-rata progres project',
                        value: `${data.average_progress}%`,
                        helper: 'Rata-rata dari 8 step QCC',
                    },
                    {
                        label: 'Kepatuhan bi-weekly',
                        value: `${data.biweekly.percent}%`,
                        helper: `${data.biweekly.compliant} dari ${data.total} project tepat waktu`,
                    },
                    {
                        label: 'Kepatuhan mentoring',
                        value: `${data.mentoring.percent}%`,
                        helper: `${data.mentoring.compliant} dari ${data.total} sesuai jadwal`,
                    },
                    {
                        label: 'Project on-time',
                        value: `${data.on_time.percent}%`,
                        helper: `${data.on_time.overdue} project overdue`,
                    },
                ]}
            />

            <div className="grid gap-5 xl:grid-cols-[minmax(0,7fr)_minmax(0,5fr)]">
                <DashboardPanel
                    title="Progress 8 step QCC"
                    description="Rata-rata progres tiap step dan jumlah project yang sudah memulainya."
                >
                    <table className="w-full text-sm">
                        <caption className="sr-only">
                            Progres rata-rata dan jumlah project per step QCC
                        </caption>
                        <thead>
                            <tr className="text-left text-xs text-muted-foreground">
                                <th scope="col" className="pb-2 font-medium">
                                    Step
                                </th>
                                <th
                                    scope="col"
                                    className="w-2/5 pb-2 font-medium"
                                >
                                    <span className="sr-only">
                                        Grafik progres
                                    </span>
                                </th>
                                <th
                                    scope="col"
                                    className="pb-2 text-right font-medium"
                                >
                                    Progres
                                </th>
                                <th
                                    scope="col"
                                    className="pb-2 text-right font-medium"
                                >
                                    Project
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border/60">
                            {data.steps.map((step) => (
                                <tr key={step.number}>
                                    <th
                                        scope="row"
                                        className="py-2 pr-3 text-left font-normal text-foreground"
                                    >
                                        <span className="mr-2 text-muted-foreground tabular-nums">
                                            {step.number}
                                        </span>
                                        {STEP_SHORT_LABELS[step.number - 1] ??
                                            step.label}
                                    </th>
                                    <td className="py-2 pr-3">
                                        <span
                                            className="block h-1.5 overflow-hidden rounded-full"
                                            style={{
                                                backgroundColor:
                                                    programColors.track,
                                            }}
                                        >
                                            <span
                                                className="block h-full rounded-full bg-primary"
                                                style={{
                                                    width: `${step.average_progress}%`,
                                                }}
                                            />
                                        </span>
                                    </td>
                                    <td className="py-2 text-right font-medium text-foreground tabular-nums">
                                        {step.average_progress}%
                                    </td>
                                    <td className="py-2 pl-3 text-right text-muted-foreground tabular-nums">
                                        {numberFormat.format(
                                            step.projects_started,
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </DashboardPanel>

                <DashboardPanel
                    title="Status dan kepatuhan"
                    description="Overdue bila ada step yang lewat target selesai."
                    contentClassName="grid gap-6"
                >
                    {data.total > 0 ? (
                        <>
                            <Meter
                                percent={data.on_time.percent}
                                tone="good"
                                caption="project on-time"
                                filledLabel={`On-Time ${numberFormat.format(data.on_time.count)}`}
                                restLabel={`Overdue ${numberFormat.format(data.on_time.overdue)}`}
                                label="Project on-time"
                            />
                            <Meter
                                percent={data.biweekly.percent}
                                caption="laporan bi-weekly tepat waktu"
                                filledLabel={`Tepat waktu ${numberFormat.format(data.biweekly.compliant)}`}
                                restLabel={`Terlambat ${numberFormat.format(data.biweekly.late)}`}
                                label="Kepatuhan bi-weekly"
                            />
                            <Meter
                                percent={data.mentoring.percent}
                                caption="mentoring sesuai jadwal"
                                filledLabel={`Sesuai jadwal ${numberFormat.format(data.mentoring.compliant)}`}
                                restLabel={`Tidak sesuai ${numberFormat.format(data.mentoring.late)}`}
                                label="Kepatuhan mentoring"
                            />
                        </>
                    ) : (
                        <PanelEmpty
                            title="Belum ada project"
                            description="Status tampil setelah proposal disetujui."
                        />
                    )}
                </DashboardPanel>
            </div>

            <ProjectList data={data} />
        </div>
    );
}
