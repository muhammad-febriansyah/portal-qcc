import { Head, router, useForm } from '@inertiajs/react';
import type {
    DataTableColumnDef,
    DataTableSort,
} from '@/components/data-table';
import { DataTable } from '@/components/data-table';
import { DataTableRowActions } from '@/components/data-table-row-actions';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { useAppName } from '@/hooks/use-app-name';
import { Badge } from '@/components/ui/badge';
import {
    Combobox,
    ComboboxChip,
    ComboboxChips,
    ComboboxChipsInput,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxItem,
    ComboboxList,
    ComboboxValue,
    useComboboxAnchor,
} from '@/components/ui/combobox';
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
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import OrganizationController from '@/actions/App/Http/Controllers/OrganizationController';
import organizationsRoute from '@/routes/admin/organizations';
import { dashboard } from '@/routes';
import { useEffect, useMemo, useState } from 'react';
import { PencilLine, Plus, Power, Trash2 } from 'lucide-react';
import { toast } from 'sonner';

type OrganizationRow = {
    id: number;
    code: string;
    name: string;
    is_active: boolean;
    mentor_ids: number[];
    mentor_names: string[];
};

type MentorOption = {
    id: number;
    name: string;
    email: string;
};

type OrganizationFilters = {
    search: string;
    status: 'all' | 'active' | 'inactive';
    sort: 'code' | 'name' | 'is_active';
    direction: 'asc' | 'desc';
    per_page: number;
};

type OrganizationQueryFilters = Partial<OrganizationFilters> & {
    page?: number;
};

type OrganizationPageProps = {
    organizations: {
        data: OrganizationRow[];
        current_page: number;
        per_page: number;
        total: number;
    };
    filters: OrganizationFilters;
    mentorOptions: MentorOption[];
};

export default function OrganizationsIndex({
    organizations,
    filters,
    mentorOptions,
}: OrganizationPageProps) {
    const mentorAnchor = useComboboxAnchor();
    const appName = useAppName();
    const [searchValue, setSearchValue] = useState(filters.search);
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [editingOrganization, setEditingOrganization] =
        useState<OrganizationRow | null>(null);
    const form = useForm<{
        code: string;
        name: string;
        mentor_ids: number[];
        is_active: boolean;
    }>({
        code: '',
        name: '',
        mentor_ids: [],
        is_active: true,
    });

    function visit(nextFilters: OrganizationQueryFilters, resetPage = true) {
        const query = {
            search: nextFilters.search ?? filters.search,
            status: nextFilters.status ?? filters.status,
            sort: nextFilters.sort ?? filters.sort,
            direction: nextFilters.direction ?? filters.direction,
            per_page: nextFilters.per_page ?? filters.per_page,
            page: resetPage
                ? 1
                : (nextFilters.page ?? organizations.current_page),
        };

        router.get(
            organizationsRoute.index.url({ query }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['organizations', 'filters'],
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
        setEditingOrganization(null);
        form.resetAndClearErrors();
        form.setData({
            code: '',
            name: '',
            mentor_ids: [],
            is_active: true,
        });
        setIsDialogOpen(true);
    }

    function openEditDialog(organization: OrganizationRow): void {
        setEditingOrganization(organization);
        form.clearErrors();
        form.setData({
            code: organization.code,
            name: organization.name,
            mentor_ids: organization.mentor_ids,
            is_active: organization.is_active,
        });
        setIsDialogOpen(true);
    }

    function submitOrganization(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => setIsDialogOpen(false),
        };

        if (editingOrganization) {
            form.patch(
                OrganizationController.update.url(editingOrganization.id),
                options,
            );

            return;
        }

        form.post(OrganizationController.store.url(), options);
    }

    function updateOrganizationStatus(organization: OrganizationRow): void {
        router.patch(
            OrganizationController.update.url(organization.id),
            { is_active: !organization.is_active },
            {
                preserveScroll: true,
                onError: () =>
                    toast.error('Status organisasi gagal diperbarui.'),
            },
        );
    }

    function deleteOrganization(organization: OrganizationRow): void {
        router.delete(OrganizationController.destroy.url(organization.id), {
            preserveScroll: true,
            onError: () => toast.error('Organisasi gagal dihapus.'),
        });
    }

    const columns = useMemo<DataTableColumnDef<OrganizationRow>[]>(
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
                header: 'Nama Organisasi',
                cell: ({ row }) => row.original.name,
            },
            {
                id: 'mentors',
                header: 'Mentor',
                cell: ({ row }) =>
                    row.original.mentor_names.length > 0 ? (
                        row.original.mentor_names.join(' / ')
                    ) : (
                        <span className="text-muted-foreground">
                            Belum diatur
                        </span>
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
                    const organization = row.original;

                    return (
                        <DataTableRowActions
                            label={`Aksi untuk ${organization.name}`}
                            actions={[
                                {
                                    label: 'Edit',
                                    icon: PencilLine,
                                    onSelect: () =>
                                        openEditDialog(organization),
                                },
                                {
                                    label: organization.is_active
                                        ? 'Nonaktifkan'
                                        : 'Aktifkan',
                                    icon: Power,
                                    destructive: organization.is_active,
                                    requiresConfirmation: true,
                                    confirmTitle: organization.is_active
                                        ? 'Nonaktifkan organisasi?'
                                        : 'Aktifkan organisasi?',
                                    confirmDescription: organization.is_active
                                        ? 'Organisasi nonaktif tidak tersedia untuk pilihan baru.'
                                        : 'Organisasi akan tersedia kembali untuk pilihan baru.',
                                    confirmLabel: organization.is_active
                                        ? 'Nonaktifkan'
                                        : 'Aktifkan',
                                    onSelect: () =>
                                        updateOrganizationStatus(organization),
                                },
                                {
                                    label: 'Hapus',
                                    icon: Trash2,
                                    destructive: true,
                                    requiresConfirmation: true,
                                    confirmTitle: 'Hapus organisasi?',
                                    confirmDescription:
                                        'Organisasi akan dihapus. Anggota yang terkait akan kehilangan asosiasi ke organisasi ini.',
                                    confirmLabel: 'Hapus organisasi',
                                    onSelect: () =>
                                        deleteOrganization(organization),
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
    const hasActiveFilter = filters.search !== '' || filters.status !== 'all';

    return (
        <>
            <Head title="Master Organisasi" />
            <div className="mx-auto w-full max-w-[1440px] px-4 py-6 md:px-6 md:py-8">
                <PageHeader
                    breadcrumbs={[
                        { title: 'Dashboard', href: dashboard() },
                        {
                            title: 'Master Organisasi',
                            href: organizationsRoute.index(),
                        },
                    ]}
                    title="Master Organisasi"
                    description={`Kelola daftar organisasi yang digunakan di ${appName}.`}
                    actions={
                        <Button
                            type="button"
                            className="h-10 px-4 text-sm"
                            onClick={openCreateDialog}
                        >
                            <Plus data-icon="inline-start" aria-hidden="true" />
                            Tambah Organisasi
                        </Button>
                    }
                />

                <Card className="gap-0 py-0">
                    <DataTable
                        embedded
                        columns={columns}
                        data={organizations.data}
                        rowId={(organization) => String(organization.id)}
                        page={organizations.current_page}
                        pageSize={organizations.per_page}
                        total={organizations.total}
                        isLoading={isLoading}
                        onPageChange={(page) => visit({ page }, false)}
                        onPageSizeChange={(per_page) => visit({ per_page })}
                        sort={sort}
                        sortableColumns={['code', 'name', 'is_active']}
                        onSortChange={(nextSort) =>
                            visit({
                                sort: (nextSort?.id ??
                                    'name') as OrganizationFilters['sort'],
                                direction: nextSort?.desc ? 'desc' : 'asc',
                            })
                        }
                        search={searchValue}
                        onSearchChange={setSearchValue}
                        searchPlaceholder="Cari kode atau nama organisasi..."
                        filters={
                            <Select
                                value={filters.status}
                                items={[
                                    { value: 'all', label: 'Semua status' },
                                    { value: 'active', label: 'Aktif' },
                                    { value: 'inactive', label: 'Nonaktif' },
                                ]}
                                onValueChange={(value) =>
                                    visit({
                                        status: (value ??
                                            'all') as OrganizationFilters['status'],
                                    })
                                }
                            >
                                <SelectTrigger
                                    aria-label="Filter status organisasi"
                                    className="w-full sm:w-40"
                                >
                                    <SelectValue placeholder="Semua status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        Semua status
                                    </SelectItem>
                                    <SelectItem value="active">
                                        Aktif
                                    </SelectItem>
                                    <SelectItem value="inactive">
                                        Nonaktif
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        }
                        emptyTitle={
                            hasActiveFilter
                                ? 'Organisasi tidak ditemukan'
                                : 'Organisasi belum tersedia'
                        }
                        emptyDescription={
                            hasActiveFilter
                                ? 'Ubah kata kunci atau filter status untuk melihat hasil lain.'
                                : 'Tambah organisasi pertama untuk mulai menggunakan master organisasi.'
                        }
                    />
                </Card>
            </div>

            <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
                <DialogContent className="gap-6 sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {editingOrganization
                                ? 'Edit Organisasi'
                                : 'Tambah Organisasi'}
                        </DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submitOrganization} className="grid gap-6">
                        <FormField
                            id="code"
                            label="Kode organisasi"
                            required
                            error={form.errors.code}
                        >
                            <Input
                                name="code"
                                value={form.data.code}
                                onChange={(event) =>
                                    form.setData('code', event.target.value)
                                }
                                placeholder="contoh: div-it"
                                autoComplete="off"
                                maxLength={255}
                            />
                        </FormField>
                        <FormField
                            id="name"
                            label="Nama organisasi"
                            required
                            error={form.errors.name}
                        >
                            <Input
                                name="name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                placeholder="contoh: DIV IT"
                                autoComplete="organization"
                                maxLength={255}
                            />
                        </FormField>
                        <FormField
                            id="mentor_ids"
                            label="Mentor"
                            hint="Otomatis menjadi mentor dan reviewer setiap proposal yang diajukan anggota organisasi ini. Bila ada dua mentor, salah satunya cukup untuk mereview."
                            error={
                                form.errors.mentor_ids ??
                                Object.entries(form.errors).find(([key]) =>
                                    key.startsWith('mentor_ids.'),
                                )?.[1]
                            }
                        >
                            <Combobox
                                multiple
                                items={mentorOptions}
                                value={mentorOptions.filter((option) =>
                                    form.data.mentor_ids.includes(option.id),
                                )}
                                onValueChange={(options: MentorOption[]) =>
                                    form.setData(
                                        'mentor_ids',
                                        options.map((option) => option.id),
                                    )
                                }
                                itemToStringLabel={(option: MentorOption) =>
                                    option.name
                                }
                                isItemEqualToValue={(
                                    a: MentorOption,
                                    b: MentorOption,
                                ) => a.id === b.id}
                            >
                                <ComboboxChips ref={mentorAnchor}>
                                    <ComboboxValue>
                                        {(values: MentorOption[]) => (
                                            <>
                                                {values.map((option) => (
                                                    <ComboboxChip
                                                        key={option.id}
                                                    >
                                                        {option.name}
                                                    </ComboboxChip>
                                                ))}
                                                <ComboboxChipsInput
                                                    id="mentor_ids"
                                                    placeholder={
                                                        values.length > 0
                                                            ? ''
                                                            : 'Cari nama pengguna'
                                                    }
                                                    aria-label="Pilih mentor"
                                                    aria-invalid={Boolean(
                                                        form.errors.mentor_ids,
                                                    )}
                                                />
                                            </>
                                        )}
                                    </ComboboxValue>
                                </ComboboxChips>
                                <ComboboxContent anchor={mentorAnchor}>
                                    <ComboboxEmpty>
                                        Pengguna tidak ditemukan.
                                    </ComboboxEmpty>
                                    <ComboboxList>
                                        {(option: MentorOption) => (
                                            <ComboboxItem
                                                key={option.id}
                                                value={option}
                                            >
                                                <span className="flex min-w-0 flex-col">
                                                    <span>{option.name}</span>
                                                    <span className="text-xs text-muted-foreground">
                                                        {option.email}
                                                    </span>
                                                </span>
                                            </ComboboxItem>
                                        )}
                                    </ComboboxList>
                                </ComboboxContent>
                            </Combobox>
                        </FormField>
                        <FormField
                            id="is_active"
                            label="Status organisasi"
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
                                    : 'Simpan Organisasi'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
