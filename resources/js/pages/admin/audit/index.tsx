import { Head, router } from '@inertiajs/react';
import { format, formatDistanceToNow, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import { Eye } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { AuditDetailDialog } from '@/components/audit-detail-dialog';
import type { AuditRow } from '@/components/audit-detail-dialog';
import { DataTable } from '@/components/data-table';
import type {
    DataTableColumnDef,
    DataTableSort,
} from '@/components/data-table';
import { DataTableRowActions } from '@/components/data-table-row-actions';
import { DateRangePicker } from '@/components/date-picker';
import type { DateRangeValue } from '@/components/date-picker';
import { PageHeader } from '@/components/page-header';
import { useAppName } from '@/hooks/use-app-name';
import { Badge } from '@/components/ui/badge';
import { moduleBadgeVariant } from '@/lib/badge-variants';
import { Card } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import { index } from '@/routes/admin/audit';

type AuditFilters = {
    search: string;
    module: string;
    date_from: string | null;
    date_to: string | null;
    direction: 'asc' | 'desc';
    per_page: number;
};

type AuditQuery = Partial<AuditFilters> & { page?: number };

type Props = {
    logs: {
        data: AuditRow[];
        current_page: number;
        per_page: number;
        total: number;
    };
    filters: AuditFilters;
    moduleOptions: { value: string; label: string }[];
};

export default function AuditIndex({ logs, filters, moduleOptions }: Props) {
    const appName = useAppName();
    const [searchValue, setSearchValue] = useState(filters.search);
    const [selectedLog, setSelectedLog] = useState<AuditRow | null>(null);
    const [range, setRange] = useState<DateRangeValue | null>(
        filters.date_from
            ? { from: filters.date_from, to: filters.date_to }
            : null,
    );

    function visit(nextQuery: AuditQuery, resetPage = true): void {
        const query = {
            search: nextQuery.search ?? filters.search,
            module: nextQuery.module ?? filters.module,
            date_from:
                nextQuery.date_from === undefined
                    ? filters.date_from
                    : nextQuery.date_from,
            date_to:
                nextQuery.date_to === undefined
                    ? filters.date_to
                    : nextQuery.date_to,
            direction: nextQuery.direction ?? filters.direction,
            per_page: nextQuery.per_page ?? filters.per_page,
            page: resetPage ? 1 : (nextQuery.page ?? logs.current_page),
        };

        router.get(
            index.url({ query }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['logs', 'filters'],
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

    function changeRange(next: DateRangeValue | null): void {
        setRange(next);

        if (next === null) {
            visit({ date_from: null, date_to: null });
        } else if (next.to) {
            visit({ date_from: next.from, date_to: next.to });
        }
    }

    const columns = useMemo<DataTableColumnDef<AuditRow>[]>(
        () => [
            {
                id: 'occurred_at',
                header: 'Waktu',
                cell: ({ row }) => {
                    const date = parseISO(row.original.occurred_at);

                    return (
                        <div className="grid gap-0.5 whitespace-nowrap">
                            <span className="font-medium text-foreground">
                                {format(date, 'd MMM yyyy, HH:mm', {
                                    locale: indonesianLocale,
                                })}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {formatDistanceToNow(date, {
                                    addSuffix: true,
                                    locale: indonesianLocale,
                                })}
                            </span>
                        </div>
                    );
                },
            },
            {
                id: 'actor',
                header: 'Pelaku',
                cell: ({ row }) => (
                    <div className="grid gap-0.5">
                        <span className="font-medium text-foreground">
                            {row.original.actor_name ?? 'Sistem'}
                        </span>
                        {row.original.actor_email && (
                            <span className="text-xs text-muted-foreground">
                                {row.original.actor_email}
                            </span>
                        )}
                    </div>
                ),
            },
            {
                id: 'description',
                header: 'Aktivitas',
                cell: ({ row }) => (
                    <span className="line-clamp-2 min-w-64">
                        {row.original.description}
                    </span>
                ),
            },
            {
                id: 'module',
                header: 'Modul',
                cell: ({ row }) => (
                    <Badge
                        variant={moduleBadgeVariant(row.original.module_label)}
                    >
                        {row.original.module_label}
                    </Badge>
                ),
            },
            {
                id: 'actions',
                header: () => <span className="sr-only">Aksi</span>,
                cell: ({ row }) => (
                    <div className="flex justify-end">
                        <DataTableRowActions
                            label="Aksi untuk aktivitas ini"
                            actions={[
                                {
                                    label: 'Lihat Detail',
                                    icon: Eye,
                                    onSelect: () =>
                                        setSelectedLog(row.original),
                                },
                            ]}
                        />
                    </div>
                ),
            },
        ],
        [],
    );

    const sort: DataTableSort = {
        id: 'occurred_at',
        desc: filters.direction === 'desc',
    };
    const hasActiveFilter =
        filters.search !== '' ||
        filters.module !== 'all' ||
        filters.date_from !== null;

    return (
        <>
            <Head title="Audit Log" />
            <div className="mx-auto w-full max-w-[1440px] px-4 py-6 md:px-6 md:py-8">
                <PageHeader
                    breadcrumbs={[
                        { title: 'Dashboard', href: dashboard() },
                        { title: 'Audit Log', href: index() },
                    ]}
                    title="Audit Log"
                    description={`Riwayat aktivitas pengguna di ${appName}: siapa melakukan apa dan kapan.`}
                />

                <Card className="gap-0 py-0">
                    <DataTable
                        embedded
                        columns={columns}
                        data={logs.data}
                        rowId={(row) => String(row.id)}
                        page={logs.current_page}
                        pageSize={logs.per_page}
                        total={logs.total}
                        onPageChange={(page) => visit({ page }, false)}
                        onPageSizeChange={(per_page) => visit({ per_page })}
                        sort={sort}
                        sortableColumns={['occurred_at']}
                        onSortChange={(nextSort) =>
                            visit({
                                direction:
                                    nextSort?.desc === false ? 'asc' : 'desc',
                            })
                        }
                        search={searchValue}
                        onSearchChange={setSearchValue}
                        searchPlaceholder="Cari pelaku, aktivitas, atau data..."
                        filters={
                            <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                                <Select
                                    value={filters.module}
                                    onValueChange={(value) =>
                                        visit({ module: value ?? 'all' })
                                    }
                                    items={[
                                        { value: 'all', label: 'Semua modul' },
                                        ...moduleOptions,
                                    ]}
                                >
                                    <SelectTrigger
                                        aria-label="Filter modul"
                                        className="w-full sm:w-44"
                                    >
                                        <SelectValue placeholder="Semua modul" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Semua modul
                                        </SelectItem>
                                        {moduleOptions.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <DateRangePicker
                                    value={range}
                                    onValueChange={changeRange}
                                    placeholder="Semua tanggal"
                                    className="h-8 w-full sm:w-64"
                                />
                            </div>
                        }
                        emptyTitle={
                            hasActiveFilter
                                ? 'Aktivitas tidak ditemukan'
                                : 'Belum ada aktivitas'
                        }
                        emptyDescription={
                            hasActiveFilter
                                ? 'Ubah kata kunci, modul, atau rentang tanggal.'
                                : 'Aktivitas pengguna akan tercatat di sini.'
                        }
                    />
                </Card>
            </div>

            <AuditDetailDialog
                log={selectedLog}
                onClose={() => setSelectedLog(null)}
            />
        </>
    );
}
