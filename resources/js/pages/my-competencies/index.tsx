import { Head, router } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import {
    BadgeCheck,
    LockKeyhole,
    PencilLine,
    Plus,
    ShieldCheck,
    Trash2,
} from 'lucide-react';
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
    LevelOption,
} from '@/components/member-competency-dialog';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { destroy, index } from '@/routes/my-competencies';

type CompetencyRow = {
    id: number;
    code: string;
    name: string;
    self_level: number;
    self_level_label: string | null;
    updated_at: string | null;
};

type SortKey = 'code' | 'self_level' | 'updated_at';

type Filters = {
    search: string;
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
    competencyOptions: CompetencyOption[];
    levelOptions: LevelOption[];
    competencyCount: number;
    competencyLimit: number;
    mustJoinTalentPool: boolean;
    editingIsOpen: boolean;
    editingClosesOn: string | null;
};

function formatDate(value: string): string {
    return format(parseISO(value), 'd MMMM yyyy', { locale: indonesianLocale });
}

export default function MyCompetenciesIndex({
    competencies,
    filters,
    competencyOptions,
    levelOptions,
    competencyCount,
    competencyLimit,
    mustJoinTalentPool,
    editingIsOpen,
    editingClosesOn,
}: Props) {
    const [searchValue, setSearchValue] = useState(filters.search);
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [editing, setEditing] = useState<EditableCompetency | null>(null);

    function visit(next: Query, resetPage = true): void {
        const query = {
            search: next.search ?? filters.search,
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

    const columns = useMemo<DataTableColumnDef<CompetencyRow>[]>(() => {
        const baseColumns: DataTableColumnDef<CompetencyRow>[] = [
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
                id: 'self_level',
                header: 'Level',
                cell: ({ row }) => (
                    <span className="whitespace-nowrap">
                        {row.original.self_level} ·{' '}
                        {row.original.self_level_label}
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
        ];

        if (!editingIsOpen) {
            return baseColumns;
        }

        return [
            ...baseColumns,
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
        ];
    }, [editingIsOpen]);

    const sort: DataTableSort = {
        id: filters.sort === 'code' ? 'code' : filters.sort,
        desc: filters.direction === 'desc',
    };
    const hasActiveFilter = filters.search !== '';
    const reachedCompetencyLimit = competencyCount >= competencyLimit;
    const deadlineNote =
        editingIsOpen && editingClosesOn
            ? ` Bisa diubah sampai ${formatDate(editingClosesOn)}.`
            : '';

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
                    description={`Isi kompetensi yang Anda kuasai beserta levelnya. ${competencyCount}/${competencyLimit} terisi.${deadlineNote}`}
                    actions={
                        editingIsOpen ? (
                            <Button
                                type="button"
                                className="h-10 px-4 text-sm"
                                disabled={reachedCompetencyLimit}
                                onClick={() => {
                                    setEditing(null);
                                    setIsDialogOpen(true);
                                }}
                            >
                                <Plus
                                    data-icon="inline-start"
                                    aria-hidden="true"
                                />
                                {reachedCompetencyLimit
                                    ? 'Batas Tercapai'
                                    : 'Tambah Kompetensi'}
                            </Button>
                        ) : null
                    }
                />

                {!editingIsOpen ? (
                    <Alert className="mb-5">
                        <LockKeyhole aria-hidden="true" />
                        <AlertTitle>
                            Pengisian kompetensi sudah ditutup
                        </AlertTitle>
                        <AlertDescription>
                            {editingClosesOn
                                ? `Batas waktunya ${formatDate(editingClosesOn)}.`
                                : 'Batas waktunya sudah lewat.'}{' '}
                            Kompetensi tidak bisa diubah lagi.
                        </AlertDescription>
                    </Alert>
                ) : competencyCount > 0 ? (
                    <Alert className="mb-5 border-success/40 bg-success/10">
                        <BadgeCheck aria-hidden="true" />
                        <AlertTitle>Kompetensi sudah terisi</AlertTitle>
                        <AlertDescription>
                            Anda bisa dipilih masuk tim QCC. Punya ide
                            perbaikan? Buat proposal QCC.
                        </AlertDescription>
                    </Alert>
                ) : mustJoinTalentPool ? (
                    <Alert className="mb-5 border-warning/40 bg-warning/10">
                        <ShieldCheck aria-hidden="true" />
                        <AlertTitle>Tambahkan minimal 1 kompetensi</AlertTitle>
                        <AlertDescription>
                            Setelah itu Anda bisa membuat proposal QCC.
                        </AlertDescription>
                    </Alert>
                ) : null}

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
                        emptyTitle={
                            hasActiveFilter
                                ? 'Kompetensi tidak ditemukan'
                                : 'Belum ada kompetensi'
                        }
                        emptyDescription={
                            hasActiveFilter
                                ? 'Ubah kata kunci pencarian.'
                                : editingIsOpen
                                  ? 'Tambahkan kompetensi yang Anda kuasai beserta levelnya.'
                                  : 'Pengisian kompetensi sudah ditutup.'
                        }
                    />
                </Card>
            </div>

            <MemberCompetencyDialog
                open={isDialogOpen}
                onOpenChange={setIsDialogOpen}
                editing={editing}
                competencyOptions={competencyOptions}
                levelOptions={levelOptions}
            />
        </>
    );
}
