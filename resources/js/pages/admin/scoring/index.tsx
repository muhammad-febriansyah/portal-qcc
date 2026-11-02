import { Head, Link, router, useForm } from '@inertiajs/react';
import { Award, Eye, Play } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { DataTable } from '@/components/data-table';
import type {
    DataTableColumnDef,
    DataTableSort,
} from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { CsvExportButton } from '@/components/csv-export-button';
import { Badge } from '@/components/ui/badge';
import { scoringStatusBadgeVariant } from '@/lib/badge-variants';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { FormField } from '@/components/form-field';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import {
    exportMethod as exportScoringCsv,
    index,
    show,
    store,
} from '@/routes/admin/scoring';
import { index as rubricsIndex } from '@/routes/admin/rubrics';

type ScoringRow = {
    id: number;
    projectId: number;
    projectTitle: string;
    rubricName: string;
    scoringType: string;
    version: number | null;
    status: string;
    statusLabel: string;
    score: string | null;
    requestedBy: string;
    createdAt: string | null;
};

type Filters = {
    search: string;
    status: string;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
};

type Query = Partial<Filters> & { page?: number };

type Props = {
    runs: {
        data: ScoringRow[];
        current_page: number;
        per_page: number;
        total: number;
    };
    filters: Filters;
    statusOptions: { value: string; label: string }[];
    canStart: boolean;
    projects: { id: number; title: string }[];
    rubrics: { id: number; label: string; scoringType: string }[];
};

export default function ScoringIndex({
    runs,
    filters,
    statusOptions,
    canStart,
    projects,
    rubrics,
}: Props) {
    const [searchValue, setSearchValue] = useState(filters.search);
    const [isStartDialogOpen, setIsStartDialogOpen] = useState(false);
    const startForm = useForm({ project_id: '', rubric_version_id: '' });

    function visit(nextQuery: Query, resetPage = true): void {
        const query = {
            search: nextQuery.search ?? filters.search,
            status: nextQuery.status ?? filters.status,
            sort: nextQuery.sort ?? filters.sort,
            direction: nextQuery.direction ?? filters.direction,
            per_page: nextQuery.per_page ?? filters.per_page,
            page: resetPage ? 1 : (nextQuery.page ?? runs.current_page),
        };

        router.get(
            index.url({ query }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['runs', 'filters'],
            },
        );
    }

    useEffect(() => setSearchValue(filters.search), [filters.search]);
    useEffect(() => {
        if (searchValue === filters.search) return;
        const timeout = window.setTimeout(
            () => visit({ search: searchValue }),
            350,
        );
        return () => window.clearTimeout(timeout);
        // Keep current filters from the latest Inertia props when debounce fires.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchValue]);

    const columns = useMemo<DataTableColumnDef<ScoringRow>[]>(
        () => [
            {
                id: 'project_title',
                accessorKey: 'projectTitle',
                header: 'Proyek',
                cell: ({ row }) => (
                    <span className="font-medium">
                        {row.original.projectTitle}
                    </span>
                ),
            },
            {
                id: 'rubric',
                header: 'Rubrik',
                cell: ({ row }) => (
                    <span>
                        {row.original.rubricName} · v
                        {row.original.version ?? '—'}
                    </span>
                ),
            },
            {
                id: 'status',
                accessorKey: 'status',
                header: 'Status',
                cell: ({ row }) => (
                    <Badge
                        variant={scoringStatusBadgeVariant(row.original.status)}
                    >
                        {row.original.statusLabel}
                    </Badge>
                ),
            },
            {
                id: 'score',
                header: 'Skor AI',
                cell: ({ row }) =>
                    row.original.score === null
                        ? '—'
                        : `${Number(row.original.score).toFixed(2)} / 100`,
            },
            {
                id: 'requestedBy',
                accessorKey: 'requestedBy',
                header: 'Diminta oleh',
            },
            { id: 'created_at', accessorKey: 'createdAt', header: 'Waktu' },
            {
                id: 'actions',
                header: 'Detail',
                cell: ({ row }) => (
                    <Button
                        render={<Link href={show.url(row.original.id)} />}
                        nativeButton={false}
                        variant="outline"
                        size="sm"
                    >
                        <Eye aria-hidden="true" /> Lihat
                    </Button>
                ),
            },
        ],
        [],
    );

    const sort: DataTableSort = filters.sort
        ? { id: filters.sort, desc: filters.direction === 'desc' }
        : null;

    function startScoring(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        startForm.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                setIsStartDialogOpen(false);
                startForm.reset();
            },
        });
    }

    return (
        <>
            <Head title="AI Scoring" />
            <div className="mx-auto w-full max-w-[1440px] px-4 py-6 md:px-6 md:py-8">
                <PageHeader
                    breadcrumbs={[
                        { title: 'Dashboard', href: dashboard() },
                        { title: 'AI Scoring', href: index() },
                    ]}
                    title="AI Scoring"
                    description="Pantau status penilaian dan telusuri skor per kriteria."
                    actions={
                        <CsvExportButton
                            href={exportScoringCsv.url({ query: filters })}
                        />
                    }
                />
                <Card className="gap-0 py-0">
                    <DataTable
                        embedded
                        columns={columns}
                        data={runs.data}
                        page={runs.current_page}
                        pageSize={runs.per_page}
                        total={runs.total}
                        onPageChange={(page) => visit({ page }, false)}
                        onPageSizeChange={(per_page) => visit({ per_page })}
                        sort={sort}
                        onSortChange={(nextSort) =>
                            visit({
                                sort: nextSort?.id ?? 'created_at',
                                direction: nextSort?.desc ? 'desc' : 'asc',
                            })
                        }
                        sortableColumns={[
                            'project_title',
                            'status',
                            'created_at',
                        ]}
                        search={searchValue}
                        onSearchChange={setSearchValue}
                        searchPlaceholder="Cari nama proyek atau ID..."
                        filters={
                            <Select
                                value={filters.status || 'all'}
                                onValueChange={(status) =>
                                    visit({
                                        status:
                                            !status || status === 'all'
                                                ? ''
                                                : status,
                                    })
                                }
                            >
                                <SelectTrigger
                                    className="w-full sm:w-44"
                                    aria-label="Filter status scoring"
                                >
                                    <SelectValue placeholder="Semua status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Semua status
                                    </SelectItem>
                                    {statusOptions.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        }
                        emptyTitle="Belum ada riwayat scoring"
                        emptyDescription="Riwayat akan tampil setelah scoring dimulai untuk proyek dan rubrik yang tersedia."
                        toolbar={
                            <div className="flex flex-wrap gap-2">
                                {canStart && (
                                    <Button
                                        onClick={() =>
                                            setIsStartDialogOpen(true)
                                        }
                                    >
                                        <Play aria-hidden="true" /> Mulai
                                        scoring
                                    </Button>
                                )}
                                <Button
                                    render={<Link href={rubricsIndex()} />}
                                    nativeButton={false}
                                    variant="outline"
                                >
                                    <Award aria-hidden="true" />
                                    Kelola Rubrik
                                </Button>
                            </div>
                        }
                    />
                </Card>
            </div>
            <Dialog
                open={isStartDialogOpen}
                onOpenChange={setIsStartDialogOpen}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Mulai AI Scoring</DialogTitle>
                    </DialogHeader>
                    {projects.length === 0 || rubrics.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            {projects.length === 0 &&
                                'Belum ada proyek yang dapat Anda nilai. '}
                            {rubrics.length === 0 && 'Belum ada rubrik terbit.'}
                        </p>
                    ) : (
                        <form onSubmit={startScoring} className="space-y-6">
                            <FormField
                                id="project_id"
                                label="Proyek"
                                error={startForm.errors.project_id}
                                required
                            >
                                <Select
                                    value={startForm.data.project_id}
                                    onValueChange={(value) =>
                                        startForm.setData(
                                            'project_id',
                                            value ?? '',
                                        )
                                    }
                                >
                                    <SelectTrigger aria-label="Pilih proyek">
                                        <SelectValue placeholder="Pilih proyek" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {projects.map((project) => (
                                            <SelectItem
                                                key={project.id}
                                                value={String(project.id)}
                                            >
                                                {project.title}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>
                            <FormField
                                id="rubric_version_id"
                                label="Rubrik terbit"
                                error={startForm.errors.rubric_version_id}
                                required
                            >
                                <Select
                                    value={startForm.data.rubric_version_id}
                                    onValueChange={(value) =>
                                        startForm.setData(
                                            'rubric_version_id',
                                            value ?? '',
                                        )
                                    }
                                >
                                    <SelectTrigger aria-label="Pilih rubrik">
                                        <SelectValue placeholder="Pilih rubrik" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {rubrics.map((rubric) => (
                                            <SelectItem
                                                key={rubric.id}
                                                value={String(rubric.id)}
                                            >
                                                {rubric.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setIsStartDialogOpen(false)}
                                >
                                    Batal
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={
                                        startForm.processing ||
                                        !startForm.data.project_id ||
                                        !startForm.data.rubric_version_id
                                    }
                                >
                                    <Play aria-hidden="true" /> Mulai scoring
                                </Button>
                            </DialogFooter>
                        </form>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
