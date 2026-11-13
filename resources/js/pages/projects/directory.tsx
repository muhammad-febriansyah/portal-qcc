import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarDays,
    FileCheck2,
    FolderKanban,
    ListChecks,
    ListTodo,
    MessageCircle,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { CsvExportButton } from '@/components/csv-export-button';
import { DataTable, type DataTableColumnDef } from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { index as projectStepsDirectoryIndex } from '@/routes/project-steps';
import { index as projectBiweeklyDirectoryIndex } from '@/routes/biweekly-directory';
import { index as projectMentoringDirectoryIndex } from '@/routes/mentoring-directory';
import { index as projectEvidenceDirectoryIndex } from '@/routes/evidence-repository';
import { index as projectRisksDirectoryIndex } from '@/routes/risk-directory';
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

type Props = {
    view: DirectoryView;
    availableTabs: DirectoryView[];
    filters: { search: string; per_page: number };
    projects: {
        data: ProjectRow[];
        current_page: number;
        per_page: number;
        total: number;
        last_page: number;
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

const projectTabs: {
    key: DirectoryView;
    label: string;
    href: () => string;
    icon: typeof FolderKanban;
}[] = [
    {
        key: 'projects',
        label: 'Ringkasan',
        href: () => qccProjectsIndex.url(),
        icon: FolderKanban,
    },
    {
        key: 'steps',
        label: '8 Step',
        href: () => projectStepsDirectoryIndex.url(),
        icon: ListChecks,
    },
    {
        key: 'biweekly',
        label: 'Bi-weekly',
        href: () => projectBiweeklyDirectoryIndex.url(),
        icon: CalendarDays,
    },
    {
        key: 'mentoring',
        label: 'Mentoring',
        href: () => projectMentoringDirectoryIndex.url(),
        icon: MessageCircle,
    },
    {
        key: 'risks',
        label: 'Aksi & Risiko',
        href: () => projectRisksDirectoryIndex.url(),
        icon: ListTodo,
    },
    {
        key: 'evidence',
        label: 'Evidence',
        href: () => projectEvidenceDirectoryIndex.url(),
        icon: FileCheck2,
    },
];

export default function ProjectDirectory({
    view,
    availableTabs,
    filters,
    projects,
}: Props) {
    const config = directoryConfig[view];
    const exportHref = {
        projects: exportQccProjects.url(),
        steps: exportProjectSteps.url(),
        biweekly: exportBiweeklyReports.url(),
        mentoring: exportMentoringReports.url(),
        risks: exportProjectRisks.url(),
        evidence: null,
    }[view];

    const projectHref = useCallback(
        (projectId: number): string => {
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
        },
        [view],
    );

    const currentTabHref =
        projectTabs.find((tab) => tab.key === view)?.href() ??
        qccProjectsIndex.url();
    const [search, setSearch] = useState(filters.search);

    function visit(
        next: Partial<{ search: string; per_page: number; page: number }>,
        resetPage = true,
    ): void {
        router.get(
            currentTabHref,
            {
                search: next.search ?? filters.search,
                per_page: next.per_page ?? projects.per_page,
                page: resetPage ? 1 : (next.page ?? projects.current_page),
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['projects', 'filters'],
            },
        );
    }

    useEffect(() => setSearch(filters.search), [filters.search]);
    useEffect(() => {
        if (search === filters.search) {
            return;
        }

        const timeout = window.setTimeout(() => visit({ search }), 350);

        return () => window.clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const columns = useMemo<DataTableColumnDef<ProjectRow>[]>(
        () => [
            {
                accessorKey: 'code',
                header: 'Kode',
                cell: ({ row }) => (
                    <span className="font-medium tabular-nums">
                        {row.original.code}
                    </span>
                ),
            },
            {
                accessorKey: 'title',
                header: 'Proyek QCC',
                cell: ({ row }) => (
                    <span className="line-clamp-2 min-w-48 font-medium">
                        {row.original.title}
                    </span>
                ),
            },
            {
                accessorKey: 'batch',
                header: 'Batch',
                cell: ({ row }) => row.original.batch ?? 'Tanpa batch',
            },
            {
                accessorKey: 'proposer',
                header: 'Pengaju',
                cell: ({ row }) => row.original.proposer ?? '—',
            },
            {
                id: 'ringkasan',
                header:
                    view === 'steps'
                        ? '8 Step disetujui'
                        : view === 'evidence'
                          ? 'Evidence tersimpan'
                          : 'Status',
                cell: ({ row }) => {
                    if (view === 'steps') {
                        return `${row.original.approved_steps_count} dari 8`;
                    }

                    if (view === 'evidence') {
                        return row.original.evidence_count;
                    }

                    return (
                        <Badge
                            variant={
                                statusVariants[row.original.status] ??
                                'secondary'
                            }
                        >
                            {row.original.status_label}
                        </Badge>
                    );
                },
            },
            {
                id: 'actions',
                header: 'Aksi',
                cell: ({ row }) => (
                    <Button
                        variant="outline"
                        size="sm"
                        className="shrink-0"
                        render={<Link href={projectHref(row.original.id)} />}
                        nativeButton={false}
                    >
                        {config.actionLabel}
                        <ArrowRight data-icon="inline-end" />
                    </Button>
                ),
            },
        ],
        [config.actionLabel, projectHref, view],
    );

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

                <Tabs
                    value={view}
                    onValueChange={(value) => {
                        const selectedTab = projectTabs.find(
                            (tab) => tab.key === value,
                        );

                        if (
                            selectedTab &&
                            availableTabs.includes(selectedTab.key)
                        ) {
                            router.visit(selectedTab.href());
                        }
                    }}
                    className="mb-5 w-full"
                >
                    <TabsList
                        aria-label="Bagian proyek QCC"
                        className="w-full max-w-full flex-nowrap justify-start overflow-x-auto"
                    >
                        {projectTabs
                            .filter((tab) => availableTabs.includes(tab.key))
                            .map((tab) => (
                                <TabsTrigger key={tab.key} value={tab.key}>
                                    <tab.icon
                                        aria-hidden="true"
                                        data-icon="inline-start"
                                    />
                                    {tab.label}
                                </TabsTrigger>
                            ))}
                    </TabsList>
                </Tabs>

                <Card className="gap-0 py-0">
                    <CardHeader className="border-b py-4">
                        <CardTitle className="text-base font-semibold">
                            {config.listTitle}
                        </CardTitle>
                        <CardDescription>
                            {projects.total} proyek ditemukan
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        <DataTable
                            embedded
                            columns={columns}
                            data={projects.data}
                            rowId={(project) => String(project.id)}
                            page={projects.current_page}
                            pageSize={projects.per_page}
                            total={projects.total}
                            onPageChange={(page) => visit({ page }, false)}
                            onPageSizeChange={(per_page) => visit({ per_page })}
                            search={search}
                            onSearchChange={setSearch}
                            searchPlaceholder="Cari kode, proyek, batch, atau pengaju..."
                            emptyTitle={
                                search
                                    ? 'Proyek tidak ditemukan'
                                    : config.emptyTitle
                            }
                            emptyDescription={
                                search
                                    ? 'Coba kata kunci lain atau hapus pencarian.'
                                    : config.emptyDescription
                            }
                            emptyAction={
                                !search ? (
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
                                ) : undefined
                            }
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
