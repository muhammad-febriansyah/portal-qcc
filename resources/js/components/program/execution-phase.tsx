import {
    AlertCircle,
    CalendarCheck,
    CheckCircle2,
    Flag,
    FolderKanban,
    Search,
    Users,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    DashboardPanel,
    KpiCard,
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
import { BarList, DonutWithLegend, programColors } from './program-kit';
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

function percentOf(value: number, total: number): number {
    return total > 0 ? Math.round((value / total) * 100) : 0;
}

function StepDots({ steps }: { steps: ExecutionProjectRow['steps'] }) {
    return (
        <ol className="flex items-center gap-1" aria-label="Progres 8 step">
            {steps.map((state, index) => (
                <li
                    key={index}
                    className={cn(
                        'flex size-6 items-center justify-center rounded-full text-[11px] font-semibold tabular-nums',
                        state === 'done' &&
                            'bg-primary text-primary-foreground',
                        state === 'current' &&
                            'border-2 border-primary bg-card text-primary',
                        state === 'todo' && 'bg-muted text-muted-foreground',
                    )}
                    title={`Step ${index + 1}: ${state === 'done' ? 'selesai' : state === 'current' ? 'berjalan' : 'belum mulai'}`}
                >
                    {state === 'done' ? (
                        <CheckCircle2 className="size-3.5" aria-hidden="true" />
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
                                    <TableHead className="min-w-56">
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
                                        <TableCell className="whitespace-nowrap">
                                            {project.leader ?? '—'}
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap">
                                            {project.current_step
                                                ? `Step ${project.current_step.number} – ${project.current_step.label}`
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
        <div className="grid gap-6">
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <KpiCard
                    label="Total Project Aktif"
                    value={numberFormat.format(data.total)}
                    helper="Proposal yang disetujui"
                    icon={FolderKanban}
                    tone="bg-[#C4844F]/12 text-[#8F5A2E]"
                />
                <KpiCard
                    label="Rata-rata Progres Project"
                    value={`${data.average_progress}%`}
                    helper="Rata-rata dari 8 step QCC"
                    icon={Flag}
                    tone="bg-[#16834A]/10 text-[#16834A]"
                />
                <KpiCard
                    label="Kepatuhan Bi-Weekly"
                    value={`${data.biweekly.percent}%`}
                    helper={`${data.biweekly.compliant} dari ${data.total} project tepat waktu`}
                    icon={CalendarCheck}
                    tone="bg-primary/10 text-primary"
                />
                <KpiCard
                    label="Kepatuhan Mentoring"
                    value={`${data.mentoring.percent}%`}
                    helper={`${data.mentoring.compliant} dari ${data.total} project sesuai jadwal`}
                    icon={Users}
                    tone="bg-[#7A5AF8]/10 text-[#5B3FD6]"
                />
                <KpiCard
                    label="Project On-Time"
                    value={`${data.on_time.percent}%`}
                    helper={`${data.on_time.count} dari ${data.total} project`}
                    icon={CheckCircle2}
                    tone="bg-[#16834A]/10 text-[#16834A]"
                />
            </div>

            <DashboardPanel
                title="Progress 8 Step QCC"
                description="Rata-rata progres setiap step dari seluruh project."
            >
                <ol className="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-8">
                    {data.steps.map((step) => (
                        <li
                            key={step.number}
                            className="grid gap-1 rounded-xl border border-border/70 bg-muted/20 px-3 py-3 text-center"
                            title={step.label}
                        >
                            <span className="mx-auto flex size-7 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground tabular-nums">
                                {step.number}
                            </span>
                            <span className="text-xs leading-snug text-muted-foreground">
                                {STEP_SHORT_LABELS[step.number - 1] ??
                                    step.label}
                            </span>
                            <span className="text-lg font-semibold text-foreground tabular-nums">
                                {step.average_progress}%
                            </span>
                        </li>
                    ))}
                </ol>
            </DashboardPanel>

            <div className="grid gap-6 xl:grid-cols-3">
                <DashboardPanel
                    title="Status Project"
                    description="Overdue bila ada step yang lewat target selesai."
                >
                    {data.total > 0 ? (
                        <DonutWithLegend
                            label={`${data.on_time.count} project on-time dan ${data.on_time.overdue} overdue.`}
                            centerLabel="Project"
                            centerValue={numberFormat.format(data.total)}
                            unit="project"
                            slices={[
                                {
                                    label: 'On-Time',
                                    value: data.on_time.count,
                                    color: programColors.good,
                                },
                                {
                                    label: 'Overdue',
                                    value: data.on_time.overdue,
                                    color: programColors.critical,
                                },
                            ]}
                            formatValue={(slice) =>
                                `${numberFormat.format(slice.value)} (${percentOf(slice.value, data.total)}%)`
                            }
                        />
                    ) : (
                        <PanelEmpty
                            title="Belum ada project"
                            description="Status tampil setelah proposal disetujui."
                        />
                    )}
                </DashboardPanel>
                <DashboardPanel
                    title="Kepatuhan Bi-Weekly"
                    description="Tepat waktu bila semua laporan yang jatuh tempo dikirim sebelum periode berakhir."
                >
                    {data.total > 0 ? (
                        <DonutWithLegend
                            label={`${data.biweekly.compliant} project tepat waktu, ${data.biweekly.late} terlambat.`}
                            centerLabel={`${data.biweekly.compliant} / ${data.total}`}
                            centerValue={`${data.biweekly.percent}%`}
                            unit="project"
                            slices={[
                                {
                                    label: 'Tepat waktu',
                                    value: data.biweekly.compliant,
                                    color: programColors.primary,
                                },
                                {
                                    label: 'Terlambat',
                                    value: data.biweekly.late,
                                    color: programColors.muted,
                                },
                            ]}
                            formatValue={(slice) =>
                                `${numberFormat.format(slice.value)} (${percentOf(slice.value, data.total)}%)`
                            }
                        />
                    ) : (
                        <PanelEmpty
                            title="Belum ada project"
                            description="Kepatuhan tampil setelah proposal disetujui."
                        />
                    )}
                </DashboardPanel>
                <DashboardPanel
                    title="Kepatuhan Mentoring"
                    description="Sesuai jadwal bila jumlah sesi mentoring mengikuti jadwal batch."
                >
                    {data.total > 0 ? (
                        <DonutWithLegend
                            label={`${data.mentoring.compliant} project sesuai jadwal, ${data.mentoring.late} tidak sesuai.`}
                            centerLabel={`${data.mentoring.compliant} / ${data.total}`}
                            centerValue={`${data.mentoring.percent}%`}
                            unit="project"
                            slices={[
                                {
                                    label: 'Sesuai jadwal',
                                    value: data.mentoring.compliant,
                                    color: programColors.primary,
                                },
                                {
                                    label: 'Tidak sesuai',
                                    value: data.mentoring.late,
                                    color: programColors.muted,
                                },
                            ]}
                            formatValue={(slice) =>
                                `${numberFormat.format(slice.value)} (${percentOf(slice.value, data.total)}%)`
                            }
                        />
                    ) : (
                        <PanelEmpty
                            title="Belum ada project"
                            description="Kepatuhan tampil setelah proposal disetujui."
                        />
                    )}
                </DashboardPanel>
            </div>

            <DashboardPanel
                title="Progress Project per Step"
                description="Jumlah project yang sudah memulai setiap step."
            >
                <BarList
                    label="Jumlah project per step"
                    rows={data.steps.map((step) => ({
                        name: `Step ${step.number} · ${STEP_SHORT_LABELS[step.number - 1] ?? step.label}`,
                        value: step.projects_started,
                        display: numberFormat.format(step.projects_started),
                    }))}
                />
            </DashboardPanel>

            <ProjectList data={data} />
        </div>
    );
}
