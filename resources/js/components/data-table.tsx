import {
    type ColumnDef,
    type RowData,
    tableFeatures,
    useTable,
} from '@tanstack/react-table';
import {
    ArrowDown,
    ArrowUp,
    ChevronsUpDown,
    Inbox,
    Search,
} from 'lucide-react';
import { useMemo } from 'react';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const features = tableFeatures({});

export type DataTableColumnDef<TData extends RowData> = ColumnDef<
    typeof features,
    TData,
    unknown
>;

export type DataTableSort = { id: string; desc: boolean } | null;

type DataTableProps<TData extends RowData> = {
    columns: DataTableColumnDef<TData>[];
    data: TData[];
    page: number;
    pageSize: number;
    total: number;
    onPageChange: (page: number) => void;
    onPageSizeChange?: (pageSize: number) => void;
    sort?: DataTableSort;
    onSortChange?: (sort: DataTableSort) => void;
    search?: string;
    onSearchChange?: (search: string) => void;
    searchPlaceholder?: string;
    toolbar?: React.ReactNode;
    filters?: React.ReactNode;
    isLoading?: boolean;
    emptyTitle?: string;
    emptyDescription?: string;
    pageSizes?: number[];
    rowId?: (row: TData, index: number) => string;
    sortableColumns?: string[];
    /** Render without its own frame so the table can sit inside a Card. */
    embedded?: boolean;
};

export function DataTable<TData extends RowData>({
    columns,
    data,
    page,
    pageSize,
    total,
    onPageChange,
    onPageSizeChange,
    sort = null,
    onSortChange,
    search,
    onSearchChange,
    searchPlaceholder = 'Cari data...',
    toolbar,
    filters,
    isLoading = false,
    emptyTitle = 'Belum ada data',
    emptyDescription = 'Data yang sesuai akan tampil di sini.',
    pageSizes = [10, 25, 50],
    rowId,
    sortableColumns,
    embedded = false,
}: DataTableProps<TData>) {
    const stableData = useMemo(() => data, [data]);
    const displayColumns = useMemo<DataTableColumnDef<TData>[]>(
        () => [
            {
                id: 'row_number',
                header: 'No.',
                enableSorting: false,
                cell: ({ row }) => (
                    <span className="text-muted-foreground tabular-nums">
                        {(page - 1) * pageSize + row.index + 1}
                    </span>
                ),
            },
            ...columns,
        ],
        [columns, page, pageSize],
    );
    const table = useTable({
        features,
        columns: displayColumns,
        data: stableData,
        getRowId: rowId,
    });
    const pageCount = Math.max(1, Math.ceil(total / pageSize));
    const firstItem = total === 0 ? 0 : (page - 1) * pageSize + 1;
    const lastItem = Math.min(page * pageSize, total);

    function toggleSort(columnId: string): void {
        if (!onSortChange) return;
        onSortChange(
            sort?.id !== columnId
                ? { id: columnId, desc: false }
                : sort.desc
                  ? null
                  : { id: columnId, desc: true },
        );
    }

    return (
        <section
            className={embedded ? 'space-y-0' : 'space-y-4'}
            aria-busy={isLoading}
        >
            <div
                className={cn(
                    'flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between',
                    embedded && 'p-4 md:p-5',
                )}
            >
                <div className="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center">
                    {onSearchChange && (
                        <div className="relative w-full sm:max-w-xs">
                            <Search
                                aria-hidden="true"
                                className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                value={search ?? ''}
                                onChange={(event) =>
                                    onSearchChange(event.target.value)
                                }
                                placeholder={searchPlaceholder}
                                aria-label={searchPlaceholder}
                                className="pl-9"
                            />
                        </div>
                    )}
                    {filters}
                </div>
                {toolbar}
            </div>
            <div
                className={
                    embedded
                        ? 'overflow-hidden border-y border-border/70'
                        : 'overflow-hidden rounded-xl border bg-card'
                }
            >
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((group) => (
                            <TableRow
                                key={group.id}
                                className="bg-muted/40 hover:bg-muted/40"
                            >
                                {group.headers.map((header) => (
                                    <TableHead key={header.id} scope="col">
                                        {header.isPlaceholder ? null : onSortChange &&
                                          header.column.id !== 'row_number' &&
                                          (!sortableColumns ||
                                              sortableColumns.includes(
                                                  header.column.id,
                                              )) ? (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                className="-ml-3 h-8 font-medium"
                                                onClick={() =>
                                                    toggleSort(header.column.id)
                                                }
                                                aria-label={`Urutkan ${String(header.column.columnDef.header ?? header.column.id)}`}
                                            >
                                                <table.FlexRender
                                                    header={header}
                                                />
                                                {sort?.id ===
                                                header.column.id ? (
                                                    sort.desc ? (
                                                        <ArrowDown aria-hidden="true" />
                                                    ) : (
                                                        <ArrowUp aria-hidden="true" />
                                                    )
                                                ) : (
                                                    <ChevronsUpDown aria-hidden="true" />
                                                )}
                                            </Button>
                                        ) : (
                                            <table.FlexRender header={header} />
                                        )}
                                    </TableHead>
                                ))}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {isLoading ? (
                            Array.from(
                                { length: Math.min(pageSize, 5) },
                                (_, index) => (
                                    <TableRow
                                        key={`loading-${index}`}
                                        aria-hidden="true"
                                    >
                                        {displayColumns.map(
                                            (column, columnIndex) => (
                                                <TableCell
                                                    key={
                                                        column.id ?? columnIndex
                                                    }
                                                >
                                                    <Skeleton className="h-4 w-3/4" />
                                                </TableCell>
                                            ),
                                        )}
                                    </TableRow>
                                ),
                            )
                        ) : table.getRowModel().rows.length ? (
                            table.getRowModel().rows.map((row) => (
                                <TableRow key={row.id}>
                                    {row.getAllCells().map((cell) => (
                                        <TableCell key={cell.id}>
                                            <table.FlexRender cell={cell} />
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell
                                    colSpan={Math.max(displayColumns.length, 1)}
                                    className="h-48"
                                >
                                    <Empty className="border-0 p-4">
                                        <EmptyHeader>
                                            <EmptyMedia variant="icon">
                                                <Inbox aria-hidden="true" />
                                            </EmptyMedia>
                                            <EmptyTitle>
                                                {emptyTitle}
                                            </EmptyTitle>
                                            <EmptyDescription>
                                                {emptyDescription}
                                            </EmptyDescription>
                                        </EmptyHeader>
                                    </Empty>
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>
            <div
                className={cn(
                    'flex flex-col gap-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between',
                    embedded ? 'p-4 md:p-5' : '',
                )}
            >
                <div className="flex items-center gap-3">
                    <span>
                        Menampilkan {firstItem}–{lastItem} dari {total}
                    </span>
                    {onPageSizeChange && (
                        <select
                            aria-label="Jumlah baris per halaman"
                            className="h-9 rounded-md border bg-background px-2 text-foreground"
                            value={pageSize}
                            onChange={(event) =>
                                onPageSizeChange(Number(event.target.value))
                            }
                        >
                            {pageSizes.map((size) => (
                                <option key={size} value={size}>
                                    {size} / halaman
                                </option>
                            ))}
                        </select>
                    )}
                </div>
                <div className="flex items-center gap-2 self-end sm:self-auto">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={page <= 1 || isLoading}
                        onClick={() => onPageChange(page - 1)}
                    >
                        Sebelumnya
                    </Button>
                    <span aria-live="polite">
                        Halaman {page} dari {pageCount}
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={page >= pageCount || isLoading}
                        onClick={() => onPageChange(page + 1)}
                    >
                        Berikutnya
                    </Button>
                </div>
            </div>
        </section>
    );
}
