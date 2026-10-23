import { Head, Link, router } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import { Eye } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { DataTableColumnDef } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
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
import { index } from '@/routes/admin/proposals';

type ProposalRow = {
    id: number;
    code: string;
    title: string;
    batch: string | null;
    proposer: string | null;
    organization: string | null;
    status: string;
    status_label: string;
    submitted_at: string | null;
    url: string;
};

type Props = {
    proposals: {
        data: ProposalRow[];
        current_page: number;
        per_page: number;
        total: number;
    };
    filters: { search: string; status: string; per_page: number };
    statusOptions: { value: string; label: string }[];
};

const statusVariants: Record<
    string,
    'info' | 'violet' | 'warning' | 'success' | 'destructive' | 'secondary'
> = {
    DRAFT: 'secondary',
    SUBMITTED: 'info',
    UNDER_REVIEW: 'violet',
    REVISION_REQUIRED: 'warning',
    APPROVED: 'success',
    REJECTED: 'destructive',
};

export default function ProposalReviewInbox({
    proposals,
    filters,
    statusOptions,
}: Props) {
    const [search, setSearch] = useState(filters.search);

    function visit(
        next: Partial<Props['filters'] & { page: number }>,
        resetPage = true,
    ): void {
        const query = {
            search: next.search ?? filters.search,
            status: next.status ?? filters.status,
            per_page: next.per_page ?? filters.per_page,
            page: resetPage ? 1 : (next.page ?? proposals.current_page),
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

    useEffect(() => setSearch(filters.search), [filters.search]);
    useEffect(() => {
        if (search === filters.search) return;
        const timeout = window.setTimeout(() => visit({ search }), 350);
        return () => window.clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const columns = useMemo<DataTableColumnDef<ProposalRow>[]>(
        () => [
            {
                id: 'proposal',
                header: 'Proposal',
                cell: ({ row }) => (
                    <div className="grid gap-0.5">
                        <span className="font-medium">
                            {row.original.title}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {row.original.code} ·{' '}
                            {row.original.batch ?? 'Tanpa batch'}
                        </span>
                    </div>
                ),
            },
            {
                id: 'proposer',
                header: 'Pengaju',
                cell: ({ row }) => (
                    <div className="grid gap-0.5">
                        <span>{row.original.proposer ?? '—'}</span>
                        <span className="text-xs text-muted-foreground">
                            {row.original.organization ??
                                'Organisasi tidak diatur'}
                        </span>
                    </div>
                ),
            },
            {
                id: 'submitted_at',
                header: 'Tanggal diajukan',
                cell: ({ row }) =>
                    row.original.submitted_at
                        ? format(
                              parseISO(row.original.submitted_at),
                              'd MMM yyyy, HH:mm',
                              { locale: indonesianLocale },
                          )
                        : '—',
            },
            {
                id: 'status',
                header: 'Status',
                cell: ({ row }) => (
                    <Badge
                        variant={
                            statusVariants[row.original.status] ?? 'secondary'
                        }
                    >
                        {row.original.status_label}
                    </Badge>
                ),
            },
            {
                id: 'actions',
                header: 'Aksi',
                cell: ({ row }) => (
                    <Button
                        size="sm"
                        variant="outline"
                        render={<Link href={row.original.url} />}
                        nativeButton={false}
                    >
                        <Eye data-icon="inline-start" /> Buka review
                    </Button>
                ),
            },
        ],
        [],
    );

    return (
        <>
            <Head title="Inbox Review Proposal" />
            <div className="mx-auto w-full max-w-[1440px] px-4 py-6 md:px-6 md:py-8">
                <PageHeader
                    breadcrumbs={[
                        { title: 'Dashboard', href: dashboard() },
                        { title: 'Inbox Review', href: index() },
                    ]}
                    title="Inbox Review Proposal"
                    description="Periksa proposal yang masuk, mulai review, lalu berikan keputusan atau minta revisi."
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
                        search={search}
                        onSearchChange={setSearch}
                        searchPlaceholder="Cari kode, proposal, atau pengaju..."
                        filters={
                            <Select
                                value={filters.status}
                                onValueChange={(status) =>
                                    visit({ status: status ?? 'pending' })
                                }
                            >
                                <SelectTrigger
                                    aria-label="Filter status"
                                    className="w-full sm:w-48"
                                >
                                    <SelectValue placeholder="Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="pending">
                                        Menunggu review
                                    </SelectItem>
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
                            filters.status === 'pending'
                                ? 'Inbox review kosong'
                                : 'Proposal tidak ditemukan'
                        }
                        emptyDescription={
                            filters.status === 'pending'
                                ? 'Proposal yang menunggu review akan muncul di sini.'
                                : 'Ubah kata kunci atau status filter.'
                        }
                    />
                </Card>
            </div>
        </>
    );
}
