import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import {
    FileDown,
    FileUp,
    PencilLine,
    Plus,
    Power,
    Trash2,
} from 'lucide-react';
import { toast } from 'sonner';
import type {
    DataTableColumnDef,
    DataTableSort,
} from '@/components/data-table';
import { DataTable } from '@/components/data-table';
import { DataTableRowActions } from '@/components/data-table-row-actions';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import CompetencyController from '@/actions/App/Http/Controllers/Admin/CompetencyController';
import competenciesRoute from '@/routes/admin/competencies';
import { dashboard } from '@/routes';
import { competencyGroupBadgeVariant } from '@/lib/badge-variants';

type CompetencyRow = {
    id: number;
    group_id: number;
    group_code: string;
    group_name: string;
    code: string;
    name: string;
    description: string;
    is_active: boolean;
};

type CompetencyGroupOption = {
    id: number;
    code: string;
    name: string;
};

type CompetencyFilters = {
    search: string;
    group: string;
    status: 'all' | 'active' | 'inactive';
    sort: 'code' | 'name' | 'group_id' | 'is_active';
    direction: 'asc' | 'desc';
    per_page: number;
};

type CompetencyQueryFilters = Partial<CompetencyFilters> & {
    page?: number;
};

type CompetencyPageProps = {
    competencies: {
        data: CompetencyRow[];
        current_page: number;
        per_page: number;
        total: number;
    };
    groups: CompetencyGroupOption[];
    filters: CompetencyFilters;
};

export default function CompetenciesIndex({
    competencies,
    groups,
    filters,
}: CompetencyPageProps) {
    const [searchValue, setSearchValue] = useState(filters.search);
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [isImportDialogOpen, setIsImportDialogOpen] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [editingCompetency, setEditingCompetency] =
        useState<CompetencyRow | null>(null);
    const form = useForm({
        code: '',
        group_id: '',
        name: '',
        description: '',
        is_active: true,
    });
    const importForm = useForm<{ file: File | null }>({ file: null });

    function visit(nextFilters: CompetencyQueryFilters, resetPage = true) {
        const query = {
            search: nextFilters.search ?? filters.search,
            group: nextFilters.group ?? filters.group,
            status: nextFilters.status ?? filters.status,
            sort: nextFilters.sort ?? filters.sort,
            direction: nextFilters.direction ?? filters.direction,
            per_page: nextFilters.per_page ?? filters.per_page,
            page: resetPage
                ? 1
                : (nextFilters.page ?? competencies.current_page),
        };

        router.get(
            competenciesRoute.index.url({ query }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['competencies', 'filters'],
                onStart: () => setIsLoading(true),
                onFinish: () => setIsLoading(false),
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
    }, [filters, searchValue]);

    function openCreateDialog(): void {
        setEditingCompetency(null);
        form.resetAndClearErrors();
        form.setData({
            code: '',
            group_id: '',
            name: '',
            description: '',
            is_active: true,
        });
        setIsDialogOpen(true);
    }

    function openEditDialog(competency: CompetencyRow): void {
        setEditingCompetency(competency);
        form.clearErrors();
        form.setData({
            code: competency.code,
            group_id: String(competency.group_id),
            name: competency.name,
            description: competency.description,
            is_active: competency.is_active,
        });
        setIsDialogOpen(true);
    }

    function submitCompetency(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => setIsDialogOpen(false),
        };

        if (editingCompetency) {
            form.patch(
                CompetencyController.update.url(editingCompetency.id),
                options,
            );

            return;
        }

        form.post(CompetencyController.store.url(), options);
    }

    function submitImport(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        importForm.post(CompetencyController.import.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setIsImportDialogOpen(false);
                importForm.reset();
            },
            onError: () =>
                toast.error('Impor gagal. Periksa pesan kesalahan di bawah.'),
        });
    }

    function updateCompetencyStatus(competency: CompetencyRow): void {
        router.patch(
            CompetencyController.update.url(competency.id),
            { is_active: !competency.is_active },
            {
                preserveScroll: true,
                onError: () =>
                    toast.error('Status kompetensi gagal diperbarui.'),
            },
        );
    }

    function deleteCompetency(competency: CompetencyRow): void {
        router.delete(CompetencyController.destroy.url(competency.id), {
            preserveScroll: true,
            onError: () => toast.error('Kompetensi gagal dihapus.'),
        });
    }

    const columns = useMemo<DataTableColumnDef<CompetencyRow>[]>(
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
                header: 'Kompetensi',
                cell: ({ row }) => (
                    <div className="grid max-w-xl gap-1">
                        <span className="font-medium text-foreground">
                            {row.original.name}
                        </span>
                        <span className="line-clamp-2 text-xs text-muted-foreground">
                            {row.original.description}
                        </span>
                    </div>
                ),
            },
            {
                id: 'group_id',
                accessorKey: 'group_id',
                header: 'Kelompok',
                cell: ({ row }) => (
                    <Badge
                        variant={competencyGroupBadgeVariant(
                            row.original.group_code,
                        )}
                    >
                        {row.original.group_code} · {row.original.group_name}
                    </Badge>
                ),
            },
            {
                id: 'is_active',
                accessorKey: 'is_active',
                header: 'Status',
                cell: ({ row }) => (
                    <Badge
                        variant={
                            row.original.is_active ? 'success' : 'secondary'
                        }
                    >
                        {row.original.is_active ? 'Aktif' : 'Nonaktif'}
                    </Badge>
                ),
            },
            {
                id: 'actions',
                header: 'Aksi',
                cell: ({ row }) => {
                    const competency = row.original;

                    return (
                        <DataTableRowActions
                            label={`Aksi untuk ${competency.code} ${competency.name}`}
                            actions={[
                                {
                                    label: 'Edit',
                                    icon: PencilLine,
                                    onSelect: () => openEditDialog(competency),
                                },
                                {
                                    label: competency.is_active
                                        ? 'Nonaktifkan'
                                        : 'Aktifkan',
                                    icon: Power,
                                    destructive: competency.is_active,
                                    requiresConfirmation: true,
                                    confirmTitle: competency.is_active
                                        ? 'Nonaktifkan kompetensi?'
                                        : 'Aktifkan kompetensi?',
                                    confirmDescription: competency.is_active
                                        ? 'Kompetensi tidak lagi tersedia untuk pilihan baru.'
                                        : 'Kompetensi akan tersedia kembali untuk pilihan baru.',
                                    confirmLabel: competency.is_active
                                        ? 'Nonaktifkan'
                                        : 'Aktifkan',
                                    onSelect: () =>
                                        updateCompetencyStatus(competency),
                                },
                                {
                                    label: 'Hapus',
                                    icon: Trash2,
                                    destructive: true,
                                    confirmTitle: 'Hapus kompetensi?',
                                    confirmDescription: `${competency.code} akan dihapus permanen jika belum dipakai pada profil atau kebutuhan proyek.`,
                                    confirmLabel: 'Hapus',
                                    onSelect: () =>
                                        deleteCompetency(competency),
                                },
                            ]}
                        />
                    );
                },
            },
        ],
        [],
    );

    const sort: DataTableSort = {
        id: filters.sort,
        desc: filters.direction === 'desc',
    };
    const hasActiveFilter =
        filters.search !== '' ||
        filters.group !== 'all' ||
        filters.status !== 'all';

    return (
        <>
            <Head title="Master Kompetensi" />
            <div className="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <PageHeader
                    breadcrumbs={[
                        { title: 'Dashboard', href: dashboard() },
                        {
                            title: 'Master Kompetensi',
                            href: competenciesRoute.index(),
                        },
                    ]}
                    title="Master Kompetensi"
                    description="Kelola katalog kompetensi resmi QCC dan kelompoknya."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="info"
                                className="h-10 px-4 text-sm"
                                onClick={() => {
                                    importForm.resetAndClearErrors();
                                    setIsImportDialogOpen(true);
                                }}
                            >
                                <FileUp
                                    data-icon="inline-start"
                                    aria-hidden="true"
                                />
                                Impor Excel
                            </Button>
                            <Button
                                type="button"
                                className="h-10 px-4 text-sm"
                                onClick={openCreateDialog}
                            >
                                <Plus
                                    data-icon="inline-start"
                                    aria-hidden="true"
                                />
                                Tambah Kompetensi
                            </Button>
                        </div>
                    }
                />

                <Card className="gap-0 py-0">
                    <DataTable
                        embedded
                        columns={columns}
                        data={competencies.data}
                        rowId={(competency) => String(competency.id)}
                        page={competencies.current_page}
                        pageSize={competencies.per_page}
                        total={competencies.total}
                        isLoading={isLoading}
                        onPageChange={(page) => visit({ page }, false)}
                        onPageSizeChange={(per_page) => visit({ per_page })}
                        sort={sort}
                        sortableColumns={[
                            'code',
                            'name',
                            'group_id',
                            'is_active',
                        ]}
                        onSortChange={(nextSort) =>
                            visit({
                                sort: (nextSort?.id ??
                                    'code') as CompetencyFilters['sort'],
                                direction: nextSort?.desc ? 'desc' : 'asc',
                            })
                        }
                        search={searchValue}
                        onSearchChange={setSearchValue}
                        searchPlaceholder="Cari kode, nama, atau definisi kompetensi..."
                        filters={
                            <>
                                <Select
                                    value={filters.group}
                                    onValueChange={(value) =>
                                        visit({ group: value ?? 'all' })
                                    }
                                    items={[
                                        {
                                            value: 'all',
                                            label: 'Semua kelompok',
                                        },
                                        ...groups.map((group) => ({
                                            value: group.code,
                                            label: `${group.code} · ${group.name}`,
                                        })),
                                    ]}
                                >
                                    <SelectTrigger
                                        aria-label="Filter kelompok kompetensi"
                                        className="w-full sm:w-48"
                                    >
                                        <SelectValue placeholder="Semua kelompok" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="all">
                                                Semua kelompok
                                            </SelectItem>
                                            {groups.map((group) => (
                                                <SelectItem
                                                    key={group.id}
                                                    value={group.code}
                                                >
                                                    {group.code} · {group.name}
                                                </SelectItem>
                                            ))}
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                <Select
                                    value={filters.status}
                                    onValueChange={(value) =>
                                        visit({
                                            status: (value ??
                                                'all') as CompetencyFilters['status'],
                                        })
                                    }
                                    items={[
                                        { value: 'all', label: 'Semua status' },
                                        { value: 'active', label: 'Aktif' },
                                        {
                                            value: 'inactive',
                                            label: 'Nonaktif',
                                        },
                                    ]}
                                >
                                    <SelectTrigger
                                        aria-label="Filter status kompetensi"
                                        className="w-full sm:w-40"
                                    >
                                        <SelectValue placeholder="Semua status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem value="all">
                                                Semua status
                                            </SelectItem>
                                            <SelectItem value="active">
                                                Aktif
                                            </SelectItem>
                                            <SelectItem value="inactive">
                                                Nonaktif
                                            </SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                            </>
                        }
                        emptyTitle={
                            hasActiveFilter
                                ? 'Kompetensi tidak ditemukan'
                                : 'Katalog kompetensi belum tersedia'
                        }
                        emptyDescription={
                            hasActiveFilter
                                ? 'Ubah kata kunci atau filter untuk melihat hasil lain.'
                                : 'Kelompok A–F tersedia. Tambahkan data berdasarkan katalog resmi C001–C062.'
                        }
                    />
                </Card>
            </div>

            <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
                <DialogContent className="gap-6 sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>
                            {editingCompetency
                                ? 'Edit Kompetensi'
                                : 'Tambah Kompetensi'}
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submitCompetency} className="grid gap-6">
                        <FormField
                            id="code"
                            label="Kode kompetensi"
                            required
                            error={form.errors.code}
                        >
                            <Input
                                name="code"
                                value={form.data.code}
                                onChange={(event) =>
                                    form.setData(
                                        'code',
                                        event.target.value.toUpperCase(),
                                    )
                                }
                                placeholder="contoh: C001"
                                autoComplete="off"
                                maxLength={10}
                            />
                        </FormField>
                        <FormField
                            id="group_id"
                            label="Kelompok kompetensi"
                            required
                            error={form.errors.group_id}
                        >
                            <Select
                                value={form.data.group_id}
                                onValueChange={(value) =>
                                    form.setData('group_id', value ?? '')
                                }
                                items={groups.map((group) => ({
                                    value: String(group.id),
                                    label: `${group.code} · ${group.name}`,
                                }))}
                            >
                                <SelectTrigger
                                    aria-label="Kelompok kompetensi"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Pilih kelompok A–F" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectLabel>
                                            Kelompok resmi
                                        </SelectLabel>
                                        {groups.map((group) => (
                                            <SelectItem
                                                key={group.id}
                                                value={String(group.id)}
                                            >
                                                {group.code} · {group.name}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </FormField>
                        <FormField
                            id="name"
                            label="Nama kompetensi"
                            required
                            error={form.errors.name}
                        >
                            <Input
                                name="name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                placeholder="Nama kompetensi resmi"
                                autoComplete="off"
                                maxLength={255}
                            />
                        </FormField>
                        <FormField
                            id="description"
                            label="Definisi kompetensi"
                            required
                            error={form.errors.description}
                        >
                            <Textarea
                                name="description"
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                                placeholder="Tuliskan definisi resmi kompetensi..."
                                rows={4}
                                maxLength={65535}
                            />
                        </FormField>
                        <FormField
                            id="is_active"
                            label="Status kompetensi"
                            required
                            error={form.errors.is_active}
                        >
                            <Switch
                                name="is_active"
                                checked={form.data.is_active}
                                onCheckedChange={(checked) =>
                                    form.setData('is_active', checked)
                                }
                            />
                        </FormField>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                className="h-10 px-4 text-sm"
                                disabled={form.processing}
                                onClick={() => setIsDialogOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                className="h-10 px-4 text-sm"
                                disabled={form.processing}
                            >
                                {form.processing
                                    ? 'Menyimpan...'
                                    : 'Simpan Kompetensi'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={isImportDialogOpen}
                onOpenChange={setIsImportDialogOpen}
            >
                <DialogContent className="gap-6 sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Impor Master Kompetensi</DialogTitle>
                        <p className="text-sm text-muted-foreground">
                            Unduh template, isi kolom Kelompok, Nama Kompetensi,
                            dan Definisi, lalu unggah kembali. Kode kompetensi
                            yang sudah ada akan diperbarui.
                        </p>
                    </DialogHeader>
                    <form onSubmit={submitImport} className="grid gap-5">
                        <div className="flex items-center justify-between gap-3 rounded-lg border border-border bg-muted/60 p-3">
                            <div className="grid gap-0.5">
                                <p className="text-sm font-medium">
                                    Template Excel
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Berisi kode C001–C062 dan pilihan kelompok.
                                    Kompetensi yang sudah ada ikut terisi.
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                className="h-9 shrink-0 px-3 text-sm"
                                render={
                                    <a
                                        href={CompetencyController.template.url()}
                                        download
                                    />
                                }
                                nativeButton={false}
                            >
                                <FileDown
                                    data-icon="inline-start"
                                    aria-hidden="true"
                                />
                                Unduh template
                            </Button>
                        </div>
                        <FormField
                            id="competency_file"
                            label="File Excel (.xlsx)"
                            required
                            error={importForm.errors.file}
                        >
                            <Input
                                id="competency_file"
                                type="file"
                                accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                onChange={(event) =>
                                    importForm.setData(
                                        'file',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                        </FormField>
                        <p className="text-xs text-muted-foreground">
                            Jika ada baris yang salah, seluruh impor dibatalkan
                            dan nomor baris Excel yang bermasalah ditampilkan.
                        </p>
                        {Object.entries(importForm.errors)
                            .filter(([key]) => key.startsWith('row_'))
                            .map(([key, message]) => (
                                <p
                                    key={key}
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {message}
                                </p>
                            ))}
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                className="h-10 px-4 text-sm"
                                disabled={importForm.processing}
                                onClick={() => setIsImportDialogOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                variant="info"
                                className="h-10 px-4 text-sm"
                                disabled={
                                    !importForm.data.file ||
                                    importForm.processing
                                }
                            >
                                <FileUp
                                    data-icon="inline-start"
                                    aria-hidden="true"
                                />
                                {importForm.processing
                                    ? 'Mengimpor...'
                                    : 'Impor Kompetensi'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
