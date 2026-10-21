import { Head, router } from '@inertiajs/react';
import { ShieldCheck, UserRound } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { CompetencyProfileDialog } from '@/components/competency-profile-dialog';
import type { MemberProfile } from '@/components/competency-profile-dialog';
import { DataTable } from '@/components/data-table';
import type {
    DataTableColumnDef,
    DataTableSort,
} from '@/components/data-table';
import { DataTableRowActions } from '@/components/data-table-row-actions';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Card } from '@/components/ui/card';
import {
    Combobox,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
} from '@/components/ui/combobox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index } from '@/routes/admin/competency-directory';

type DirectoryRow = {
    id: number;
    user_id: number;
    name: string;
    sim_id: string | null;
    email: string;
    organization: string | null;
    role: string | null;
    competency_code?: string;
    competency_name?: string;
    group_code?: string | null;
    self_level?: number;
    self_level_label?: string | null;
    verified_level?: number | null;
    verified_level_label?: string | null;
    competencies_count?: number;
    verified_count?: number;
};

type View = 'competency' | 'member';

type Filters = {
    view: View;
    search: string;
    organization: string;
    group: string;
    competency: string;
    min_level: number;
    verified_only: boolean;
    filled: string;
    sort: string;
    direction: 'asc' | 'desc';
    per_page: number;
};

type Query = Partial<Filters> & { page?: number; member?: number | null };

type Option = { id: number; label: string };
type CompetencyOption = Option & { group_id: number };

type Props = {
    rows: {
        data: DirectoryRow[];
        current_page: number;
        per_page: number;
        total: number;
    };
    filters: Filters;
    selectedMember: MemberProfile | null;
    organizationOptions: Option[];
    groupOptions: Option[];
    competencyOptions: CompetencyOption[];
    levelOptions: { value: number; label: string }[];
};

const tabs: { value: View; label: string }[] = [
    { value: 'competency', label: 'Per Kompetensi' },
    { value: 'member', label: 'Per Anggota' },
];

function FilterSelect({
    label,
    value,
    onChange,
    allLabel,
    options,
    className,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    allLabel: string;
    options: { value: string; label: string }[];
    className?: string;
}) {
    return (
        <Select
            value={value}
            onValueChange={(next) => onChange(next ?? 'all')}
            items={[{ value: 'all', label: allLabel }, ...options]}
        >
            <SelectTrigger
                aria-label={label}
                className={cn('w-full sm:w-44', className)}
            >
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

export default function CompetencyDirectoryIndex({
    rows,
    filters,
    selectedMember,
    organizationOptions,
    groupOptions,
    competencyOptions,
    levelOptions,
}: Props) {
    const [searchValue, setSearchValue] = useState(filters.search);

    function visit(next: Query, resetPage = true): void {
        const query = {
            view: next.view ?? filters.view,
            search: next.search ?? filters.search,
            organization: next.organization ?? filters.organization,
            group: next.group ?? filters.group,
            competency: next.competency ?? filters.competency,
            min_level: next.min_level ?? filters.min_level,
            verified_only:
                (next.verified_only ?? filters.verified_only) ? 1 : 0,
            filled: next.filled ?? filters.filled,
            sort: next.sort ?? filters.sort,
            direction: next.direction ?? filters.direction,
            per_page: next.per_page ?? filters.per_page,
            member:
                next.member === undefined
                    ? undefined
                    : (next.member ?? undefined),
            page: resetPage ? 1 : (next.page ?? rows.current_page),
        };

        router.get(
            index.url({ query }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }

    function switchView(view: View): void {
        router.get(
            index.url({ query: { view } }),
            {},
            { preserveScroll: true },
        );
    }

    useEffect(() => {
        setSearchValue(filters.search);
    }, [filters.search]);

    useEffect(() => {
        if (searchValue === filters.search) {
            return;
        }

        const timeout = window.setTimeout(
            () => visit({ search: searchValue }),
            350,
        );

        return () => window.clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchValue]);

    const selectedCompetency =
        competencyOptions.find(
            (option) => String(option.id) === filters.competency,
        ) ?? null;

    const levelFilterOptions = [
        { value: '0', label: 'Semua level' },
        ...levelOptions.map((level) => ({
            value: String(level.value),
            label: `Level ${level.value}+ (${level.label})`,
        })),
    ];

    const personCell = (row: DirectoryRow) => (
        <div className="grid gap-0.5">
            <span className="font-medium text-foreground">{row.name}</span>
            <span className="text-xs text-muted-foreground">
                {[row.sim_id, row.email].filter(Boolean).join(' · ')}
            </span>
        </div>
    );

    const viewProfileAction = (row: DirectoryRow) => ({
        label: 'Lihat Kompetensi',
        icon: UserRound,
        onSelect: () => visit({ member: row.user_id }, false),
    });

    const competencyColumns = useMemo<DataTableColumnDef<DirectoryRow>[]>(
        () => [
            {
                id: 'name',
                header: 'Anggota',
                cell: ({ row }) => personCell(row.original),
            },
            {
                id: 'organization',
                header: 'LOB / Divisi',
                cell: ({ row }) => row.original.organization ?? '—',
            },
            {
                id: 'role',
                header: 'Role',
                cell: ({ row }) =>
                    row.original.role ? (
                        <Badge variant="outline">{row.original.role}</Badge>
                    ) : (
                        '—'
                    ),
            },
            {
                id: 'competency',
                header: 'Kompetensi',
                cell: ({ row }) => (
                    <div className="grid gap-0.5">
                        <span className="font-medium">
                            {row.original.competency_name}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {row.original.competency_code}
                            {row.original.group_code
                                ? ` · Kelompok ${row.original.group_code}`
                                : ''}
                        </span>
                    </div>
                ),
            },
            {
                id: 'self_level',
                header: 'Level Sendiri',
                cell: ({ row }) => (
                    <span className="whitespace-nowrap">
                        {row.original.self_level} ·{' '}
                        {row.original.self_level_label}
                    </span>
                ),
            },
            {
                id: 'level',
                header: 'Terverifikasi',
                cell: ({ row }) =>
                    row.original.verified_level != null ? (
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
                id: 'actions',
                header: () => <span className="sr-only">Aksi</span>,
                cell: ({ row }) => (
                    <div className="flex justify-end">
                        <DataTableRowActions
                            label={`Aksi untuk ${row.original.name}`}
                            actions={[viewProfileAction(row.original)]}
                        />
                    </div>
                ),
            },
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [filters],
    );

    const memberColumns = useMemo<DataTableColumnDef<DirectoryRow>[]>(
        () => [
            {
                id: 'name',
                header: 'Anggota',
                cell: ({ row }) => personCell(row.original),
            },
            {
                id: 'organization',
                header: 'LOB / Divisi',
                cell: ({ row }) => row.original.organization ?? '—',
            },
            {
                id: 'role',
                header: 'Role',
                cell: ({ row }) =>
                    row.original.role ? (
                        <Badge variant="outline">{row.original.role}</Badge>
                    ) : (
                        '—'
                    ),
            },
            {
                id: 'competencies_count',
                header: 'Kompetensi Diisi',
                cell: ({ row }) =>
                    row.original.competencies_count ? (
                        row.original.competencies_count
                    ) : (
                        <span className="text-muted-foreground">
                            Belum mengisi
                        </span>
                    ),
            },
            {
                id: 'verified_count',
                header: 'Terverifikasi',
                cell: ({ row }) => row.original.verified_count ?? 0,
            },
            {
                id: 'actions',
                header: () => <span className="sr-only">Aksi</span>,
                cell: ({ row }) => (
                    <div className="flex justify-end">
                        <DataTableRowActions
                            label={`Aksi untuk ${row.original.name}`}
                            actions={[viewProfileAction(row.original)]}
                        />
                    </div>
                ),
            },
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [filters],
    );

    const sortable =
        filters.view === 'competency'
            ? ['name', 'competency', 'level']
            : ['name', 'competencies_count'];
    const sortIdFor = (id: string): string =>
        id === 'competency' ? 'competency' : id;
    const sort: DataTableSort = {
        id: sortIdFor(filters.sort),
        desc: filters.direction === 'desc',
    };
    const hasActiveFilter =
        filters.search !== '' ||
        filters.organization !== 'all' ||
        filters.group !== 'all' ||
        filters.competency !== 'all' ||
        filters.min_level > 0 ||
        filters.verified_only ||
        filters.filled !== 'all';

    return (
        <>
            <Head title="Direktori Kompetensi" />
            <div className="mx-auto w-full max-w-[1440px] px-4 py-6 md:px-6 md:py-8">
                <PageHeader
                    breadcrumbs={[
                        { title: 'Dashboard', href: dashboard() },
                        { title: 'Direktori Kompetensi', href: index() },
                    ]}
                    title="Direktori Kompetensi"
                    description="Cari anggota berdasarkan kompetensi dan level yang mereka miliki."
                />

                <nav
                    aria-label="Tampilan direktori"
                    className="mb-6 flex gap-1 border-b border-border/70"
                >
                    {tabs.map((tab) => (
                        <button
                            key={tab.value}
                            type="button"
                            onClick={() => switchView(tab.value)}
                            aria-current={
                                filters.view === tab.value ? 'page' : undefined
                            }
                            className={cn(
                                '-mb-px border-b-2 px-4 py-2.5 text-sm font-medium transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring',
                                filters.view === tab.value
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {tab.label}
                        </button>
                    ))}
                </nav>

                <Card className="gap-0 py-0">
                    <DataTable
                        embedded
                        columns={
                            filters.view === 'competency'
                                ? competencyColumns
                                : memberColumns
                        }
                        data={rows.data}
                        rowId={(row) => `${filters.view}-${row.id}`}
                        page={rows.current_page}
                        pageSize={rows.per_page}
                        total={rows.total}
                        onPageChange={(page) => visit({ page }, false)}
                        onPageSizeChange={(per_page) => visit({ per_page })}
                        sort={sort}
                        sortableColumns={sortable}
                        onSortChange={(nextSort) =>
                            visit(
                                nextSort
                                    ? {
                                          sort: nextSort.id,
                                          direction: nextSort.desc
                                              ? 'desc'
                                              : 'asc',
                                      }
                                    : filters.view === 'competency'
                                      ? { sort: 'level', direction: 'desc' }
                                      : { sort: 'name', direction: 'asc' },
                            )
                        }
                        search={searchValue}
                        onSearchChange={setSearchValue}
                        searchPlaceholder="Cari nama, SIM ID, atau email..."
                        filters={
                            <div className="flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap">
                                <div className="w-full sm:w-64">
                                    <Combobox
                                        items={competencyOptions}
                                        value={selectedCompetency}
                                        onValueChange={(
                                            item: CompetencyOption | null,
                                        ) =>
                                            visit({
                                                competency: item
                                                    ? String(item.id)
                                                    : 'all',
                                            })
                                        }
                                        itemToStringLabel={(
                                            item: CompetencyOption,
                                        ) => item.label}
                                        isItemEqualToValue={(
                                            a: CompetencyOption,
                                            b: CompetencyOption,
                                        ) => a.id === b.id}
                                    >
                                        <ComboboxInput
                                            placeholder="Semua kompetensi"
                                            aria-label="Filter kompetensi"
                                            showClear
                                        />
                                        <ComboboxContent>
                                            <ComboboxEmpty>
                                                Kompetensi tidak ditemukan.
                                            </ComboboxEmpty>
                                            <ComboboxList>
                                                {(item: CompetencyOption) => (
                                                    <ComboboxItem
                                                        key={item.id}
                                                        value={item}
                                                    >
                                                        {item.label}
                                                    </ComboboxItem>
                                                )}
                                            </ComboboxList>
                                        </ComboboxContent>
                                    </Combobox>
                                </div>
                                <FilterSelect
                                    label="Filter kelompok"
                                    value={filters.group}
                                    onChange={(group) => visit({ group })}
                                    allLabel="Semua kelompok"
                                    options={groupOptions.map((group) => ({
                                        value: String(group.id),
                                        label: group.label,
                                    }))}
                                />
                                <FilterSelect
                                    label="Filter LOB atau divisi"
                                    value={filters.organization}
                                    onChange={(organization) =>
                                        visit({ organization })
                                    }
                                    allLabel="Semua LOB / Divisi"
                                    options={organizationOptions.map((org) => ({
                                        value: String(org.id),
                                        label: org.label,
                                    }))}
                                />
                                <Select
                                    value={String(filters.min_level)}
                                    onValueChange={(value) =>
                                        visit({ min_level: Number(value ?? 0) })
                                    }
                                    items={levelFilterOptions}
                                >
                                    <SelectTrigger
                                        aria-label="Filter level minimum"
                                        className="w-full sm:w-48"
                                    >
                                        <SelectValue placeholder="Semua level" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {levelFilterOptions.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <FilterSelect
                                    label="Filter verifikasi"
                                    value={
                                        filters.verified_only
                                            ? 'verified'
                                            : 'all'
                                    }
                                    onChange={(value) =>
                                        visit({
                                            verified_only: value === 'verified',
                                        })
                                    }
                                    allLabel="Semua data"
                                    options={[
                                        {
                                            value: 'verified',
                                            label: 'Hanya terverifikasi',
                                        },
                                    ]}
                                />
                                {filters.view === 'member' && (
                                    <FilterSelect
                                        label="Filter pengisian"
                                        value={filters.filled}
                                        onChange={(filled) => visit({ filled })}
                                        allLabel="Semua anggota"
                                        options={[
                                            {
                                                value: 'filled',
                                                label: 'Sudah mengisi',
                                            },
                                            {
                                                value: 'empty',
                                                label: 'Belum mengisi',
                                            },
                                        ]}
                                    />
                                )}
                            </div>
                        }
                        emptyTitle={
                            hasActiveFilter
                                ? 'Data tidak ditemukan'
                                : 'Belum ada data kompetensi'
                        }
                        emptyDescription={
                            hasActiveFilter
                                ? 'Ubah atau kurangi filter untuk melihat hasil lain.'
                                : 'Data akan muncul setelah anggota mengisi Kompetensi Saya.'
                        }
                    />
                </Card>
            </div>

            <CompetencyProfileDialog
                member={selectedMember}
                onClose={() => visit({ member: null }, false)}
            />
        </>
    );
}
