import { Head, router } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import {
    CircleCheck,
    CircleDashed,
    Lock,
    PencilLine,
    Plus,
    Trash2,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { BatchFormDialog } from '@/components/batch-form-dialog';
import type {
    BatchFormValues,
    BatchStatusOption,
} from '@/components/batch-form-dialog';
import { DataTable } from '@/components/data-table';
import type {
    DataTableColumnDef,
    DataTableSort,
} from '@/components/data-table';
import { DataTableRowActions } from '@/components/data-table-row-actions';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import type { BadgeVariant } from '@/lib/badge-variants';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import { destroy, index } from '@/routes/admin/batches';

type BatchRow = BatchFormValues & {
    updated_at: string | null;
    is_used: boolean;
};

type BatchSort =
    | 'code'
    | 'name'
    | 'starts_on'
    | 'ends_on'
    | 'status'
    | 'updated_at';

type BatchFilters = {
    search: string;
    status: string;
    sort: BatchSort;
    direction: 'asc' | 'desc';
    per_page: number;
};

type BatchQuery = Partial<BatchFilters> & { page?: number };

type Props = {
    batches: {
        data: BatchRow[];
        current_page: number;
        per_page: number;
        total: number;
    };
    filters: BatchFilters;
    statusOptions: BatchStatusOption[];
};

const statusStyles: Record<
    string,
    { icon: LucideIcon; variant: BadgeVariant }
> = {
    draft: {
        icon: CircleDashed,
        variant: 'warning',
    },
    active: {
        icon: CircleCheck,
        variant: 'success',
    },
    closed: {
        icon: Lock,
        variant: 'secondary',
    },
};

function formatDate(value: string, pattern = 'd MMM yyyy'): string {
    return format(parseISO(value), pattern, { locale: indonesianLocale });
}

function StatusBadge({ status, label }: { status: string; label: string }) {
    const style = statusStyles[status] ?? statusStyles.draft;
    const Icon = style.icon;

    return (
        <Badge variant={style.variant} className="gap-1">
            <Icon aria-hidden="true" />
            {label}
        </Badge>
    );
}

export default function BatchesIndex({
    batches,
    filters,
    statusOptions,
}: Props) {
    const [searchValue, setSearchValue] = useState(filters.search);
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [editingBatch, setEditingBatch] = useState<BatchRow | null>(null);

    const statusLabels = useMemo(
        () =>
            Object.fromEntries(
                statusOptions.map((option) => [option.value, option.label]),
            ),
        [statusOptions],
    );

    function visit(nextQuery: BatchQuery, resetPage = true): void {
        const query = {
            search: nextQuery.search ?? filters.search,
            status: nextQuery.status ?? filters.status,
            sort: nextQuery.sort ?? filters.sort,
            direction: nextQuery.direction ?? filters.direction,
            per_page: nextQuery.per_page ?? filters.per_page,
            page: resetPage ? 1 : (nextQuery.page ?? batches.current_page),
        };

        router.get(
            index.url({ query }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['batches', 'filters'],
            },
        );
    }

    useEffect(() => {
        setSearchValue(filters.search);
    }, [filters.search]);

    useEffect(() => {
        if (searchValue === filters.search) {
            return;
        }

        const timeout = window.setTimeout(() => {
            visit({ search: searchValue });
        }, 350);

        return () => window.clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchValue]);

    function openCreateDialog(): void {
        setEditingBatch(null);
        setIsDialogOpen(true);
    }

    function openEditDialog(batch: BatchRow): void {
        setEditingBatch(batch);
        setIsDialogOpen(true);
    }

    function deleteBatch(batch: BatchRow): void {
        router.delete(destroy.url(batch.id), { preserveScroll: true });
    }

    const columns = useMemo<DataTableColumnDef<BatchRow>[]>(
        () => [
            {
                id: 'code',
                accessorKey: 'code',
                header: 'Kode',
                cell: ({ row }) => (
                    <span className="font-medium text-foreground">
                        {row.original.code}
                    </span>
                ),
            },
            {
                id: 'name',
                accessorKey: 'name',
                header: 'Nama Batch',
                cell: ({ row }) => row.original.name,
            },
            {
                id: 'starts_on',
                accessorKey: 'starts_on',
                header: 'Periode',
                cell: ({ row }) => (
                    <span className="whitespace-nowrap">
                        {formatDate(row.original.starts_on)} –{' '}
                        {formatDate(row.original.ends_on)}
                    </span>
                ),
            },
            {
                id: 'status',
                accessorKey: 'status',
                header: 'Status',
                cell: ({ row }) => (
                    <StatusBadge
                        status={row.original.status}
                        label={
                            statusLabels[row.original.status] ??
                            row.original.status
                        }
                    />
                ),
            },
            {
                id: 'updated_at',
                accessorKey: 'updated_at',
                header: 'Diperbarui',
                cell: ({ row }) => (
                    <span className="whitespace-nowrap text-muted-foreground">
                        {row.original.updated_at
                            ? formatDate(
                                  row.original.updated_at,
                                  'd MMM yyyy, HH:mm',
                              )
                            : '—'}
                    </span>
                ),
            },
            {
                id: 'actions',
                header: () => <span className="sr-only">Aksi</span>,
                cell: ({ row }) => {
                    const batch = row.original;

                    return (
                        <div className="flex justify-end">
                            <DataTableRowActions
                                label={`Aksi untuk ${batch.name}`}
                                actions={[
                                    {
                                        label: 'Ubah',
                                        icon: PencilLine,
                                        onSelect: () => openEditDialog(batch),
                                    },
                                    {
                                        label: 'Hapus',
                                        icon: Trash2,
                                        destructive: true,
                                        hidden: batch.is_used,
                                        confirmTitle: 'Hapus batch?',
                                        confirmDescription: `Batch ${batch.code} akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.`,
                                        confirmLabel: 'Hapus',
                                        onSelect: () => deleteBatch(batch),
                                    },
                                ]}
                            />
                        </div>
                    );
                },
            },
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [statusLabels],
    );

    const sort: DataTableSort = {
        id: filters.sort,
        desc: filters.direction === 'desc',
    };
    const hasActiveFilter = filters.search !== '' || filters.status !== 'all';

    return (
        <>
            <Head title="Batch" />
            <div className="mx-auto w-full max-w-[1440px] px-4 py-6 md:px-6 md:py-8">
                <PageHeader
                    breadcrumbs={[
                        { title: 'Dashboard', href: dashboard() },
                        { title: 'Batch', href: index() },
                    ]}
                    title="Batch"
                    description="Kelola periode batch QCC yang menjadi dasar proposal dan proyek."
                    actions={
                        <Button
                            type="button"
                            className="h-10 px-4 text-sm"
                            onClick={openCreateDialog}
                        >
                            <Plus data-icon="inline-start" aria-hidden="true" />
                            Tambah Batch
                        </Button>
                    }
                />

                <Card className="gap-0 py-0">
                    <DataTable
                        embedded
                        columns={columns}
                        data={batches.data}
                        rowId={(row) => String(row.id)}
                        page={batches.current_page}
                        pageSize={batches.per_page}
                        total={batches.total}
                        onPageChange={(page) => visit({ page }, false)}
                        onPageSizeChange={(per_page) => visit({ per_page })}
                        sort={sort}
                        sortableColumns={[
                            'code',
                            'name',
                            'starts_on',
                            'status',
                            'updated_at',
                        ]}
                        onSortChange={(nextSort) =>
                            visit(
                                nextSort
                                    ? {
                                          sort: nextSort.id as BatchSort,
                                          direction: nextSort.desc
                                              ? 'desc'
                                              : 'asc',
                                      }
                                    : { sort: 'starts_on', direction: 'desc' },
                            )
                        }
                        search={searchValue}
                        onSearchChange={setSearchValue}
                        searchPlaceholder="Cari kode atau nama batch..."
                        filters={
                            <Select
                                value={filters.status}
                                onValueChange={(value) =>
                                    visit({ status: value ?? 'all' })
                                }
                                items={[
                                    { value: 'all', label: 'Semua status' },
                                    ...statusOptions,
                                ]}
                            >
                                <SelectTrigger
                                    aria-label="Filter status batch"
                                    className="w-full sm:w-44"
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
                        emptyTitle={
                            hasActiveFilter
                                ? 'Batch tidak ditemukan'
                                : 'Belum ada batch'
                        }
                        emptyDescription={
                            hasActiveFilter
                                ? 'Ubah kata kunci atau filter status untuk melihat hasil lain.'
                                : 'Tambah batch pertama untuk mulai menerima proposal.'
                        }
                    />
                </Card>
            </div>

            <BatchFormDialog
                open={isDialogOpen}
                onOpenChange={setIsDialogOpen}
                batch={editingBatch}
                statusOptions={statusOptions}
            />
        </>
    );
}
