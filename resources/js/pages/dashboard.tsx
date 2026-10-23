import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    CalendarCheck2,
    CalendarClock,
    ClipboardList,
    FileText,
    FolderKanban,
    Gauge,
    Inbox,
    MessageCircleMore,
    type LucideIcon,
} from 'lucide-react';
import { AdminOverview } from '@/components/dashboard/admin-overview';
import type { AdminOverviewData } from '@/components/dashboard/admin-overview';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { dashboard } from '@/routes';

type RoleDashboardData = {
    summary: {
        pending_tasks: number;
        proposals: number;
        active_projects: number;
        average_progress: number | null;
        biweekly_on_time: number;
        biweekly_late: number;
        mentoring_sessions: number;
        mentoring_feedback_pending: number;
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

const summaryCards: {
    key: keyof RoleDashboardData['summary'];
    label: string;
    icon: LucideIcon;
    suffix?: string;
}[] = [
    { key: 'pending_tasks', label: 'Tugas tertunda', icon: ClipboardList },
    { key: 'proposals', label: 'Proposal diproses', icon: FileText },
    { key: 'active_projects', label: 'Proyek aktif', icon: FolderKanban },
    {
        key: 'average_progress',
        label: 'Rata-rata progres',
        icon: Gauge,
        suffix: '%',
    },
    {
        key: 'biweekly_on_time',
        label: 'Bi-weekly tepat waktu',
        icon: CalendarCheck2,
    },
    { key: 'biweekly_late', label: 'Bi-weekly terlambat', icon: CalendarClock },
    {
        key: 'mentoring_sessions',
        label: 'Sesi mentoring',
        icon: MessageCircleMore,
    },
    {
        key: 'mentoring_feedback_pending',
        label: 'Feedback menunggu tanggapan',
        icon: Inbox,
    },
];

function StatusBadge({ status, label }: { status: string; label: string }) {
    const color =
        status === 'APPROVED'
            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
            : status === 'REVISION_REQUIRED'
              ? 'border-amber-200 bg-amber-50 text-amber-700'
              : status === 'REJECTED'
                ? 'border-red-200 bg-red-50 text-red-700'
                : 'border-blue-200 bg-blue-50 text-blue-700';

    return <Badge className={color}>{label}</Badge>;
}

function EmptyState({
    title,
    description,
}: {
    title: string;
    description: string;
}) {
    return (
        <Empty className="min-h-0 gap-2 rounded-lg px-6 py-8">
            <EmptyHeader>
                <EmptyMedia variant="icon">
                    <Inbox aria-hidden="true" />
                </EmptyMedia>
                <EmptyTitle>{title}</EmptyTitle>
                <EmptyDescription>{description}</EmptyDescription>
            </EmptyHeader>
        </Empty>
    );
}

function RoleOverview({ overview }: { overview: RoleDashboardData }) {
    return (
        <div className="flex flex-col gap-6">
            <section
                className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                aria-label="Ringkasan QCC"
            >
                {summaryCards.map(({ key, label, icon: Icon, suffix }) => (
                    <Card key={key} className="gap-0 py-0">
                        <CardContent className="flex items-start justify-between gap-3 p-5">
                            <div className="space-y-1">
                                <p className="text-[13px] text-muted-foreground">
                                    {label}
                                </p>
                                <p className="text-2xl font-semibold text-foreground">
                                    {overview.summary[key] ?? '—'}
                                    {overview.summary[key] === null
                                        ? ''
                                        : suffix}
                                </p>
                            </div>
                            <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/8 text-primary">
                                <Icon
                                    className="size-[18px]"
                                    aria-hidden="true"
                                />
                            </span>
                        </CardContent>
                    </Card>
                ))}
            </section>

            <section className="grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_minmax(360px,0.75fr)]">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base font-semibold">
                            Proyek dan progres
                        </CardTitle>
                        <CardDescription>
                            Proyek yang tersedia sesuai akses akun Anda.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {overview.projects.length === 0 ? (
                            <EmptyState
                                title="Belum ada proyek"
                                description="Proyek yang dapat Anda akses akan tampil di sini."
                            />
                        ) : (
                            <div className="divide-y">
                                {overview.projects.map((project) => (
                                    <Link
                                        key={project.id}
                                        href={project.href}
                                        className="flex flex-col gap-3 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div className="flex min-w-0 flex-col gap-2">
                                            <span className="truncate text-sm font-medium text-foreground">
                                                {project.title}
                                            </span>
                                            <span className="flex items-center gap-2 text-xs text-muted-foreground">
                                                <StatusBadge
                                                    status={project.status}
                                                    label={project.status_label}
                                                />
                                                {project.steps_total > 0 && (
                                                    <span>
                                                        {
                                                            project.steps_completed
                                                        }
                                                        /{project.steps_total}{' '}
                                                        langkah selesai
                                                    </span>
                                                )}
                                            </span>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-3 sm:w-40">
                                            {project.progress !== null && (
                                                <div
                                                    className="h-2 flex-1 overflow-hidden rounded-full bg-muted"
                                                    role="progressbar"
                                                    aria-label={`Progres ${project.title}`}
                                                    aria-valuemin={0}
                                                    aria-valuemax={100}
                                                    aria-valuenow={
                                                        project.progress
                                                    }
                                                >
                                                    <div
                                                        className="h-full rounded-full bg-primary transition-[width]"
                                                        style={{
                                                            width: `${project.progress}%`,
                                                        }}
                                                    />
                                                </div>
                                            )}
                                            <span className="w-9 text-right text-xs font-medium text-foreground">
                                                {project.progress === null
                                                    ? '—'
                                                    : `${project.progress}%`}
                                            </span>
                                            <ArrowUpRight
                                                className="size-4 text-muted-foreground"
                                                aria-hidden="true"
                                            />
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base font-semibold">
                            Tugas tertunda
                        </CardTitle>
                        <CardDescription>
                            Tindakan yang menunggu Anda berdasarkan hak akses.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {overview.tasks.length === 0 ? (
                            <EmptyState
                                title="Tidak ada tugas tertunda"
                                description="Tugas review, revisi, dan action item akan muncul di sini."
                            />
                        ) : (
                            <div className="flex flex-col divide-y">
                                {overview.tasks.map((task) => (
                                    <Link
                                        key={task.key}
                                        href={task.href}
                                        className="flex items-start justify-between gap-3 py-3 first:pt-0 last:pb-0"
                                    >
                                        <span className="flex min-w-0 flex-col gap-1">
                                            <span className="truncate text-sm font-medium text-foreground">
                                                {task.title}
                                            </span>
                                            <span className="truncate text-xs text-muted-foreground">
                                                {task.project} · {task.detail}
                                            </span>
                                        </span>
                                        <span className="shrink-0 text-xs text-muted-foreground">
                                            {task.date ?? '—'}
                                        </span>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </section>
        </div>
    );
}

export default function Dashboard({
    adminOverview,
    roleOverview,
}: {
    adminOverview: AdminOverviewData | null;
    roleOverview: RoleDashboardData | null;
}) {
    const { auth } = usePage().props;
    const firstName = auth.user?.name.split(' ')[0] ?? '';

    return (
        <>
            <Head title="Dashboard" />
            <main className="mx-auto w-full max-w-[1440px] flex-1 px-4 py-6 md:px-6 md:py-8">
                <PageHeader
                    breadcrumbs={[{ title: 'Dashboard', href: dashboard() }]}
                    title="Dashboard"
                    description={`Selamat datang, ${firstName}. Berikut ringkasan kerja QCC Anda.`}
                />
                {adminOverview ? (
                    <AdminOverview overview={adminOverview} />
                ) : roleOverview ? (
                    <RoleOverview overview={roleOverview} />
                ) : null}
            </main>
        </>
    );
}
