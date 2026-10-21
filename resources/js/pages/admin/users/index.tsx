import { Head, router } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import { CircleCheck, CirclePause, KeyRound, PencilLine, Plus, Power } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { CredentialDialog } from '@/components/credential-dialog';
import type { Credential } from '@/components/credential-dialog';
import { DataTable } from '@/components/data-table';
import type {
    DataTableColumnDef,
    DataTableSort,
} from '@/components/data-table';
import { DataTableRowActions } from '@/components/data-table-row-actions';
import { PageHeader } from '@/components/page-header';
import { UserFormDialog } from '@/components/user-form-dialog';
import type {
    OrganizationOption,
    UserFormValues,
} from '@/components/user-form-dialog';
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
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index } from '@/routes/admin/users';
import { update as updatePassword } from '@/routes/admin/users/password';
import { update as updateStatus } from '@/routes/admin/users/status';

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
        <Badge
            variant="secondary"
            className={cn(
                'gap-1',
                isActive
                    ? 'bg-[#16834a]/10 text-[#16834a]'
                    : 'bg-muted text-muted-foreground',
            )}
        >
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
    const [searchValue, setSearchValue] = useState(filters.search);
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [editingUser, setEditingUser] = useState<UserRow | null>(null);
    const [credential, setCredential] = useState<Credential | null>(null);

    function visit(nextQuery: UserQuery, resetPage = true): void {
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

    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;

            if (flash?.credential) {
                setCredential(flash.credential as Credential);
            }
        });
    }, []);

    function openCreateDialog(): void {
        setEditingUser(null);
        setIsDialogOpen(true);
    }

    function openEditDialog(user: UserRow): void {
        setEditingUser(user);
        setIsDialogOpen(true);
    }

    function resetPassword(user: UserRow): void {
        router.put(updatePassword.url(user.id), {}, { preserveScroll: true });
    }

    function toggleStatus(user: UserRow): void {
        router.patch(
            updateStatus.url(user.id),
            { is_active: !user.is_active },
            { preserveScroll: true },
        );
    }

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
                        <Badge variant="outline">{row.original.role}</Badge>
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
                                        requiresConfirmation: true,
                                        confirmTitle: 'Reset password?',
                                        confirmDescription: `Password lama ${user.name} akan diganti dengan password sementara baru.`,
                                        confirmLabel: 'Reset',
                                        onSelect: () => resetPassword(user),
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
                                            ? `${user.name} tidak akan bisa masuk ke Portal QCC sampai akunnya diaktifkan kembali.`
                                            : `${user.name} dapat masuk kembali ke Portal QCC.`,
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
        [],
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
                    description="Kelola akun, organisasi, dan role pengguna Portal QCC."
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
                        emptyTitle={
                            hasActiveFilter
                                ? 'Anggota tidak ditemukan'
                                : 'Belum ada anggota'
                        }
                        emptyDescription={
                            hasActiveFilter
                                ? 'Ubah kata kunci atau filter untuk melihat hasil lain.'
                                : 'Tambah anggota pertama untuk memberi akses ke Portal QCC.'
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
            <CredentialDialog
                credential={credential}
                onClose={() => setCredential(null)}
            />
        </>
    );
}
