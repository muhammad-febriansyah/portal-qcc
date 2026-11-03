import { Head, Link, router, usePage } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import {
    ArrowRight,
    ArrowUpRight,
    CalendarClock,
    ClipboardList,
    FileSearch,
    FileText,
    Flag,
    FolderKanban,
    Gauge,
    Inbox,
    ListChecks,
    MessageCircleMore,
    RotateCcw,
    Users,
    type LucideIcon,
} from 'lucide-react';
import { lazy, Suspense } from 'react';
import type { BiweeklyCounts } from '@/components/charts/biweekly-chart';
import { AdminOverview } from '@/components/dashboard/admin-overview';
import type { AdminOverviewData } from '@/components/dashboard/admin-overview';
import {
    ChartFallback,
    DashboardPanel,
    KpiCard,
    numberFormat,
    PanelEmpty,
    ProgressBar,
    surfaceClassName,
    useMounted,
} from '@/components/dashboard/dashboard-kit';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

const BiweeklyChart = lazy(() => import('@/components/charts/biweekly-chart'));

type RoleDashboardData = {
    summary: {
        pending_tasks: number;
        proposals: number;
        active_projects: number;
        average_progress: number | null;
        biweekly_on_time: number;
        biweekly_late: number;
        biweekly_overdue: number;
        mentoring_sessions: number;
        mentoring_feedback_pending: number;
    };
    monitoring: {
        biweekly_reports: {
            id: number;
            project: string;
            period_no: number;
            ends_on: string;
            status: string;
            status_label: string;
            overdue: boolean;
            href: string;
        }[];
        step_statuses: { status: string; label: string; count: number }[];
        steps: {
            project: string;
            step_number: number;
            step_label: string;
            status: string;
            status_label: string;
            progress: number;
            target_date: string | null;
            href: string;
        }[];
        mentoring_reports: {
            project: string;
            topic: string;
            session_at: string;
            status: string;
            status_label: string;
            feedback_pending: boolean;
            href: string;
        }[];
    };
    projects: {
        id: number;
        title: string;
        status: string;
        status_label: string;
        steps_total: number;
        steps_completed: number;
        progress: number | null;
        href: string;
    }[];
    tasks: {
        key: string;
        title: string;
        project: string;
        detail: string;
        href: string;
        date: string | null;
    }[];
};

const taskKinds: Record<string, { icon: LucideIcon; tone: string }> = {
    proposal: { icon: FileSearch, tone: 'bg-warning/10 text-warning' },
    'team-composition': { icon: Users, tone: 'bg-primary/10 text-primary' },
    step: { icon: ListChecks, tone: 'bg-violet/10 text-violet' },
    action: { icon: Flag, tone: 'bg-teal/10 text-teal' },
    revision: { icon: RotateCcw, tone: 'bg-destructive/10 text-destructive' },
};

const fallbackTaskKind = {
    icon: ClipboardList,
    tone: 'bg-muted text-muted-foreground',
};

const biweeklyLegend: {
    key: keyof BiweeklyCounts;
    label: string;
    color: string;
}[] = [
    { key: 'on_time', label: 'Tepat waktu', color: '#16834A' },
    { key: 'late', label: 'Terlambat', color: '#F79009' },
    { key: 'overdue', label: 'Belum dikirim, lewat tenggat', color: '#D92D20' },
];

function StatusBadge({ status, label }: { status: string; label: string }) {
    const color =
        status === 'APPROVED'
            ? 'border-success/20 bg-success/10 text-success'
            : status === 'REVISION_REQUIRED'
              ? 'border-warning/20 bg-warning/10 text-warning'
              : status === 'REJECTED'
                ? 'border-destructive/20 bg-destructive/10 text-destructive'
                : status === 'DRAFT' || status === 'NOT_STARTED'
                  ? 'border-border bg-muted text-muted-foreground'
                  : status === 'ACKNOWLEDGED'
                    ? 'border-success/20 bg-success/10 text-success'
                    : 'border-primary/20 bg-primary/10 text-primary';

    return <Badge className={color}>{label}</Badge>;
}

function formatDate(value: string | null): string | null {
    if (!value) {
        return null;
    }

    return format(parseISO(value), 'd MMM yyyy', { locale: indonesianLocale });
}

function DashboardFilters({
    batches,
    selectedBatchId,
    selectedPeriod,
}: {
    batches: { id: number; name: string }[];
    selectedBatchId: number | null;
    selectedPeriod: 'all' | '30' | '90' | '365';
}) {
    const visit = (params: Record<string, string>) =>
        router.get(dashboard.url(), params, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });

    const selectClassName =
        'h-10 rounded-lg border border-border/70 bg-background px-3 text-sm text-foreground shadow-[0_1px_2px_rgba(24,35,55,0.03)] outline-none transition-colors focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30';

    return (
        <div className="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end">
            <div className="grid gap-1.5">
                <label
                    htmlFor="dashboard-batch"
                    className="text-xs font-medium text-muted-foreground"
                >
                    Batch QCC
                </label>
                <select
                    id="dashboard-batch"
                    className={cn(selectClassName, 'min-w-0 sm:min-w-52')}
                    value={selectedBatchId ?? ''}
                    onChange={(event) =>
                        visit({
                            ...(event.target.value
                                ? { batch_id: event.target.value }
                                : {}),
                            period: selectedPeriod,
                        })
                    }
                >
                    <option value="">Semua batch</option>
                    {batches.map((batch) => (
                        <option key={batch.id} value={batch.id}>
                            {batch.name}
                        </option>
                    ))}
                </select>
            </div>
            <div className="grid gap-1.5">
                <label
                    htmlFor="dashboard-period"
                    className="text-xs font-medium text-muted-foreground"
                >
                    Periode
                </label>
                <select
                    id="dashboard-period"
                    className={cn(selectClassName, 'min-w-0 sm:min-w-48')}
                    value={selectedPeriod}
                    onChange={(event) =>
                        visit({
                            ...(selectedBatchId
                                ? { batch_id: String(selectedBatchId) }
                                : {}),
                            period: event.target.value,
                        })
                    }
                >
                    <option value="all">Semua periode</option>
                    <option value="30">30 hari terakhir</option>
                    <option value="90">90 hari terakhir</option>
                    <option value="365">1 tahun terakhir</option>
                </select>
            </div>
        </div>
    );
}

function BiweeklyPanel({
    counts,
    reports,
    scopeLabel,
}: {
    counts: BiweeklyCounts;
    reports: RoleDashboardData['monitoring']['biweekly_reports'];
    scopeLabel: string;
}) {
    const isMounted = useMounted();
    const total = counts.on_time + counts.late + counts.overdue;

    return (
        <DashboardPanel
            title="Status bi-weekly"
            description={`Ketepatan laporan ${scopeLabel.toLowerCase()}.`}
        >
            {total === 0 && reports.length === 0 ? (
                <PanelEmpty
                    icon={CalendarClock}
                    title="Belum ada laporan terkirim"
                    description="Laporan bi-weekly akan tampil setelah jadwal berjalan."
                />
            ) : total > 0 ? (
                <div className="grid items-center gap-6 sm:grid-cols-[180px_minmax(0,1fr)] lg:grid-cols-1 2xl:grid-cols-[180px_minmax(0,1fr)]">
                    <div
                        className="mx-auto w-full max-w-[200px] min-w-0"
                        role="img"
                        aria-label={`Kepatuhan bi-weekly: ${counts.on_time} tepat waktu, ${counts.late} terlambat, ${counts.overdue} belum dikirim.`}
                    >
                        {isMounted ? (
                            <Suspense fallback={<ChartFallback height={200} />}>
                                <BiweeklyChart counts={counts} />
                            </Suspense>
                        ) : (
                            <ChartFallback height={200} />
                        )}
                    </div>
                    <ul className="grid gap-3">
                        {biweeklyLegend.map((item) => {
                            const value = counts[item.key];
                            const percent =
                                total === 0
                                    ? 0
                                    : Math.round((value / total) * 100);

                            return (
                                <li
                                    key={item.key}
                                    className="grid gap-1.5 rounded-lg border border-border/60 px-3.5 py-2.5"
                                >
                                    <div className="flex items-center justify-between gap-3">
                                        <span className="flex min-w-0 items-center gap-2 text-sm text-muted-foreground">
                                            <span
                                                className="size-2 shrink-0 rounded-full"
                                                style={{
                                                    backgroundColor: item.color,
                                                }}
                                                aria-hidden="true"
                                            />
                                            <span className="truncate">
                                                {item.label}
                                            </span>
                                        </span>
                                        <span className="text-sm font-semibold tabular-nums">
                                            {numberFormat.format(value)}
                                            <span className="ml-1 text-xs font-normal text-muted-foreground">
                                                ({percent}%)
                                            </span>
                                        </span>
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                </div>
            ) : null}
            {reports.length > 0 && (
                <div className="mt-5 grid gap-2 border-t border-border/60 pt-4">
                    <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        Laporan terbaru
                    </p>
                    {reports.map((report) => (
                        <Link
                            key={report.id}
                            href={report.href}
                            className="flex min-w-0 items-center justify-between gap-3 rounded-lg border border-border/60 px-3 py-2.5 transition-colors hover:border-primary/30 hover:bg-primary/[0.03]"
                        >
                            <span className="grid min-w-0 gap-1">
                                <span className="truncate text-sm font-medium text-foreground">
                                    {report.project}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    Periode {report.period_no} · Tenggat{' '}
                                    {formatDate(report.ends_on) ?? '—'}
                                </span>
                            </span>
                            <StatusBadge
                                status={
                                    report.overdue
                                        ? 'REVISION_REQUIRED'
                                        : report.status
                                }
                                label={
                                    report.overdue
                                        ? 'Terlambat'
                                        : report.status_label
                                }
                            />
                        </Link>
                    ))}
                </div>
            )}
        </DashboardPanel>
    );
}

function StepsPanel({
    statuses,
    steps,
    scopeLabel,
}: {
    statuses: RoleDashboardData['monitoring']['step_statuses'];
    steps: RoleDashboardData['monitoring']['steps'];
    scopeLabel: string;
}) {
    return (
        <DashboardPanel
            title="Status 8 Step"
            description={`Tahap pengerjaan terbaru ${scopeLabel.toLowerCase()}.`}
        >
            {statuses.every((item) => item.count === 0) ? (
                <PanelEmpty
                    icon={ListChecks}
                    title="Belum ada progres step"
                    description="Status 8 Step muncul setelah proyek disetujui."
                />
            ) : (
                <div className="grid gap-4">
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        {statuses
                            .filter((item) => item.count > 0)
                            .map((item) => (
                                <div
                                    key={item.status}
                                    className="grid gap-1 rounded-lg border border-border/60 p-3"
                                >
                                    <span className="text-xs text-muted-foreground">
                                        {item.label}
                                    </span>
                                    <span className="text-xl font-semibold tabular-nums">
                                        {numberFormat.format(item.count)}
                                    </span>
                                </div>
                            ))}
                    </div>
                    {steps.length > 0 && (
                        <div className="grid gap-2 border-t border-border/60 pt-4">
                            <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                Step terakhir diperbarui
                            </p>
                            {steps.map((step) => (
                                <Link
                                    key={`${step.project}-${step.step_number}`}
                                    href={step.href}
                                    className="flex min-w-0 items-center justify-between gap-3 rounded-lg border border-border/60 px-3 py-2.5 transition-colors hover:border-primary/30 hover:bg-primary/[0.03]"
                                >
                                    <span className="grid min-w-0 gap-1">
                                        <span className="truncate text-sm font-medium text-foreground">
                                            {step.project}
                                        </span>
                                        <span className="truncate text-xs text-muted-foreground">
                                            Step {step.step_number} ·{' '}
                                            {step.step_label} · {step.progress}%
                                            {step.target_date && (
                                                <>
                                                    {' '}
                                                    · Tenggat{' '}
                                                    {formatDate(
                                                        step.target_date,
                                                    )}
                                                </>
                                            )}
                                        </span>
                                    </span>
                                    <StatusBadge
                                        status={step.status}
                                        label={step.status_label}
                                    />
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
            )}
        </DashboardPanel>
    );
}

function MentoringPanel({
    sessions,
    feedbackPending,
    reports,
    scopeLabel,
}: {
    sessions: number;
    feedbackPending: number;
    reports: RoleDashboardData['monitoring']['mentoring_reports'];
    scopeLabel: string;
}) {
    return (
        <DashboardPanel
            title="Mentoring"
            description={`Sesi dan tanggapan terbaru ${scopeLabel.toLowerCase()}.`}
        >
            <div className="grid gap-3">
                <div className="flex items-center justify-between gap-4 rounded-xl border border-border/60 p-4">
                    <div className="grid gap-1">
                        <p className="text-sm text-muted-foreground">
                            Sesi mentoring
                        </p>
                        <p className="text-3xl leading-none font-semibold tracking-tight tabular-nums">
                            {numberFormat.format(sessions)}
                        </p>
                    </div>
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-violet/10 text-violet">
                        <MessageCircleMore
                            className="size-5"
                            aria-hidden="true"
                        />
                    </span>
                </div>
                <div className="flex items-center justify-between gap-4 rounded-xl border border-border/60 p-4">
                    <div className="grid gap-1">
                        <p className="text-sm text-muted-foreground">
                            Feedback menunggu tanggapan
                        </p>
                        <p
                            className={cn(
                                'text-3xl leading-none font-semibold tracking-tight tabular-nums',
                                feedbackPending > 0
                                    ? 'text-warning'
                                    : 'text-foreground',
                            )}
                        >
                            {numberFormat.format(feedbackPending)}
                        </p>
                    </div>
                    <span
                        className={cn(
                            'flex size-10 shrink-0 items-center justify-center rounded-xl',
                            feedbackPending > 0
                                ? 'bg-warning/10 text-warning'
                                : 'bg-muted text-muted-foreground',
                        )}
                    >
                        <Inbox className="size-5" aria-hidden="true" />
                    </span>
                </div>
            </div>
            {reports.length > 0 ? (
                <div className="mt-5 grid gap-2 border-t border-border/60 pt-4">
                    <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        Sesi terbaru
                    </p>
                    {reports.map((report) => (
                        <Link
                            key={`${report.project}-${report.session_at}-${report.topic}`}
                            href={report.href}
                            className="grid gap-2 rounded-lg border border-border/60 px-3 py-2.5 transition-colors hover:border-primary/30 hover:bg-primary/[0.03]"
                        >
                            <span className="flex min-w-0 items-start justify-between gap-3">
                                <span className="grid min-w-0 gap-1">
                                    <span className="truncate text-sm font-medium text-foreground">
                                        {report.topic}
                                    </span>
                                    <span className="truncate text-xs text-muted-foreground">
                                        {report.project} ·{' '}
                                        {formatDate(report.session_at) ?? '—'}
                                    </span>
                                </span>
                                <StatusBadge
                                    status={report.status}
                                    label={report.status_label}
                                />
                            </span>
                            {report.feedback_pending && (
                                <span className="w-fit rounded-md bg-warning/10 px-2 py-1 text-xs font-medium text-warning">
                                    Menunggu tanggapan tim
                                </span>
                            )}
                        </Link>
                    ))}
                </div>
            ) : (
                <PanelEmpty
                    icon={MessageCircleMore}
                    title="Belum ada sesi mentoring"
                    description="Sesi mentoring akan tampil setelah laporan sesi dibuat."
                />
            )}
        </DashboardPanel>
    );
}

function ProjectsPanel({
    projects,
}: {
    projects: RoleDashboardData['projects'];
}) {
    return (
        <DashboardPanel
            title="Proyek dan progres"
            description="Lima proyek terbaru yang dapat Anda akses."
        >
            {projects.length === 0 ? (
                <PanelEmpty
                    icon={FolderKanban}
                    title="Belum ada proyek"
                    description="Proyek yang dapat Anda akses akan tampil di sini."
                />
            ) : (
                <ul className="grid gap-2.5">
                    {projects.map((project) => (
                        <li key={project.id}>
                            <Link
                                href={project.href}
                                className="group flex flex-col gap-3 rounded-xl border border-border/60 p-4 transition-colors hover:border-primary/30 hover:bg-primary/[0.03] focus-visible:border-ring focus-visible:outline-none sm:flex-row sm:items-center sm:gap-5"
                            >
                                <div className="grid min-w-0 flex-1 gap-2">
                                    <span className="truncate text-sm font-medium text-foreground">
                                        {project.title}
                                    </span>
                                    <span className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                        <StatusBadge
                                            status={project.status}
                                            label={project.status_label}
                                        />
                                        {project.steps_total > 0 && (
                                            <span className="tabular-nums">
                                                {project.steps_completed}/
                                                {project.steps_total} langkah
                                                selesai
                                            </span>
                                        )}
                                    </span>
                                </div>
                                <div className="flex items-center gap-3 sm:w-52 sm:shrink-0">
                                    {project.progress !== null ? (
                                        <ProgressBar
                                            value={project.progress}
                                            label={`Progres ${project.title}`}
                                            className="flex-1"
                                        />
                                    ) : (
                                        <span className="flex-1 text-xs text-muted-foreground">
                                            Belum ada progres
                                        </span>
                                    )}
                                    <span className="w-10 text-right text-xs font-semibold text-foreground tabular-nums">
                                        {project.progress === null
                                            ? '—'
                                            : `${project.progress}%`}
                                    </span>
                                    <ArrowUpRight
                                        className="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-primary"
                                        aria-hidden="true"
                                    />
                                </div>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </DashboardPanel>
    );
}

function TasksPanel({ tasks }: { tasks: RoleDashboardData['tasks'] }) {
    return (
        <DashboardPanel
            title="Tugas tertunda"
            description="Tindakan yang menunggu Anda berdasarkan hak akses."
        >
            {tasks.length === 0 ? (
                <PanelEmpty
                    icon={ClipboardList}
                    title="Tidak ada tugas tertunda"
                    description="Tugas review, revisi, dan action item akan muncul di sini."
                />
            ) : (
                <ul className="grid gap-2.5">
                    {tasks.map((task) => {
                        const kind =
                            taskKinds[task.key.replace(/-\d+$/, '')] ??
                            fallbackTaskKind;
                        const Icon = kind.icon;
                        const date = formatDate(task.date);

                        return (
                            <li key={task.key}>
                                <Link
                                    href={task.href}
                                    className="group flex items-start gap-3 rounded-xl border border-border/60 p-3.5 transition-colors hover:border-primary/30 hover:bg-primary/[0.03] focus-visible:border-ring focus-visible:outline-none"
                                >
                                    <span
                                        className={cn(
                                            'flex size-8 shrink-0 items-center justify-center rounded-lg',
                                            kind.tone,
                                        )}
                                    >
                                        <Icon
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <span className="grid min-w-0 flex-1 gap-0.5">
                                        <span className="truncate text-sm font-medium text-foreground">
                                            {task.title}
                                        </span>
                                        <span className="truncate text-xs text-muted-foreground">
                                            {task.project}
                                        </span>
                                        <span className="truncate text-xs text-muted-foreground">
                                            {task.detail}
                                        </span>
                                    </span>
                                    <span className="flex shrink-0 items-center gap-1.5 text-xs text-muted-foreground tabular-nums">
                                        {date ?? '—'}
                                        <ArrowRight
                                            className="size-3.5 opacity-0 transition-opacity group-hover:opacity-100"
                                            aria-hidden="true"
                                        />
                                    </span>
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            )}
        </DashboardPanel>
    );
}

function RoleOverview({
    overview,
    scopeLabel,
}: {
    overview: RoleDashboardData;
    scopeLabel: string;
}) {
    const { summary } = overview;

    return (
        <div className="flex flex-col gap-6">
            <section
                className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                aria-label="Ringkasan QCC"
            >
                <KpiCard
                    label="Tugas tertunda"
                    value={numberFormat.format(summary.pending_tasks)}
                    helper="Review, revisi, dan action item"
                    icon={ClipboardList}
                    tone="bg-warning/10 text-warning"
                />
                <KpiCard
                    label="Proyek aktif"
                    value={numberFormat.format(summary.active_projects)}
                    helper="Proposal sudah disetujui"
                    icon={FolderKanban}
                    tone="bg-primary/10 text-primary"
                />
                <KpiCard
                    label="Rata-rata progres"
                    value={
                        summary.average_progress === null
                            ? '—'
                            : `${summary.average_progress}%`
                    }
                    helper="Dari langkah proyek aktif"
                    icon={Gauge}
                    tone="bg-success/10 text-success"
                    footer={
                        <ProgressBar
                            value={summary.average_progress ?? 0}
                            label="Rata-rata progres proyek aktif"
                        />
                    }
                />
                <KpiCard
                    label="Proposal diproses"
                    value={numberFormat.format(summary.proposals)}
                    helper="Dari draft sampai revisi"
                    icon={FileText}
                    tone="bg-violet/10 text-violet"
                />
            </section>

            <section
                className="grid gap-6 xl:grid-cols-2"
                aria-label="Status QCC"
            >
                <BiweeklyPanel
                    counts={{
                        on_time: summary.biweekly_on_time,
                        late: summary.biweekly_late,
                        overdue: summary.biweekly_overdue,
                    }}
                    reports={overview.monitoring.biweekly_reports}
                    scopeLabel={scopeLabel}
                />
                <StepsPanel
                    statuses={overview.monitoring.step_statuses}
                    steps={overview.monitoring.steps}
                    scopeLabel={scopeLabel}
                />
                <MentoringPanel
                    sessions={summary.mentoring_sessions}
                    feedbackPending={summary.mentoring_feedback_pending}
                    reports={overview.monitoring.mentoring_reports}
                    scopeLabel={scopeLabel}
                />
            </section>

            <section className="grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_minmax(0,0.75fr)]">
                <ProjectsPanel projects={overview.projects} />
                <TasksPanel tasks={overview.tasks} />
            </section>
        </div>
    );
}

export default function Dashboard({
    adminOverview,
    roleOverview,
    batches,
    selectedBatchId,
    selectedPeriod,
}: {
    adminOverview: AdminOverviewData | null;
    roleOverview: RoleDashboardData | null;
    batches: { id: number; name: string }[];
    selectedBatchId: number | null;
    selectedPeriod: 'all' | '30' | '90' | '365';
}) {
    const { auth, roles } = usePage<{
        auth: { user: { name: string } | null };
        roles?: string[];
    }>().props;
    const firstName = auth.user?.name.split(' ')[0] ?? '';
    const roleNames = roles ?? [];
    const scopeLabel = roleNames.includes('Team Leader')
        ? 'tim Anda'
        : roleNames.includes('SIMer / Member')
          ? 'proyek Anda'
          : 'proyek yang dapat Anda akses';

    return (
        <>
            <Head title="Dashboard" />
            <main className="mx-auto w-full max-w-[1440px] flex-1 px-4 py-6 md:px-6 md:py-8">
                <PageHeader
                    breadcrumbs={[{ title: 'Dashboard', href: dashboard() }]}
                    title="Dashboard"
                    description={`Selamat datang, ${firstName}. Berikut ringkasan kerja QCC Anda.`}
                />

                <div
                    className={cn(
                        'mb-8 flex flex-col gap-4 rounded-xl border bg-card p-4 sm:p-5 lg:flex-row lg:items-end lg:justify-between',
                        surfaceClassName,
                    )}
                >
                    <DashboardFilters
                        batches={batches}
                        selectedBatchId={selectedBatchId}
                        selectedPeriod={selectedPeriod}
                    />
                    <div className="flex flex-col gap-2 lg:max-w-md lg:items-end">
                        {roleNames.length > 0 && (
                            <div className="flex flex-wrap gap-1.5 lg:justify-end">
                                {roleNames.map((role) => (
                                    <Badge
                                        key={role}
                                        variant="outline"
                                        className="font-medium"
                                    >
                                        {role}
                                    </Badge>
                                ))}
                            </div>
                        )}
                        <p className="text-xs text-muted-foreground lg:text-right">
                            Periode proyek dihitung dari tanggal dibuat; laporan
                            dari tanggal periode/sesi. Tugas tertunda tetap
                            mencakup semua tenggat.
                        </p>
                    </div>
                </div>

                {adminOverview && roleOverview ? (
                    <Tabs defaultValue="system" className="gap-6">
                        <TabsList className="w-full justify-start overflow-x-auto sm:w-fit">
                            <TabsTrigger
                                value="system"
                                className="px-3 sm:px-4"
                            >
                                Ringkasan sistem
                            </TabsTrigger>
                            <TabsTrigger value="role" className="px-3 sm:px-4">
                                Ringkasan QCC Anda
                            </TabsTrigger>
                        </TabsList>
                        <TabsContent value="system">
                            <AdminOverview overview={adminOverview} />
                        </TabsContent>
                        <TabsContent value="role">
                            <RoleOverview
                                overview={roleOverview}
                                scopeLabel={scopeLabel}
                            />
                        </TabsContent>
                    </Tabs>
                ) : adminOverview ? (
                    <AdminOverview overview={adminOverview} />
                ) : roleOverview ? (
                    <RoleOverview
                        overview={roleOverview}
                        scopeLabel={scopeLabel}
                    />
                ) : null}
            </main>
        </>
    );
}
