import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarDays,
    FileCheck2,
    FolderKanban,
    ListChecks,
    ListTodo,
    MessageCircle,
} from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { CsvExportButton } from '@/components/csv-export-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { dashboard } from '@/routes';
import { index as qccProjectsIndex } from '@/routes/qcc-projects';
import {
    index as proposalIndex,
    show as proposalShow,
} from '@/routes/proposals';
import { index as projectEvidenceIndex } from '@/routes/projects/evidence';
import { show as projectStepsShow } from '@/routes/projects/steps';
import { index as projectBiweeklyIndex } from '@/routes/projects/biweekly';
import { index as projectMentoringIndex } from '@/routes/projects/mentoring';
import { index as projectRisksIndex } from '@/routes/projects/risks';
import {
    biweeklyReports as exportBiweeklyReports,
    mentoringReports as exportMentoringReports,
    projectRisks as exportProjectRisks,
    projectSteps as exportProjectSteps,
    qccProjects as exportQccProjects,
} from '@/routes/exports';
import type { BadgeVariant } from '@/lib/badge-variants';

type DirectoryView =
    | 'projects'
    | 'steps'
    | 'evidence'
    | 'biweekly'
    | 'mentoring'
    | 'risks';

type ProjectRow = {
    id: number;
    code: string;
    title: string;
    batch: string | null;
    proposer: string | null;
    status: string;
    status_label: string;
    approved_steps_count: number;
    evidence_count: number;
};

type PageLink = { url: string | null; label: string; active: boolean };

type Props = {
    view: DirectoryView;
    projects: {
        data: ProjectRow[];
        current_page: number;
        from: number | null;
        to: number | null;
        total: number;
        last_page: number;
        links: PageLink[];
    };
};

const directoryConfig: Record<
    DirectoryView,
    {
        title: string;
        description: string;
        listTitle: string;
        emptyTitle: string;
        emptyDescription: string;
        actionLabel: string;
        icon: typeof FolderKanban;
    }
> = {
    projects: {
        title: 'Proyek QCC',
        description: 'Lihat proposal dan proyek QCC yang dapat Anda akses.',
        listTitle: 'Daftar proyek',
        emptyTitle: 'Belum ada proyek',
        emptyDescription:
            'Proyek yang dapat Anda akses akan muncul di sini. Mulai dari Proposal QCC; proyek aktif tampil setelah proposal disetujui.',
        actionLabel: 'Lihat detail proposal',
        icon: FolderKanban,
    },
    steps: {
        title: 'Progress 8 Step QCC',
        description: 'Pantau kemajuan delapan langkah pada proyek disetujui.',
        listTitle: 'Proyek dengan 8 Step',
        emptyTitle: 'Belum ada proyek disetujui',
        emptyDescription:
            'Proyek akan muncul setelah proposal disetujui dan Anda mendapat akses ke proyek tersebut.',
        actionLabel: 'Buka 8 Step',
        icon: ListChecks,
    },
    evidence: {
        title: 'Evidence Repository',
        description: 'Buka arsip evidence pada proyek yang dapat Anda akses.',
        listTitle: 'Evidence per proyek',
        emptyTitle: 'Belum ada proyek disetujui',
        emptyDescription:
            'Repository tersedia untuk proyek disetujui yang dapat Anda akses. Pilih proyek untuk melihat atau mengunggah evidence.',
        actionLabel: 'Buka repository',
        icon: FileCheck2,
    },
    biweekly: {
        title: 'Bi-weekly Report',
        description:
            'Pilih proyek untuk melihat dan mengelola laporan berkala.',
        listTitle: 'Proyek untuk laporan bi-weekly',
        emptyTitle: 'Belum ada proyek aktif',
        emptyDescription:
            'Proyek disetujui yang dapat Anda akses akan muncul di sini.',
        actionLabel: 'Buka laporan',
        icon: CalendarDays,
    },
    mentoring: {
        title: 'Mentoring',
        description:
            'Pilih proyek untuk melihat sesi dan tindak lanjut mentoring.',
        listTitle: 'Proyek untuk mentoring',
        emptyTitle: 'Belum ada proyek binaan',
        emptyDescription:
            'Proyek disetujui yang dapat Anda akses akan muncul di sini.',
        actionLabel: 'Buka mentoring',
        icon: MessageCircle,
    },
    risks: {
        title: 'Aksi & Risiko',
        description:
            'Pantau risiko, kendala, dan tindak lanjut pada proyek QCC.',
        listTitle: 'Proyek dengan pemantauan aksi & risiko',
        emptyTitle: 'Belum ada proyek aktif',
        emptyDescription:
            'Proyek disetujui yang dapat Anda akses akan muncul di sini.',
        actionLabel: 'Buka aksi & risiko',
        icon: ListTodo,
    },
};

const statusVariants: Record<string, BadgeVariant> = {
    DRAFT: 'secondary',
    SUBMITTED: 'info',
    UNDER_REVIEW: 'violet',
    REVISION_REQUIRED: 'warning',
    APPROVED: 'success',
    REJECTED: 'destructive',
};

export default function ProjectDirectory({ view, projects }: Props) {
    const config = directoryConfig[view];
    const PageIcon = config.icon;
    const exportHref = {
        projects: exportQccProjects.url(),
        steps: exportProjectSteps.url(),
        biweekly: exportBiweeklyReports.url(),
        mentoring: exportMentoringReports.url(),
        risks: exportProjectRisks.url(),
        evidence: null,
    }[view];

    function projectHref(projectId: number): string {
        if (view === 'steps') {
            return projectStepsShow.url({ project: projectId });
        }

        if (view === 'evidence') {
            return projectEvidenceIndex.url({ project: projectId });
        }

        if (view === 'biweekly') {
            return projectBiweeklyIndex.url({ project: projectId });
        }

        if (view === 'mentoring') {
            return projectMentoringIndex.url({ project: projectId });
        }

        if (view === 'risks') {
            return projectRisksIndex.url({ project: projectId });
        }

        return proposalShow.url(projectId);
    }

    return (
        <>
            <Head title={config.title} />
            <div className="mx-auto w-full max-w-[1440px] px-4 py-6 md:px-6 md:py-8">
                <PageHeader
                    breadcrumbs={[
                        { title: 'Dashboard', href: dashboard() },
                        { title: config.title, href: qccProjectsIndex() },
                    ]}
                    title={config.title}
                    description={config.description}
                    actions={
                        exportHref ? (
                            <CsvExportButton href={exportHref} />
                        ) : undefined
                    }
                />

                <Card className="gap-0 py-0">
                    <CardHeader className="border-b py-4">
                        <CardTitle className="text-base font-semibold">
                            {config.listTitle}
                        </CardTitle>
                        <CardDescription>
                            {projects.total} proyek dapat diakses
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        {projects.data.length === 0 ? (
                            <Empty className="min-h-64 border-0">
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <PageIcon aria-hidden="true" />
                                    </EmptyMedia>
                                    <EmptyTitle>{config.emptyTitle}</EmptyTitle>
                                    <EmptyDescription>
                                        {config.emptyDescription}
                                    </EmptyDescription>
                                </EmptyHeader>
                                <EmptyContent>
                                    <Button
                                        variant="outline"
                                        render={
                                            <Link
                                                href={
                                                    view === 'biweekly' ||
                                                    view === 'mentoring' ||
                                                    view === 'risks'
                                                        ? qccProjectsIndex()
                                                        : proposalIndex()
                                                }
                                            />
                                        }
                                        nativeButton={false}
                                    >
                                        {view === 'biweekly' ||
                                        view === 'mentoring' ||
                                        view === 'risks'
                                            ? 'Buka Proyek QCC'
                                            : 'Buka Proposal QCC'}
                                        <ArrowRight data-icon="inline-end" />
                                    </Button>
                                </EmptyContent>
                            </Empty>
                        ) : (
                            <ul className="divide-y divide-border/60">
                                {projects.data.map((project) => (
                                    <li
                                        key={project.id}
                                        className="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div className="flex min-w-0 items-start gap-3">
                                            <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                <PageIcon aria-hidden="true" />
                                            </span>
                                            <div className="grid min-w-0 gap-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="truncate text-sm font-semibold">
                                                        {project.title}
                                                    </p>
                                                    <Badge
                                                        variant={
                                                            statusVariants[
                                                                project.status
                                                            ] ?? 'secondary'
                                                        }
                                                    >
                                                        {project.status_label}
                                                    </Badge>
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    {project.code}
                                                    {' · '}
                                                    {project.batch ??
                                                        'Tanpa batch'}
                                                    {' · Pengaju: '}
                                                    {project.proposer ?? '—'}
                                                </p>
                                                {view === 'steps' && (
                                                    <p className="text-xs text-muted-foreground">
                                                        {
                                                            project.approved_steps_count
                                                        }{' '}
                                                        dari 8 langkah disetujui
                                                    </p>
                                                )}
                                                {view === 'evidence' && (
                                                    <p className="text-xs text-muted-foreground">
                                                        {project.evidence_count}{' '}
                                                        evidence tersimpan
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="shrink-0"
                                            render={
                                                <Link
                                                    href={projectHref(
                                                        project.id,
                                                    )}
                                                />
                                            }
                                            nativeButton={false}
                                        >
                                            {config.actionLabel}
                                            <ArrowRight data-icon="inline-end" />
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                    {projects.last_page > 1 && (
                        <CardContent className="flex flex-col gap-3 border-t px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-sm text-muted-foreground">
                                Menampilkan {projects.from}–{projects.to} dari{' '}
                                {projects.total} proyek
                            </p>
                            <nav
                                aria-label={`Navigasi halaman ${config.title}`}
                                className="flex flex-wrap gap-2"
                            >
                                {projects.links.map((link, index) => (
                                    <Button
                                        key={`${link.label}-${index}`}
                                        variant={
                                            link.active ? 'default' : 'outline'
                                        }
                                        size="sm"
                                        disabled={!link.url}
                                        render={
                                            link.url ? (
                                                <Link
                                                    href={link.url}
                                                    preserveScroll
                                                />
                                            ) : undefined
                                        }
                                        nativeButton={!link.url}
                                    >
                                        {link.label
                                            .replace(/&laquo;|&raquo;/g, '')
                                            .trim()}
                                    </Button>
                                ))}
                            </nav>
                        </CardContent>
                    )}
                </Card>
            </div>
        </>
    );
}
