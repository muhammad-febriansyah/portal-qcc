import { Head, router, useForm } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import {
    CircleCheck,
    CirclePause,
    KeyRound,
    PencilLine,
    Plus,
    Power,
    Trash2,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { DataTable } from '@/components/data-table';
import type {
    DataTableColumnDef,
    DataTableSort,
} from '@/components/data-table';
import { DataTableRowActions } from '@/components/data-table-row-actions';
import { PageHeader } from '@/components/page-header';
import { FormField } from '@/components/form-field';
import { useAppName } from '@/hooks/use-app-name';
import { UserFormDialog } from '@/components/user-form-dialog';
import type {
    OrganizationOption,
    UserFormValues,
} from '@/components/user-form-dialog';
import { Badge } from '@/components/ui/badge';
import { roleBadgeVariant } from '@/lib/badge-variants';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import { index } from '@/routes/admin/users';
import { update as updatePassword } from '@/routes/admin/users/password';
import { update as updateStatus } from '@/routes/admin/users/status';
import { update as updateBulkStatus } from '@/routes/admin/users/bulk-status';
import { destroy as destroyBulkUsers } from '@/routes/admin/users/bulk';

type UserRow = UserFormValues & {
    organization_name: string | null;
    is_active: boolean;
    is_self: boolean;
    updated_at: string | null;
};

type UserSort = 'name' | 'sim_id' | 'email' | 'is_active' | 'updated_at';

type UserFilters = {
    search: string;
    role: string;
    status: string;
    sort: UserSort;
    direction: 'asc' | 'desc';
    per_page: number;
};

type UserQuery = Partial<UserFilters> & { page?: number };

type Props = {
    users: {
        data: UserRow[];
        current_page: number;
        per_page: number;
        total: number;
    };
    filters: UserFilters;
    roleOptions: string[];
    organizationOptions: OrganizationOption[];
};

const statusOptions = [
    { value: 'all', label: 'Semua status' },
    { value: 'active', label: 'Aktif' },
    { value: 'inactive', label: 'Nonaktif' },
];

function formatDateTime(value: string): string {
    return format(parseISO(value), 'd MMM yyyy, HH:mm', {
        locale: indonesianLocale,
    });
}

function StatusBadge({ isActive }: { isActive: boolean }) {
    const Icon = isActive ? CircleCheck : CirclePause;

    return (
        <Badge variant={isActive ? 'success' : 'secondary'} className="gap-1">
            <Icon aria-hidden="true" />
            {isActive ? 'Aktif' : 'Nonaktif'}
        </Badge>
    );
}

export default function UsersIndex({
    users,
    filters,
    roleOptions,
    organizationOptions,
}: Props) {
    const appName = useAppName();
    const [searchValue, setSearchValue] = useState(filters.search);
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [editingUser, setEditingUser] = useState<UserRow | null>(null);
    const [resetUser, setResetUser] = useState<UserRow | null>(null);
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [bulkDeleteOpen, setBulkDeleteOpen] = useState(false);
    const [bulkProcessing, setBulkProcessing] = useState(false);
    const resetForm = useForm({ password: '', password_confirmation: '' });

    function visit(nextQuery: UserQuery, resetPage = true): void {
        setSelectedIds([]);

        const query = {
            search: nextQuery.search ?? filters.search,
            role: nextQuery.role ?? filters.role,
            status: nextQuery.status ?? filters.status,
            sort: nextQuery.sort ?? filters.sort,
            direction: nextQuery.direction ?? filters.direction,
            per_page: nextQuery.per_page ?? filters.per_page,
            page: resetPage ? 1 : (nextQuery.page ?? users.current_page),
        };

        router.get(
            index.url({ query }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['users', 'filters'],
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

    function openCreateDialog(): void {
        setEditingUser(null);
        setIsDialogOpen(true);
    }

    function openEditDialog(user: UserRow): void {
        setEditingUser(user);
        setIsDialogOpen(true);
    }

    function openResetPassword(user: UserRow): void {
        resetForm.reset();
        resetForm.clearErrors();
        setResetUser(user);
    }

    function closeResetPassword(): void {
        setResetUser(null);
        resetForm.reset();
        resetForm.clearErrors();
    }

    function toggleStatus(user: UserRow): void {
        router.patch(
            updateStatus.url(user.id),
            { is_active: !user.is_active },
            { preserveScroll: true },
        );
    }

    function toggleSelectedUser(userId: number, isChecked: boolean): void {
        setSelectedIds((current) =>
            isChecked
                ? [...new Set([...current, userId])]
                : current.filter((id) => id !== userId),
        );
    }

    function toggleVisibleUsers(isChecked: boolean): void {
        const visibleIds = users.data
            .filter((user) => !user.is_self)
            .map((user) => user.id);

        setSelectedIds((current) => {
            const remaining = current.filter((id) => !visibleIds.includes(id));

            return isChecked ? [...remaining, ...visibleIds] : remaining;
        });
    }

    function updateSelectedStatus(isActive: boolean): void {
        if (selectedIds.length === 0) {
            return;
        }

        router.patch(
            updateBulkStatus.url(),
            { user_ids: selectedIds, is_active: isActive },
            {
                preserveScroll: true,
                onStart: () => setBulkProcessing(true),
                onFinish: () => setBulkProcessing(false),
                onSuccess: () => setSelectedIds([]),
            },
        );
    }

    function deleteSelectedUsers(): void {
        if (selectedIds.length === 0) {
            return;
        }

        router.delete(destroyBulkUsers.url(), {
            data: { user_ids: selectedIds },
            preserveScroll: true,
            onStart: () => setBulkProcessing(true),
            onFinish: () => setBulkProcessing(false),
            onSuccess: () => {
                setSelectedIds([]);
                setBulkDeleteOpen(false);
            },
        });
    }

    const selectableIds = users.data
        .filter((user) => !user.is_self)
        .map((user) => user.id);
    const selectedVisibleCount = selectableIds.filter((id) =>
        selectedIds.includes(id),
    ).length;
    const allVisibleSelected =
        selectableIds.length > 0 &&
        selectedVisibleCount === selectableIds.length;
    const someVisibleSelected = selectedVisibleCount > 0 && !allVisibleSelected;

    const leadingColumns: DataTableColumnDef<UserRow>[] = [
        {
            id: 'select',
            header: () => (
                <Checkbox
                    aria-label="Pilih semua anggota di halaman ini"
                    checked={allVisibleSelected}
                    indeterminate={someVisibleSelected}
                    disabled={selectableIds.length === 0}
                    onCheckedChange={(checked) =>
                        toggleVisibleUsers(checked === true)
                    }
                />
            ),
            cell: ({ row }) => (
                <Checkbox
                    aria-label={`Pilih ${row.original.name}`}
                    checked={selectedIds.includes(row.original.id)}
                    disabled={row.original.is_self}
                    onCheckedChange={(checked) =>
                        toggleSelectedUser(row.original.id, checked === true)
                    }
                />
            ),
        },
    ];

    const columns = useMemo<DataTableColumnDef<UserRow>[]>(
        () => [
            {
                id: 'name',
                accessorKey: 'name',
                header: 'Nama',
                cell: ({ row }) => (
                    <span className="font-medium text-foreground">
                        {row.original.name}
                        {row.original.is_self && (
                            <span className="ml-2 text-xs font-normal text-muted-foreground">
                                (Anda)
                            </span>
                        )}
                    </span>
                ),
            },
            {
                id: 'sim_id',
                accessorKey: 'sim_id',
                header: 'SIM ID',
                cell: ({ row }) => (
                    <span className="whitespace-nowrap">
                        {row.original.sim_id ?? '—'}
                    </span>
                ),
            },
            {
                id: 'email',
                accessorKey: 'email',
                header: 'Email',
                cell: ({ row }) => row.original.email,
            },
            {
                id: 'organization_name',
                header: 'Organisasi',
                cell: ({ row }) => row.original.organization_name ?? '—',
            },
            {
                id: 'role',
                header: 'Role',
                cell: ({ row }) =>
                    row.original.role ? (
                        <Badge variant={roleBadgeVariant(row.original.role)}>
                            {row.original.role}
                        </Badge>
                    ) : (
                        '—'
                    ),
            },
            {
                id: 'is_active',
                accessorKey: 'is_active',
                header: 'Status',
                cell: ({ row }) => (
                    <StatusBadge isActive={row.original.is_active} />
                ),
            },
            {
                id: 'updated_at',
                accessorKey: 'updated_at',
                header: 'Diperbarui',
                cell: ({ row }) => (
                    <span className="whitespace-nowrap text-muted-foreground">
                        {row.original.updated_at
                            ? formatDateTime(row.original.updated_at)
                            : '—'}
                    </span>
                ),
            },
            {
                id: 'actions',
                header: () => <span className="sr-only">Aksi</span>,
                cell: ({ row }) => {
                    const user = row.original;

                    return (
                        <div className="flex justify-end">
                            <DataTableRowActions
                                label={`Aksi untuk ${user.name}`}
                                actions={[
                                    {
                                        label: 'Ubah',
                                        icon: PencilLine,
                                        onSelect: () => openEditDialog(user),
                                    },
                                    {
                                        label: 'Reset Password',
                                        icon: KeyRound,
                                        onSelect: () => openResetPassword(user),
                                    },
                                    {
                                        label: user.is_active
                                            ? 'Nonaktifkan'
                                            : 'Aktifkan',
                                        icon: Power,
                                        destructive: user.is_active,
                                        requiresConfirmation: true,
                                        hidden: user.is_self,
                                        confirmTitle: user.is_active
                                            ? 'Nonaktifkan akun?'
                                            : 'Aktifkan akun?',
                                        confirmDescription: user.is_active
                                            ? `${user.name} tidak akan bisa masuk ke ${appName} sampai akunnya diaktifkan kembali.`
                                            : `${user.name} dapat masuk kembali ke ${appName}.`,
                                        confirmLabel: user.is_active
                                            ? 'Nonaktifkan'
                                            : 'Aktifkan',
                                        onSelect: () => toggleStatus(user),
                                    },
                                ]}
                            />
                        </div>
                    );
                },
            },
        ],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [appName, users.data],
    );

    const sort: DataTableSort = {
        id: filters.sort,
        desc: filters.direction === 'desc',
    };
    const hasActiveFilter =
        filters.search !== '' ||
        filters.role !== 'all' ||
        filters.status !== 'all';

    return (
        <>
            <Head title="Anggota" />
            <div className="mx-auto w-full max-w-[1440px] px-4 py-6 md:px-6 md:py-8">
                <PageHeader
                    breadcrumbs={[
                        { title: 'Dashboard', href: dashboard() },
                        { title: 'Anggota', href: index() },
                    ]}
                    title="Anggota"
                    description={`Kelola akun, organisasi, dan role pengguna ${appName}.`}
                    actions={
                        <Button
                            type="button"
                            className="h-10 px-4 text-sm"
                            onClick={openCreateDialog}
                        >
                            <Plus data-icon="inline-start" aria-hidden="true" />
                            Tambah Anggota
                        </Button>
                    }
                />

                <Card className="gap-0 py-0">
                    <DataTable
                        embedded
                        leadingColumns={leadingColumns}
                        columns={columns}
                        data={users.data}
                        rowId={(row) => String(row.id)}
                        page={users.current_page}
                        pageSize={users.per_page}
                        total={users.total}
                        onPageChange={(page) => visit({ page }, false)}
                        onPageSizeChange={(per_page) => visit({ per_page })}
                        sort={sort}
                        sortableColumns={[
                            'name',
                            'email',
                            'is_active',
                            'updated_at',
                        ]}
                        onSortChange={(nextSort) =>
                            visit(
                                nextSort
                                    ? {
                                          sort: nextSort.id as UserSort,
                                          direction: nextSort.desc
                                              ? 'desc'
                                              : 'asc',
                                      }
                                    : { sort: 'name', direction: 'asc' },
                            )
                        }
                        search={searchValue}
                        onSearchChange={setSearchValue}
                        searchPlaceholder="Cari nama, SIM ID, atau email..."
                        filters={
                            <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                                <Select
                                    value={filters.role}
                                    onValueChange={(value) =>
                                        visit({ role: value ?? 'all' })
                                    }
                                    items={[
                                        { value: 'all', label: 'Semua role' },
                                        ...roleOptions.map((role) => ({
                                            value: role,
                                            label: role,
                                        })),
                                    ]}
                                >
                                    <SelectTrigger
                                        aria-label="Filter role"
                                        className="w-full sm:w-44"
                                    >
                                        <SelectValue placeholder="Semua role" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Semua role
                                        </SelectItem>
                                        {roleOptions.map((role) => (
                                            <SelectItem key={role} value={role}>
                                                {role}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select
                                    value={filters.status}
                                    onValueChange={(value) =>
                                        visit({ status: value ?? 'all' })
                                    }
                                    items={statusOptions}
                                >
                                    <SelectTrigger
                                        aria-label="Filter status"
                                        className="w-full sm:w-40"
                                    >
                                        <SelectValue placeholder="Semua status" />
                                    </SelectTrigger>
                                    <SelectContent>
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
                            </div>
                        }
                        toolbar={
                            selectedIds.length > 0 ? (
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="mr-1 text-sm text-muted-foreground">
                                        {selectedIds.length} dipilih
                                    </span>
                                    <Button
                                        type="button"
                                        size="sm"
                                        className="bg-emerald-600 text-white hover:bg-emerald-700"
                                        disabled={bulkProcessing}
                                        onClick={() =>
                                            updateSelectedStatus(true)
                                        }
                                    >
                                        <CircleCheck aria-hidden="true" />
                                        Aktifkan
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        className="bg-amber-500 text-white hover:bg-amber-600"
                                        disabled={bulkProcessing}
                                        onClick={() =>
                                            updateSelectedStatus(false)
                                        }
                                    >
                                        <CirclePause aria-hidden="true" />
                                        Nonaktifkan
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        className="bg-rose-600 text-white hover:bg-rose-700"
                                        disabled={bulkProcessing}
                                        onClick={() => setBulkDeleteOpen(true)}
                                    >
                                        <Trash2 aria-hidden="true" />
                                        Hapus
                                    </Button>
                                </div>
                            ) : null
                        }
                        emptyTitle={
                            hasActiveFilter
                                ? 'Anggota tidak ditemukan'
                                : 'Belum ada anggota'
                        }
                        emptyDescription={
                            hasActiveFilter
                                ? 'Ubah kata kunci atau filter untuk melihat hasil lain.'
                                : `Tambah anggota pertama untuk memberi akses ke ${appName}.`
                        }
                    />
                </Card>
            </div>

            <UserFormDialog
                open={isDialogOpen}
                onOpenChange={setIsDialogOpen}
                user={editingUser}
                roleOptions={roleOptions}
                organizationOptions={organizationOptions}
            />
            <Dialog
                open={resetUser !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        closeResetPassword();
                    }
                }}
            >
                <DialogContent className="gap-6 sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Reset password</DialogTitle>
                    </DialogHeader>
                    <form
                        className="grid gap-5"
                        onSubmit={(event) => {
                            event.preventDefault();

                            if (!resetUser) {
                                return;
                            }

                            resetForm.put(updatePassword.url(resetUser.id), {
                                preserveScroll: true,
                                onSuccess: closeResetPassword,
                            });
                        }}
                    >
                        <FormField
                            id="reset-password"
                            label="Password baru"
                            required
                            error={
                                resetForm.errors.password ===
                                'Konfirmasi password tidak sama.'
                                    ? undefined
                                    : resetForm.errors.password
                            }
                        >
                            <Input
                                type="password"
                                autoComplete="new-password"
                                placeholder="Masukkan password baru"
                                value={resetForm.data.password}
                                onChange={(event) =>
                                    resetForm.setData(
                                        'password',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            id="reset-password-confirmation"
                            label="Konfirmasi password"
                            required
                            error={
                                resetForm.errors.password_confirmation ??
                                (resetForm.errors.password ===
                                'Konfirmasi password tidak sama.'
                                    ? resetForm.errors.password
                                    : undefined)
                            }
                        >
                            <Input
                                type="password"
                                autoComplete="new-password"
                                placeholder="Ulangi password baru"
                                value={resetForm.data.password_confirmation}
                                onChange={(event) =>
                                    resetForm.setData(
                                        'password_confirmation',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={closeResetPassword}
                                disabled={resetForm.processing}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={resetForm.processing}
                            >
                                {resetForm.processing && <Spinner />}
                                Simpan password
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
            <Dialog open={bulkDeleteOpen} onOpenChange={setBulkDeleteOpen}>
                <DialogContent className="gap-6 sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Hapus anggota terpilih?</DialogTitle>
                        <DialogDescription>
                            {selectedIds.length} akun akan dihapus. Akun yang
                            masih terhubung ke proyek atau aktivitas tidak dapat
                            dihapus. Aksi ini tidak dapat dibatalkan.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={bulkProcessing}
                            onClick={() => setBulkDeleteOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            type="button"
                            className="bg-rose-600 text-white hover:bg-rose-700"
                            disabled={bulkProcessing}
                            onClick={deleteSelectedUsers}
                        >
                            {bulkProcessing && <Spinner />}
                            Hapus anggota
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
