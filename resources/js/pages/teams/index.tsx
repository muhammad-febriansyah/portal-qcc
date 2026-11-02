import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, FileText } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { DataTableColumnDef } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button, buttonVariants } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import {
    index as proposalIndex,
    candidates as proposalCandidates,
} from '@/routes/proposals';
import { index as teamFormationIndex } from '@/routes/team-formation';

type ProposalRow = {
    id: number;
    code: string;
    title: string;
    batch: string | null;
    proposer: string | null;
    organization: string | null;
    members_count: number;
    requirements_count: number;
    updated_at: string | null;
};

type Props = {
    proposals: {
        data: ProposalRow[];
        current_page: number;
        per_page: number;
        total: number;
    };
    filters: { search: string };
    can_create_proposal: boolean;
};

function formatUpdatedAt(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

export default function TeamFormationIndex({
    proposals,
    filters,
    can_create_proposal,
}: Props) {
    const [search, setSearch] = useState(filters.search);

    function visit(
        query: { search?: string; page?: number },
        resetPage = true,
    ): void {
        const nextQuery = {
            search: query.search ?? filters.search,
            page: resetPage ? 1 : (query.page ?? proposals.current_page),
        };

        router.get(
            teamFormationIndex.url({ query: nextQuery }),
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
        setSearch(filters.search);
    }, [filters.search]);

    useEffect(() => {
        if (search === filters.search) {
            return;
        }

        const timeout = window.setTimeout(() => visit({ search }), 350);

        return () => window.clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

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
                    <div className="grid gap-1">
                        <span className="line-clamp-2 max-w-md font-medium">
                            {row.original.title}
                        </span>
                        <Badge variant="secondary" className="w-fit">
                            Draf
                        </Badge>
                    </div>
                ),
            },
            {
                id: 'batch',
                accessorKey: 'batch',
                header: 'Batch',
                cell: ({ row }) => row.original.batch ?? 'Tanpa batch',
            },
            {
                id: 'proposer',
                accessorKey: 'proposer',
                header: 'Pengaju',
                cell: ({ row }) => (
                    <div className="grid gap-0.5">
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
                cell: ({ row }) => `${row.original.members_count} dipilih`,
            },
            {
                id: 'requirements_count',
                accessorKey: 'requirements_count',
                header: 'Kompetensi',
                cell: ({ row }) =>
                    `${row.original.requirements_count} dibutuhkan`,
            },
            {
                id: 'updated_at',
                accessorKey: 'updated_at',
                header: 'Diperbarui',
                cell: ({ row }) => (
                    <span className="whitespace-nowrap text-muted-foreground">
                        {formatUpdatedAt(row.original.updated_at)}
                    </span>
                ),
            },
            {
                id: 'actions',
                header: 'Aksi',
                cell: ({ row }) => (
                    <Link
                        href={proposalCandidates.url(row.original.id)}
                        className={cn(
                            buttonVariants({ size: 'sm' }),
                            'whitespace-nowrap text-white hover:bg-warning/90',
                        )}
                        style={{ backgroundColor: 'var(--warning)' }}
                    >
                        Atur susunan tim
                        <ArrowRight data-icon="inline-end" aria-hidden="true" />
                    </Link>
                ),
            },
        ],
        [],
    );

    return (
        <>
            <Head title="Pembentukan Tim" />
            <div className="mx-auto w-full max-w-[1440px] px-4 py-6 md:px-6 md:py-8">
                <PageHeader
                    breadcrumbs={[
                        { title: 'Dashboard', href: dashboard() },
                        {
                            title: 'Pembentukan Tim',
                            href: teamFormationIndex(),
                        },
                    ]}
                    title="Pembentukan Tim"
                    description="Pilih proposal draf untuk mencocokkan anggota dan meninjau susunan tim."
                    actions={
                        can_create_proposal ? (
                            <Button
                                variant="outline"
                                render={<Link href={proposalIndex()} />}
                                nativeButton={false}
                            >
                                <FileText data-icon="inline-start" />
                                Proposal QCC
                            </Button>
                        ) : undefined
                    }
                />

                <Card className="gap-0 py-0">
                    <CardHeader className="border-b py-4">
                        <CardTitle className="text-base font-semibold">
                            Proposal siap dibentuk tim
                        </CardTitle>
                        <CardDescription>
                            {proposals.total} proposal draf dapat ditangani
                        </CardDescription>
                    </CardHeader>
                    <DataTable
                        embedded
                        columns={columns}
                        data={proposals.data}
                        rowId={(row) => String(row.id)}
                        page={proposals.current_page}
                        pageSize={proposals.per_page}
                        total={proposals.total}
                        onPageChange={(page) => visit({ page }, false)}
                        search={search}
                        onSearchChange={setSearch}
                        searchPlaceholder="Cari kode, judul, atau pengaju..."
                        emptyTitle={
                            filters.search
                                ? 'Proposal tidak ditemukan'
                                : 'Belum ada proposal draf'
                        }
                        emptyDescription={
                            filters.search
                                ? 'Coba kata kunci lain atau hapus pencarian.'
                                : 'Proposal draf yang dapat Anda tangani akan muncul di sini.'
                        }
                    />
                </Card>
            </div>
        </>
    );
}
