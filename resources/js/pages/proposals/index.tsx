import { Head, router } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import {
    CircleCheck,
    CircleDashed,
    CircleX,
    Eye,
    PencilLine,
    Send,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { DataTable } from '@/components/data-table';
import type {
    DataTableColumnDef,
    DataTableSort,
} from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { BadgeVariant } from '@/lib/badge-variants';
import { dashboard } from '@/routes';
import { index } from '@/routes/proposals';

type ProposalRow = {
    id: number;
    code: string;
    title: string;
    batch: string | null;
    proposer: string | null;
    organization: string | null;
    members_count: number;
    status: string;
    updated_at: string | null;
};

type Option = { value: string; label: string };

type ProposalSort = 'code' | 'title' | 'proposal_status' | 'updated_at';

type ProposalFilters = {
    search: string;
    status: string;
    batch: string;
    sort: ProposalSort;
    direction: 'asc' | 'desc';
    per_page: number;
};

type ProposalQuery = Partial<ProposalFilters> & { page?: number };

type Props = {
    proposals: {
        data: ProposalRow[];
        current_page: number;
        per_page: number;
        total: number;
    };
    filters: ProposalFilters;
    statusOptions: Option[];
    batchOptions: Option[];
};

const statusStyles: Record<
    string,
    { icon: LucideIcon; variant: BadgeVariant }
> = {
    DRAFT: { icon: CircleDashed, variant: 'secondary' },
    SUBMITTED: { icon: Send, variant: 'info' },
    UNDER_REVIEW: { icon: Eye, variant: 'violet' },
    REVISION_REQUIRED: { icon: PencilLine, variant: 'warning' },
    APPROVED: { icon: CircleCheck, variant: 'success' },
    REJECTED: { icon: CircleX, variant: 'destructive' },
};

function StatusBadge({ status, label }: { status: string; label: string }) {
    const style = statusStyles[status] ?? statusStyles.DRAFT;
    const Icon = style.icon;

    return (
        <Badge variant={style.variant} className="gap-1">
            <Icon aria-hidden="true" />
            {label}
        </Badge>
    );
}

function FilterSelect({
    value,
    onChange,
    label,
    allLabel,
    options,
}: {
    value: string;
    onChange: (value: string) => void;
    label: string;
    allLabel: string;
    options: Option[];
}) {
    return (
        <Select
            value={value}
            onValueChange={(next) => onChange(next ?? 'all')}
            items={[{ value: 'all', label: allLabel }, ...options]}
        >
            <SelectTrigger aria-label={label} className="w-full sm:w-48">
                <SelectValue placeholder={allLabel} />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">{allLabel}</SelectItem>
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

export default function ProposalsIndex({
    proposals,
    filters,
    statusOptions,
    batchOptions,
}: Props) {
    const [searchValue, setSearchValue] = useState(filters.search);

    const statusLabels = useMemo(
        () =>
            Object.fromEntries(
                statusOptions.map((option) => [option.value, option.label]),
            ),
        [statusOptions],
    );

    function visit(nextQuery: ProposalQuery, resetPage = true): void {
        const query = {
            search: nextQuery.search ?? filters.search,
            status: nextQuery.status ?? filters.status,
            batch: nextQuery.batch ?? filters.batch,
            sort: nextQuery.sort ?? filters.sort,
            direction: nextQuery.direction ?? filters.direction,
            per_page: nextQuery.per_page ?? filters.per_page,
            page: resetPage ? 1 : (nextQuery.page ?? proposals.current_page),
        };

        router.get(
            index.url({ query }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['proposals', 'filters'],
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

    const columns = useMemo<DataTableColumnDef<ProposalRow>[]>(
        () => [
            {
                id: 'code',
                accessorKey: 'code',
                header: 'Kode',
                cell: ({ row }) => (
                    <span className="font-medium whitespace-nowrap text-foreground">
                        {row.original.code}
                    </span>
                ),
            },
            {
                id: 'title',
                accessorKey: 'title',
                header: 'Judul Proposal',
                cell: ({ row }) => (
                    <span className="line-clamp-2 max-w-md">
                        {row.original.title}
                    </span>
                ),
            },
            {
                id: 'batch',
                accessorKey: 'batch',
                header: 'Batch',
                cell: ({ row }) => row.original.batch ?? '—',
            },
            {
                id: 'proposer',
                accessorKey: 'proposer',
                header: 'Pengaju',
                cell: ({ row }) => (
                    <div className="grid">
                        <span>{row.original.proposer ?? '—'}</span>
                        {row.original.organization && (
                            <span className="text-xs text-muted-foreground">
                                {row.original.organization}
                            </span>
                        )}
                    </div>
                ),
            },
            {
                id: 'members_count',
                accessorKey: 'members_count',
                header: 'Anggota',
                cell: ({ row }) => row.original.members_count,
            },
            {
                id: 'proposal_status',
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
                            ? format(
                                  parseISO(row.original.updated_at),
                                  'd MMM yyyy, HH:mm',
                                  { locale: indonesianLocale },
                              )
                            : '—'}
                    </span>
                ),
            },
        ],
        [statusLabels],
    );

    const sort: DataTableSort = {
        id: filters.sort,
        desc: filters.direction === 'desc',
    };
    const hasActiveFilter =
        filters.search !== '' ||
        filters.status !== 'all' ||
        filters.batch !== 'all';

    return (
        <>
            <Head title="Proposal QCC" />
            <div className="mx-auto w-full max-w-[1440px] px-4 py-6 md:px-6 md:py-8">
                <PageHeader
                    breadcrumbs={[
                        { title: 'Dashboard', href: dashboard() },
                        { title: 'Proposal QCC', href: index() },
                    ]}
                    title="Proposal QCC"
                    description="Buat proposal, lanjutkan draft, dan pantau status review serta revisinya."
                />

                <Card className="gap-0 py-0">
                    <DataTable
                        embedded
                        columns={columns}
                        data={proposals.data}
                        rowId={(row) => String(row.id)}
                        page={proposals.current_page}
                        pageSize={proposals.per_page}
                        total={proposals.total}
                        onPageChange={(page) => visit({ page }, false)}
                        onPageSizeChange={(per_page) => visit({ per_page })}
                        sort={sort}
                        sortableColumns={[
                            'code',
                            'title',
                            'proposal_status',
                            'updated_at',
                        ]}
                        onSortChange={(nextSort) =>
                            visit(
                                nextSort
                                    ? {
                                          sort: nextSort.id as ProposalSort,
                                          direction: nextSort.desc
                                              ? 'desc'
                                              : 'asc',
                                      }
                                    : { sort: 'updated_at', direction: 'desc' },
                            )
                        }
                        search={searchValue}
                        onSearchChange={setSearchValue}
                        searchPlaceholder="Cari kode, judul, atau pengaju..."
                        filters={
                            <>
                                <FilterSelect
                                    value={filters.status}
                                    onChange={(status) => visit({ status })}
                                    label="Filter status proposal"
                                    allLabel="Semua status"
                                    options={statusOptions}
                                />
                                <FilterSelect
                                    value={filters.batch}
                                    onChange={(batch) => visit({ batch })}
                                    label="Filter batch"
                                    allLabel="Semua batch"
                                    options={batchOptions}
                                />
                            </>
                        }
                        emptyTitle={
                            hasActiveFilter
                                ? 'Proposal tidak ditemukan'
                                : 'Belum ada proposal'
                        }
                        emptyDescription={
                            hasActiveFilter
                                ? 'Ubah kata kunci atau filter untuk melihat hasil lain.'
                                : 'Proposal yang diajukan tim akan muncul di sini.'
                        }
                    />
                </Card>
            </div>
        </>
    );
}
