import { Head, router } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import { PencilLine, Plus, ShieldCheck, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { DataTable } from '@/components/data-table';
import type {
    DataTableColumnDef,
    DataTableSort,
} from '@/components/data-table';
import { DataTableRowActions } from '@/components/data-table-row-actions';
import { MemberCompetencyDialog } from '@/components/member-competency-dialog';
import type {
    CompetencyOption,
    EditableCompetency,
    GroupOption,
    LevelOption,
} from '@/components/member-competency-dialog';
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
import { destroy, index } from '@/routes/my-competencies';

type CompetencyRow = {
    id: number;
    code: string;
    name: string;
    group_code: string | null;
    self_level: number;
    self_level_label: string | null;
    verified_level: number | null;
    verified_level_label: string | null;
    updated_at: string | null;
};

type SortKey = 'code' | 'self_level' | 'updated_at';

type Filters = {
    search: string;
    group: string;
    sort: SortKey;
    direction: 'asc' | 'desc';
    per_page: number;
};

type Query = Partial<Filters> & { page?: number };

type Props = {
    competencies: {
        data: CompetencyRow[];
        current_page: number;
        per_page: number;
        total: number;
    };
    filters: Filters;
    groupOptions: GroupOption[];
    competencyOptions: CompetencyOption[];
    levelOptions: LevelOption[];
};

export default function MyCompetenciesIndex({
    competencies,
    filters,
    groupOptions,
    competencyOptions,
    levelOptions,
}: Props) {
    const [searchValue, setSearchValue] = useState(filters.search);
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [editing, setEditing] = useState<EditableCompetency | null>(null);

    function visit(next: Query, resetPage = true): void {
        const query = {
            search: next.search ?? filters.search,
            group: next.group ?? filters.group,
            sort: next.sort ?? filters.sort,
            direction: next.direction ?? filters.direction,
            per_page: next.per_page ?? filters.per_page,
            page: resetPage ? 1 : (next.page ?? competencies.current_page),
        };

        router.get(
            index.url({ query }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['competencies', 'filters'],
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

    const columns = useMemo<DataTableColumnDef<CompetencyRow>[]>(
        () => [
            {
                id: 'code',
                header: 'Kompetensi',
                cell: ({ row }) => (
                    <div className="grid gap-0.5">
                        <span className="font-medium text-foreground">
                            {row.original.name}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {row.original.code}
                        </span>
                    </div>
                ),
            },
            {
                id: 'group',
                header: 'Kelompok',
                cell: ({ row }) =>
                    row.original.group_code ? (
                        <Badge variant="outline">
                            Kelompok {row.original.group_code}
                        </Badge>
                    ) : (
                        '—'
                    ),
            },
            {
                id: 'self_level',
                header: 'Level Saya',
                cell: ({ row }) => (
                    <span className="whitespace-nowrap">
                        {row.original.self_level} ·{' '}
                        {row.original.self_level_label}
                    </span>
                ),
            },
            {
                id: 'verified',
                header: 'Terverifikasi',
                cell: ({ row }) =>
                    row.original.verified_level !== null ? (
                        <Badge
                            variant="secondary"
                            className="gap-1 bg-[#16834a]/10 text-[#16834a]"
                        >
                            <ShieldCheck aria-hidden="true" />
                            {row.original.verified_level} ·{' '}
                            {row.original.verified_level_label}
                        </Badge>
                    ) : (
                        <span className="text-muted-foreground">
                            Belum terverifikasi
                        </span>
                    ),
            },
            {
                id: 'updated_at',
                header: 'Diperbarui',
                cell: ({ row }) => (
                    <span className="whitespace-nowrap text-muted-foreground">
                        {row.original.updated_at
                            ? format(
                                  parseISO(row.original.updated_at),
                                  'd MMM yyyy',
                                  { locale: indonesianLocale },
                              )
                            : '—'}
                    </span>
                ),
            },
            {
                id: 'actions',
                header: () => <span className="sr-only">Aksi</span>,
                cell: ({ row }) => {
                    const item = row.original;

                    return (
                        <div className="flex justify-end">
                            <DataTableRowActions
                                label={`Aksi untuk ${item.name}`}
                                actions={[
                                    {
                                        label: 'Ubah Level',
                                        icon: PencilLine,
                                        onSelect: () => {
                                            setEditing({
                                                id: item.id,
                                                code: item.code,
                                                name: item.name,
                                                self_level: item.self_level,
                                            });
                                            setIsDialogOpen(true);
                                        },
                                    },
                                    {
                                        label: 'Hapus',
                                        icon: Trash2,
                                        destructive: true,
                                        hidden: item.verified_level !== null,
                                        confirmTitle: 'Hapus kompetensi?',
                                        confirmDescription: `${item.name} akan dihapus dari daftar kompetensi Anda.`,
                                        confirmLabel: 'Hapus',
                                        onSelect: () =>
                                            router.delete(
                                                destroy.url(item.id),
                                                {
                                                    preserveScroll: true,
                                                },
                                            ),
                                    },
                                ]}
                            />
                        </div>
                    );
                },
            },
        ],
        [],
    );

    const sort: DataTableSort = {
        id: filters.sort === 'code' ? 'code' : filters.sort,
        desc: filters.direction === 'desc',
    };
    const hasActiveFilter = filters.search !== '' || filters.group !== 'all';

    return (
        <>
            <Head title="Kompetensi Saya" />
            <div className="mx-auto w-full max-w-[1440px] px-4 py-6 md:px-6 md:py-8">
                <PageHeader
                    breadcrumbs={[
                        { title: 'Dashboard', href: dashboard() },
                        { title: 'Kompetensi Saya', href: index() },
                    ]}
                    title="Kompetensi Saya"
                    description="Catat kemampuan Anda agar pembentukan tim QCC dapat mempertimbangkannya."
                    actions={
                        <Button
                            type="button"
                            className="h-10 px-4 text-sm"
                            onClick={() => {
                                setEditing(null);
                                setIsDialogOpen(true);
                            }}
                        >
                            <Plus data-icon="inline-start" aria-hidden="true" />
                            Tambah Kompetensi
                        </Button>
                    }
                />

                <Card className="gap-0 py-0">
                    <DataTable
                        embedded
                        columns={columns}
                        data={competencies.data}
                        rowId={(row) => String(row.id)}
                        page={competencies.current_page}
                        pageSize={competencies.per_page}
                        total={competencies.total}
                        onPageChange={(page) => visit({ page }, false)}
                        onPageSizeChange={(per_page) => visit({ per_page })}
                        sort={sort}
                        sortableColumns={['code', 'self_level', 'updated_at']}
                        onSortChange={(nextSort) =>
                            visit(
                                nextSort
                                    ? {
                                          sort: nextSort.id as SortKey,
                                          direction: nextSort.desc
                                              ? 'desc'
                                              : 'asc',
                                      }
                                    : { sort: 'code', direction: 'asc' },
                            )
                        }
                        search={searchValue}
                        onSearchChange={setSearchValue}
                        searchPlaceholder="Cari kode atau nama kompetensi..."
                        filters={
                            <Select
                                value={filters.group}
                                onValueChange={(value) =>
                                    visit({ group: value ?? 'all' })
                                }
                                items={[
                                    { value: 'all', label: 'Semua kelompok' },
                                    ...groupOptions.map((group) => ({
                                        value: String(group.id),
                                        label: group.label,
                                    })),
                                ]}
                            >
                                <SelectTrigger
                                    aria-label="Filter kelompok"
                                    className="w-full sm:w-48"
                                >
                                    <SelectValue placeholder="Semua kelompok" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Semua kelompok
                                    </SelectItem>
                                    {groupOptions.map((group) => (
                                        <SelectItem
                                            key={group.id}
                                            value={String(group.id)}
                                        >
                                            {group.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        }
                        emptyTitle={
                            hasActiveFilter
                                ? 'Kompetensi tidak ditemukan'
                                : 'Belum ada kompetensi'
                        }
                        emptyDescription={
                            hasActiveFilter
                                ? 'Ubah kata kunci atau filter kelompok.'
                                : 'Tambahkan kompetensi yang Anda kuasai beserta levelnya.'
                        }
                    />
                </Card>
            </div>

            <MemberCompetencyDialog
                open={isDialogOpen}
                onOpenChange={setIsDialogOpen}
                editing={editing}
                groupOptions={groupOptions}
                competencyOptions={competencyOptions}
                levelOptions={levelOptions}
            />
        </>
    );
}
