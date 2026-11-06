import { Head, Link, usePage } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import {
    ArrowRight,
    BadgeCheck,
    Check,
    ClipboardList,
    FileText,
    FolderKanban,
    GraduationCap,
    X,
} from 'lucide-react';
import {
    DashboardPanel,
    PanelEmpty,
    ProgressBar,
    surfaceClassName,
} from '@/components/dashboard/dashboard-kit';
import { PageHeader } from '@/components/page-header';
import { ProgramDashboard } from '@/components/program/program-dashboard';
import type { ProgramOverview } from '@/components/program/types';
import type { BadgeVariant } from '@/lib/badge-variants';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

type JourneyStep = {
    key: string;
    label: string;
    /** `rejected` marks the last step when the proposal did not pass. */
    state: 'done' | 'current' | 'todo' | 'rejected';
};

type MemberOverview = {
    journey: {
        steps: JourneyStep[];
        next_action: {
            label: string;
            description: string;
            href: string;
        } | null;
    };
    competencies: {
        count: number;
        limit: number;
        verified: number;
        href: string;
    };
    projects: {
        id: number;
        title: string;
        role: 'Leader' | 'Anggota';
        steps_total: number;
        steps_completed: number;
        current_step: {
            number: number;
            label: string;
            status_label: string;
            target_date: string | null;
            progress: number;
        } | null;
        href: string;
    }[];
    proposals: {
        id: number;
        code: string;
        title: string;
        status: string;
        status_label: string;
        updated_at: string | null;
        action_label: string;
        href: string;
    }[];
    tasks: {
        key: string;
        title: string;
        project: string;
        detail: string;
        href: string;
        date: string | null;
        overdue: boolean;
    }[];
    task_count: number;
};

const proposalStatusVariants: Record<string, BadgeVariant> = {
    DRAFT: 'secondary',
    SUBMITTED: 'info',
    UNDER_REVIEW: 'violet',
    REVISION_REQUIRED: 'warning',
    APPROVED: 'success',
    REJECTED: 'destructive',
};

function formatDate(value: string | null): string | null {
    return value
        ? format(parseISO(value), 'd MMM yyyy', { locale: indonesianLocale })
        : null;
}

/** The four stages of the member flow, with the next thing to do. */
function JourneyCard({
    firstName,
    journey,
}: {
    firstName: string;
    journey: MemberOverview['journey'];
}) {
    const action = journey.next_action;

    return (
        <Card className={cn('gap-0 py-0', surfaceClassName)}>
            <div className="grid gap-6 p-5 sm:p-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center lg:gap-10">
                <div className="grid gap-5">
                    <div className="grid gap-1">
                        <h2 className="text-lg font-semibold tracking-tight">
                            Halo, {firstName}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Posisi Anda di alur QCC saat ini.
                        </p>
                    </div>
                    <ol
                        className="grid grid-cols-2 gap-3 sm:flex sm:items-start sm:gap-0"
                        aria-label="Alur QCC saya"
                    >
                        {journey.steps.map((step, index) => (
                            <li
                                key={step.key}
                                className="relative flex items-center gap-2.5 sm:flex-1 sm:flex-col sm:gap-2 sm:text-center"
                                aria-current={
                                    step.state === 'current'
                                        ? 'step'
                                        : undefined
                                }
                            >
                                {index > 0 && (
                                    <span
                                        aria-hidden="true"
                                        className={cn(
                                            'absolute top-4 right-[calc(50%+1.25rem)] left-[calc(-50%+1.25rem)] hidden h-0.5 -translate-y-1/2 rounded-full sm:block',
                                            step.state === 'todo'
                                                ? 'bg-border'
                                                : 'bg-primary',
                                        )}
                                    />
                                )}
                                <span
                                    className={cn(
                                        'relative flex size-8 shrink-0 items-center justify-center rounded-full border-2 text-sm font-semibold',
                                        step.state === 'done' &&
                                            'border-primary bg-primary/10 text-primary',
                                        step.state === 'current' &&
                                            'border-primary bg-primary text-primary-foreground ring-4 ring-primary/15',
                                        step.state === 'todo' &&
                                            'border-border bg-muted text-muted-foreground',
                                        step.state === 'rejected' &&
                                            'border-destructive bg-destructive/10 text-destructive',
                                    )}
                                >
                                    {step.state === 'done' ? (
                                        <Check
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    ) : step.state === 'rejected' ? (
                                        <X
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    ) : (
                                        index + 1
                                    )}
                                </span>
                                <span
                                    className={cn(
                                        'text-xs leading-snug sm:text-sm',
                                        step.state === 'current'
                                            ? 'font-semibold text-primary'
                                            : step.state === 'rejected'
                                              ? 'font-semibold text-destructive'
                                              : step.state === 'done'
                                                ? 'font-medium text-foreground'
                                                : 'text-muted-foreground',
                                    )}
                                >
                                    {step.label}
                                    {step.state === 'done' && (
                                        <span className="sr-only">
                                            , selesai
                                        </span>
                                    )}
                                </span>
                            </li>
                        ))}
                    </ol>
                </div>
                {action && (
                    <div className="grid gap-3 rounded-xl border border-primary/20 bg-primary/[0.04] p-4 lg:w-80">
                        <div className="grid gap-1">
                            <p className="text-xs font-semibold tracking-wide text-primary uppercase">
                                Langkah berikutnya
                            </p>
                            <p className="text-sm text-foreground">
                                {action.description}
                            </p>
                        </div>
                        <Button
                            render={<Link href={action.href} />}
                            nativeButton={false}
                            className="h-10 w-full sm:w-fit lg:w-full"
                        >
                            {action.label}
                            <ArrowRight data-icon="inline-end" />
                        </Button>
                    </div>
                )}
            </div>
        </Card>
    );
}

function TasksPanel({
    tasks,
    total,
}: {
    tasks: MemberOverview['tasks'];
    total: number;
}) {
    return (
        <DashboardPanel
            title="Yang perlu dikerjakan"
            description={
                total > tasks.length
                    ? `${tasks.length} dari ${total} tugas terdekat.`
                    : 'Tugas yang menunggu Anda.'
            }
        >
            {tasks.length === 0 ? (
                <PanelEmpty
                    icon={ClipboardList}
                    title="Tidak ada tugas"
                    description="Revisi, action item, dan tugas leader akan muncul di sini."
                />
            ) : (
                <ul className="grid gap-2.5">
                    {tasks.map((task) => (
                        <li key={task.key}>
                            <Link
                                href={task.href}
                                className="group flex items-start justify-between gap-3 rounded-xl border border-border/60 p-3.5 transition-colors hover:border-primary/30 hover:bg-primary/[0.03] focus-visible:border-ring focus-visible:outline-none"
                            >
                                <div className="grid min-w-0 gap-0.5">
                                    <span className="text-sm font-medium break-words text-foreground">
                                        {task.title}
                                    </span>
                                    <span className="text-xs break-words text-muted-foreground">
                                        {task.project} · {task.detail}
                                    </span>
                                </div>
                                {task.date && (
                                    <Badge
                                        variant={
                                            task.overdue
                                                ? 'destructive'
                                                : 'secondary'
                                        }
                                        className="shrink-0"
                                    >
                                        {task.overdue ? 'Lewat ' : ''}
                                        {formatDate(task.date)}
                                    </Badge>
                                )}
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </DashboardPanel>
    );
}

function ProjectsPanel({ projects }: { projects: MemberOverview['projects'] }) {
    return (
        <DashboardPanel
            title="Proyek saya"
            description="Proyek QCC yang sedang Anda kerjakan."
        >
            {projects.length === 0 ? (
                <PanelEmpty
                    icon={FolderKanban}
                    title="Belum ada proyek berjalan"
                    description="Proyek muncul setelah Owner QCC menyetujui proposal dan memasukkan Anda ke tim."
                />
            ) : (
                <ul className="grid gap-3">
                    {projects.map((project) => {
                        const step = project.current_step;

                        return (
                            <li
                                key={project.id}
                                className="grid gap-3 rounded-xl border border-border/60 p-4"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <p className="min-w-0 flex-1 text-sm font-semibold break-words">
                                        {project.title}
                                    </p>
                                    <Badge
                                        variant={
                                            project.role === 'Leader'
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        {project.role}
                                    </Badge>
                                </div>
                                {step ? (
                                    <div className="grid gap-2">
                                        <p className="text-sm text-foreground">
                                            Step {step.number} dari{' '}
                                            {project.steps_total} · {step.label}
                                        </p>
                                        <ProgressBar
                                            value={step.progress}
                                            label={`Progres Step ${step.number}`}
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            {step.status_label} ·{' '}
                                            {step.progress}%
                                            {step.target_date &&
                                                ` · Target ${formatDate(step.target_date)}`}
                                        </p>
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        Semua {project.steps_total} step sudah
                                        disetujui.
                                    </p>
                                )}
                                <Button
                                    variant="outline"
                                    size="sm"
                                    render={<Link href={project.href} />}
                                    nativeButton={false}
                                    className="w-fit"
                                >
                                    Buka proyek
                                    <ArrowRight data-icon="inline-end" />
                                </Button>
                            </li>
                        );
                    })}
                </ul>
            )}
        </DashboardPanel>
    );
}

function ProposalsPanel({
    proposals,
}: {
    proposals: MemberOverview['proposals'];
}) {
    return (
        <DashboardPanel
            title="Proposal saya"
            description="Proposal yang Anda ajukan."
        >
            {proposals.length === 0 ? (
                <PanelEmpty
                    icon={FileText}
                    title="Belum ada proposal"
                    description="Proposal yang Anda buat akan tampil di sini."
                />
            ) : (
                <ul className="grid divide-y divide-border/60">
                    {proposals.map((proposal) => (
                        <li
                            key={proposal.id}
                            className="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                        >
                            <div className="grid min-w-0 flex-1 gap-1">
                                <span className="text-sm font-medium break-words">
                                    {proposal.title}
                                </span>
                                <span className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                    {proposal.code}
                                    <Badge
                                        variant={
                                            proposalStatusVariants[
                                                proposal.status
                                            ] ?? 'secondary'
                                        }
                                    >
                                        {proposal.status_label}
                                    </Badge>
                                </span>
                            </div>
                            <Button
                                variant={
                                    proposal.action_label === 'Lihat'
                                        ? 'outline'
                                        : 'default'
                                }
                                size="sm"
                                render={<Link href={proposal.href} />}
                                nativeButton={false}
                            >
                                {proposal.action_label}
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
        </DashboardPanel>
    );
}

function CompetenciesPanel({
    competencies,
}: {
    competencies: MemberOverview['competencies'];
}) {
    const inPool = competencies.count > 0;

    return (
        <DashboardPanel
            title="Kompetensi saya"
            description="Dipakai Owner QCC untuk menyusun tim."
        >
            <div className="grid gap-4">
                <div className="flex items-end gap-2">
                    <span className="text-3xl leading-none font-semibold tracking-tight tabular-nums">
                        {competencies.count}
                    </span>
                    <span className="pb-0.5 text-sm text-muted-foreground">
                        dari {competencies.limit} kompetensi ·{' '}
                        {competencies.verified} terverifikasi
                    </span>
                </div>
                <ProgressBar
                    value={(competencies.count / competencies.limit) * 100}
                    label="Kompetensi terisi"
                />
                <p
                    className={cn(
                        'flex items-center gap-2 text-sm font-medium',
                        inPool ? 'text-success' : 'text-warning',
                    )}
                >
                    {inPool ? (
                        <BadgeCheck className="size-4" aria-hidden="true" />
                    ) : (
                        <GraduationCap className="size-4" aria-hidden="true" />
                    )}
                    {inPool
                        ? 'Anda sudah masuk Talent Pool'
                        : 'Belum masuk Talent Pool'}
                </p>
                <Button
                    variant="outline"
                    size="sm"
                    render={<Link href={competencies.href} />}
                    nativeButton={false}
                    className="w-fit"
                >
                    Kelola kompetensi
                </Button>
            </div>
        </DashboardPanel>
    );
}

export default function MemberDashboard({
    overview,
    program,
}: {
    overview: MemberOverview;
    program: ProgramOverview | null;
}) {
    const { auth } = usePage<{ auth: { user: { name: string } | null } }>()
        .props;
    const firstName = auth.user?.name.split(' ')[0] ?? '';

    return (
        <>
            <Head title="Dashboard" />
            <main className="mx-auto w-full max-w-[1440px] flex-1 px-4 py-6 md:px-6 md:py-8">
                <PageHeader
                    breadcrumbs={[{ title: 'Dashboard', href: dashboard() }]}
                    title="Dashboard"
                    description="Ringkasan kerja QCC Anda."
                />
                <div className="grid gap-5">
                    <JourneyCard
                        firstName={firstName}
                        journey={overview.journey}
                    />
                    <div className="grid gap-5 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]">
                        <TasksPanel
                            tasks={overview.tasks}
                            total={overview.task_count}
                        />
                        <ProjectsPanel projects={overview.projects} />
                    </div>
                    <div className="grid gap-5 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]">
                        <ProposalsPanel proposals={overview.proposals} />
                        <CompetenciesPanel
                            competencies={overview.competencies}
                        />
                    </div>
                </div>
                {program && (
                    <section className="mt-8">
                        <ProgramDashboard overview={program} />
                    </section>
                )}
            </main>
        </>
    );
}
