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
import { useState } from 'react';
import {
    DashboardGreeting,
    GreetingChip,
} from '@/components/dashboard/dashboard-greeting';
import {
    PanelEmpty,
    ProgressBar,
    surfaceClassName,
} from '@/components/dashboard/dashboard-kit';
import { ProgramDashboard } from '@/components/program/program-dashboard';
import { Panel } from '@/components/program/program-kit';
import { DataTable } from '@/components/data-table';
import type { DataTableColumnDef } from '@/components/data-table';
import type { ProgramOverview } from '@/components/program/types';
import type { BadgeVariant } from '@/lib/badge-variants';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';

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
function JourneyCard({ journey }: { journey: MemberOverview['journey'] }) {
    const action = journey.next_action;

    return (
        <Card className={cn('gap-0 py-0', surfaceClassName)}>
            <div className="grid gap-5 p-5 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center lg:gap-8">
                <div className="grid gap-5">
                    <div className="grid gap-0.5">
                        <h2 className="text-sm font-semibold text-foreground">
                            Posisi Anda di alur QCC
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            Dari mengisi kompetensi sampai hasil seleksi
                            proposal.
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
                                        'relative flex size-8 shrink-0 items-center justify-center rounded-full border text-sm font-semibold',
                                        step.state === 'done' &&
                                            'border-primary bg-primary/10 text-primary',
                                        step.state === 'current' &&
                                            'border-primary bg-primary text-primary-foreground',
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
                    <div className="grid gap-3 rounded-xl bg-primary p-4 text-primary-foreground lg:w-80">
                        <div className="grid gap-1">
                            <p className="text-xs font-semibold tracking-wide text-primary-foreground/80 uppercase">
                                Langkah berikutnya
                            </p>
                            <p className="text-sm">{action.description}</p>
                        </div>
                        <Button
                            render={<Link href={action.href} />}
                            nativeButton={false}
                            variant="secondary"
                            className="h-10 w-full bg-white text-primary hover:bg-white/90 sm:w-fit lg:w-full"
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

type Task = MemberOverview['tasks'][number];

const taskColumns: DataTableColumnDef<Task>[] = [
    {
        id: 'title',
        header: 'Tugas',
        cell: ({ row }) => {
            const task = row.original;

            return (
                <Link
                    href={task.href}
                    className="group flex min-w-0 items-start gap-2.5 rounded focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <span
                        aria-hidden="true"
                        className={cn(
                            'mt-1.5 size-2 shrink-0 rounded-full',
                            task.overdue ? 'bg-destructive' : 'bg-primary',
                        )}
                    />
                    <span className="grid min-w-0 gap-0.5 whitespace-normal">
                        <span className="text-sm font-medium break-words text-foreground group-hover:text-primary">
                            {task.title}
                        </span>
                        <span className="line-clamp-1 text-xs text-muted-foreground">
                            {task.project} · {task.detail}
                        </span>
                    </span>
                </Link>
            );
        },
    },
    {
        id: 'date',
        header: 'Tenggat',
        cell: ({ row }) => {
            const task = row.original;

            return task.date ? (
                <Badge variant={task.overdue ? 'destructive' : 'secondary'}>
                    {task.overdue ? 'Lewat ' : ''}
                    {formatDate(task.date)}
                </Badge>
            ) : (
                <span className="text-xs text-muted-foreground">—</span>
            );
        },
    },
];

/** Every open task as a small table, most urgent first, searchable and paged. */
function TasksPanel({ tasks }: { tasks: MemberOverview['tasks'] }) {
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [pageSize, setPageSize] = useState(5);
    const term = search.trim().toLowerCase();
    const filtered = term
        ? tasks.filter((task) =>
              [task.title, task.project, task.detail]
                  .join(' ')
                  .toLowerCase()
                  .includes(term),
          )
        : tasks;
    const pageCount = Math.max(1, Math.ceil(filtered.length / pageSize));
    const currentPage = Math.min(page, pageCount);
    const rows = filtered.slice(
        (currentPage - 1) * pageSize,
        currentPage * pageSize,
    );
    const overdue = tasks.filter((task) => task.overdue).length;

    return (
        <Card className={cn('min-w-0 gap-0 py-0', surfaceClassName)}>
            <div className="grid gap-0.5 px-5 pt-4">
                <h3 className="text-sm font-semibold text-foreground">
                    Yang perlu dikerjakan
                </h3>
                <p className="text-xs text-muted-foreground">
                    {tasks.length > 0
                        ? `${tasks.length} tugas${overdue > 0 ? ` · ${overdue} lewat tenggat` : ''}`
                        : 'Tugas yang menunggu Anda.'}
                </p>
            </div>
            {tasks.length === 0 ? (
                <div className="px-5 pt-3 pb-5">
                    <PanelEmpty
                        icon={ClipboardList}
                        title="Tidak ada tugas"
                        description="Revisi, action item, dan tugas leader akan muncul di sini."
                    />
                </div>
            ) : (
                <DataTable
                    embedded
                    columns={taskColumns}
                    data={rows}
                    rowId={(row) => row.key}
                    page={currentPage}
                    pageSize={pageSize}
                    total={filtered.length}
                    pageSizes={[5, 10, 25]}
                    onPageChange={setPage}
                    onPageSizeChange={(size) => {
                        setPageSize(size);
                        setPage(1);
                    }}
                    search={search}
                    onSearchChange={(value) => {
                        setSearch(value);
                        setPage(1);
                    }}
                    searchPlaceholder="Cari tugas atau proyek..."
                    emptyTitle="Tidak ada tugas yang cocok"
                    emptyDescription="Ubah kata pencarian untuk melihat tugas lain."
                />
            )}
        </Card>
    );
}

function ProjectsPanel({ projects }: { projects: MemberOverview['projects'] }) {
    return (
        <Panel
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
                                className="grid gap-3 border-b border-border/60 pb-4 last:border-b-0 last:pb-0"
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
        </Panel>
    );
}

function ProposalsPanel({
    proposals,
}: {
    proposals: MemberOverview['proposals'];
}) {
    return (
        <Panel title="Proposal saya" description="Proposal yang Anda ajukan.">
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
        </Panel>
    );
}

function CompetenciesPanel({
    competencies,
}: {
    competencies: MemberOverview['competencies'];
}) {
    const inPool = competencies.count > 0;

    return (
        <Panel
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
        </Panel>
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
                <DashboardGreeting
                    name={firstName}
                    description="Ringkasan kerja QCC Anda hari ini."
                    aside={
                        <>
                            <GreetingChip
                                label="Tugas"
                                value={String(overview.task_count)}
                            />
                            <GreetingChip
                                label="Kompetensi"
                                value={`${overview.competencies.count}/${overview.competencies.limit}`}
                            />
                        </>
                    }
                />
                <div className="grid gap-4">
                    <JourneyCard journey={overview.journey} />
                    <div className="grid items-start gap-4 lg:grid-cols-[minmax(0,7fr)_minmax(0,5fr)]">
                        <div className="grid gap-4">
                            <TasksPanel tasks={overview.tasks} />
                            <ProposalsPanel proposals={overview.proposals} />
                        </div>
                        <div className="grid gap-4">
                            <ProjectsPanel projects={overview.projects} />
                            <CompetenciesPanel
                                competencies={overview.competencies}
                            />
                        </div>
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
