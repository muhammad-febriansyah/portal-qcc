import { Head, useForm } from '@inertiajs/react';
import {
    Activity,
    Award,
    BadgeCheck,
    BookOpenCheck,
    Building2,
    ClipboardCheck,
    FileCheck2,
    FileText,
    Gauge,
    History,
    Info,
    KeyRound,
    Layers,
    LayoutGrid,
    Search,
    ShieldCheck,
    SlidersHorizontal,
    Users,
    UsersRound,
    type LucideIcon,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { roleBadgeVariant } from '@/lib/badge-variants';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { dashboard } from '@/routes';
import { index as rolesIndex } from '@/routes/admin/roles';
import RolePermissionController from '@/actions/App/Http/Controllers/Admin/RolePermissionController';
import InputError from '@/components/input-error';

const permissionLabels: Record<
    string,
    { menu: string; action: string; icon: LucideIcon }
> = {
    'audit.view': {
        menu: 'Audit Log',
        action: 'Lihat riwayat aktivitas',
        icon: History,
    },
    'batches.manage': { menu: 'Batch', action: 'Kelola batch', icon: Layers },
    'competencies.manage': {
        menu: 'Master Kompetensi',
        action: 'Kelola kompetensi',
        icon: BookOpenCheck,
    },
    'competencies.verify': {
        menu: 'Direktori Kompetensi',
        action: 'Verifikasi kompetensi anggota',
        icon: BadgeCheck,
    },
    'dashboard.admin': {
        menu: 'Dashboard',
        action: 'Lihat ringkasan admin',
        icon: LayoutGrid,
    },
    'evidence.upload': {
        menu: 'Evidence Repository',
        action: 'Unggah evidence proyek',
        icon: FileCheck2,
    },
    'evidence.view': {
        menu: 'Evidence Repository',
        action: 'Lihat evidence proyek',
        icon: FileCheck2,
    },
    'organizations.manage': {
        menu: 'Master Organisasi',
        action: 'Kelola organisasi',
        icon: Building2,
    },
    'people.report': {
        menu: 'Direktori Kompetensi',
        action: 'Lihat direktori anggota',
        icon: UsersRound,
    },
    'projects.approve': {
        menu: 'Inbox Review',
        action: 'Setujui proposal dan step QCC',
        icon: ClipboardCheck,
    },
    'projects.create': {
        menu: 'Proposal QCC',
        action: 'Buat proposal',
        icon: FileText,
    },
    'projects.review': {
        menu: 'Inbox Review',
        action: 'Tinjau proposal, step, dan bi-weekly',
        icon: ClipboardCheck,
    },
    'projects.submit': {
        menu: 'Proposal QCC',
        action: 'Ajukan proposal',
        icon: FileText,
    },
    'projects.update': {
        menu: 'Proposal QCC',
        action: 'Ubah proposal',
        icon: FileText,
    },
    'projects.view': {
        menu: 'Proposal QCC',
        action: 'Lihat proposal',
        icon: FileText,
    },
    'projects.viewAny': {
        menu: 'Monitoring Proyek',
        action: 'Lihat semua proyek',
        icon: Gauge,
    },
    'review-assignments.manage': {
        menu: 'Penugasan Reviewer',
        action: 'Kelola penugasan reviewer',
        icon: Users,
    },
    'roles.manage': {
        menu: 'Role & Permission',
        action: 'Atur role dan akses',
        icon: ShieldCheck,
    },
    'rubrics.manage': { menu: 'Rubrik', action: 'Kelola rubrik', icon: Award },
    'rubrics.publish': {
        menu: 'Rubrik',
        action: 'Terbitkan rubrik',
        icon: Award,
    },
    'scoring.approve': {
        menu: 'AI Scoring',
        action: 'Setujui hasil scoring',
        icon: Activity,
    },
    'scoring.review': {
        menu: 'AI Scoring',
        action: 'Tinjau hasil scoring',
        icon: Activity,
    },
    'scoring.run': {
        menu: 'AI Scoring',
        action: 'Jalankan scoring',
        icon: Activity,
    },
    'scoring.view': {
        menu: 'AI Scoring',
        action: 'Lihat hasil scoring',
        icon: Activity,
    },
    'system-settings.manage': {
        menu: 'Pengaturan Sistem',
        action: 'Ubah konfigurasi sistem',
        icon: SlidersHorizontal,
    },
    'system-settings.view': {
        menu: 'Pengaturan Sistem',
        action: 'Lihat konfigurasi sistem',
        icon: SlidersHorizontal,
    },
    'users.manage': {
        menu: 'Anggota',
        action: 'Kelola akun anggota',
        icon: Users,
    },
};

function getPermissionLabel(permission: string): {
    menu: string;
    action: string;
    icon: LucideIcon;
} {
    return (
        permissionLabels[permission] ?? {
            menu: permission
                .split(/[._-]/)
                .map(
                    (part) =>
                        part.charAt(0).toLocaleUpperCase() + part.slice(1),
                )
                .join(' '),
            action: 'Akses fitur',
            icon: KeyRound,
        }
    );
}

type RoleSummary = {
    id: number;
    name: string;
    permissions: string[];
};

type Props = {
    roles: RoleSummary[];
    permissionCatalog: string[];
};

export default function RolesIndex({ roles, permissionCatalog }: Props) {
    const [permissionSearch, setPermissionSearch] = useState('');

    const visiblePermissions = useMemo(() => {
        const search = permissionSearch.trim().toLocaleLowerCase();

        return permissionCatalog.filter((permission) => {
            const label = getPermissionLabel(permission);

            return `${label.menu} ${label.action}`
                .toLocaleLowerCase()
                .includes(search);
        });
    }, [permissionCatalog, permissionSearch]);

    return (
        <>
            <Head title="Role & Permission" />
            <div className="mx-auto w-full max-w-7xl p-4 sm:p-6 lg:p-8">
                <PageHeader
                    breadcrumbs={[
                        { title: 'Dashboard', href: dashboard().url },
                        { title: 'Role & Permission', href: rolesIndex().url },
                    ]}
                    title="Role & Permission"
                    description="Kelola akses lima role resmi dengan histori perubahan yang dapat diaudit."
                />

                <div className="grid gap-6">
                    <Alert className="border-primary/30 bg-primary/10 text-primary *:[svg]:text-primary">
                        <Info aria-hidden="true" />
                        <AlertTitle>Perubahan akses tercatat</AlertTitle>
                        <AlertDescription>
                            Setiap perubahan otomatis tercatat di Audit Log.
                        </AlertDescription>
                    </Alert>

                    <div className="grid gap-2">
                        <Label htmlFor="permission-search">Cari fitur</Label>
                        <div className="relative w-full sm:max-w-md">
                            <Search
                                aria-hidden="true"
                                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            />
                            <Input
                                id="permission-search"
                                value={permissionSearch}
                                onChange={(event) =>
                                    setPermissionSearch(event.target.value)
                                }
                                placeholder="Contoh: Anggota, Proposal QCC, setujui"
                                className="h-10 pl-9"
                            />
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Centang fitur langsung pada role. Pencarian hanya
                            menyaring tampilan.
                        </p>
                    </div>

                    <Tabs
                        defaultValue={
                            roles[0] ? String(roles[0].id) : undefined
                        }
                        className="gap-4"
                    >
                        <TabsList
                            aria-label="Pilih role"
                            className="grid h-auto w-full grid-cols-2 gap-2 rounded-xl bg-muted/50 p-2 sm:grid-cols-3 xl:grid-cols-5"
                        >
                            {roles.map((role) => (
                                <TabsTrigger
                                    key={role.id}
                                    value={String(role.id)}
                                    className="h-auto min-h-12 min-w-0 justify-start gap-3 rounded-lg border border-transparent px-3 py-2 whitespace-normal data-active:border-primary/25 data-active:bg-background data-active:text-foreground data-active:shadow-sm"
                                >
                                    <ShieldCheck
                                        aria-hidden="true"
                                        className="size-4 shrink-0 text-primary"
                                    />
                                    <span className="min-w-0 text-left leading-tight break-words">
                                        {role.name}
                                    </span>
                                    <span className="ml-auto shrink-0 rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground tabular-nums">
                                        {role.permissions.length}
                                    </span>
                                </TabsTrigger>
                            ))}
                        </TabsList>

                        {roles.map((role) => (
                            <TabsContent key={role.id} value={String(role.id)}>
                                <RoleAccessCard
                                    role={role}
                                    permissionCatalog={visiblePermissions}
                                    permissionCount={permissionCatalog.length}
                                />
                            </TabsContent>
                        ))}
                    </Tabs>
                </div>
            </div>
        </>
    );
}

function RoleAccessCard({
    role,
    permissionCatalog,
    permissionCount,
}: {
    role: RoleSummary;
    permissionCatalog: string[];
    permissionCount: number;
}) {
    const rolePermissions =
        role.name === 'Super Admin'
            ? role.permissions
            : role.permissions.filter(
                  (permission) => permission !== 'rubrics.publish',
              );
    const form = useForm<{ permissions: string[] }>({
        permissions: rolePermissions,
    });

    useEffect(() => {
        form.setData({ permissions: rolePermissions });
        form.setDefaults({ permissions: rolePermissions });
    }, [role.id, role.permissions]);

    const permissionGroups = useMemo(() => {
        const groups = new Map<
            string,
            { icon: LucideIcon; permissions: string[] }
        >();

        for (const permission of permissionCatalog) {
            if (
                permission === 'rubrics.publish' &&
                role.name !== 'Super Admin'
            ) {
                continue;
            }

            const label = getPermissionLabel(permission);
            const group = groups.get(label.menu) ?? {
                icon: label.icon,
                permissions: [],
            };

            group.permissions.push(permission);
            groups.set(label.menu, group);
        }

        return Array.from(groups.entries());
    }, [permissionCatalog, role.name]);

    function togglePermission(permission: string, checked: boolean): void {
        form.setData(
            'permissions',
            checked
                ? [...form.data.permissions, permission].sort()
                : form.data.permissions.filter((item) => item !== permission),
        );
    }

    function submitPermissions(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.put(RolePermissionController.update.url({ role: role.id }), {
            preserveScroll: true,
            onSuccess: () =>
                form.setDefaults('permissions', form.data.permissions),
        });
    }

    return (
        <form onSubmit={submitPermissions}>
            <Card className="gap-0 py-0">
                <CardHeader className="flex flex-col gap-3 border-b py-5 sm:flex-row sm:items-center sm:justify-between">
                    <div className="grid gap-1.5">
                        <CardTitle className="flex items-center gap-2 text-base">
                            <ShieldCheck
                                aria-hidden="true"
                                className="size-4 text-primary"
                            />
                            {role.name}
                            {role.name === 'Super Admin' && (
                                <Badge variant={roleBadgeVariant(role.name)}>
                                    Sistem
                                </Badge>
                            )}
                        </CardTitle>
                        <CardDescription>
                            {form.data.permissions.length} fitur aktif dari{' '}
                            {permissionCount}
                        </CardDescription>
                    </div>
                    <div className="flex items-center gap-3">
                        {form.isDirty && (
                            <span className="text-sm text-muted-foreground">
                                Ada perubahan belum disimpan
                            </span>
                        )}
                        <Button
                            type="submit"
                            className="h-10 px-4 text-sm"
                            disabled={form.processing || !form.isDirty}
                        >
                            {form.processing ? 'Menyimpan…' : 'Simpan Akses'}
                        </Button>
                    </div>
                </CardHeader>
                <CardContent className="grid gap-4 py-6">
                    <InputError message={form.errors.permissions} />
                    {permissionGroups.length > 0 ? (
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {permissionGroups.map(([menu, group]) => {
                                const MenuIcon = group.icon;
                                const headingId = `role-${role.id}-group-${menu
                                    .toLocaleLowerCase()
                                    .replace(/[^a-z0-9]+/g, '-')}`;
                                const activeCount = group.permissions.filter(
                                    (permission) =>
                                        form.data.permissions.includes(
                                            permission,
                                        ),
                                ).length;

                                return (
                                    <section
                                        key={menu}
                                        aria-labelledby={headingId}
                                        className="grid content-start gap-3 rounded-lg border bg-muted/20 p-4"
                                    >
                                        <h3
                                            id={headingId}
                                            className="flex items-center gap-2 text-sm font-semibold"
                                        >
                                            <span className="flex size-7 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                                                <MenuIcon
                                                    aria-hidden="true"
                                                    className="size-4"
                                                />
                                            </span>
                                            {menu}
                                            <span className="ml-auto text-xs font-medium text-muted-foreground tabular-nums">
                                                {activeCount}/
                                                {group.permissions.length}
                                            </span>
                                        </h3>
                                        <div className="grid gap-2">
                                            {group.permissions.map(
                                                (permission) => {
                                                    const label =
                                                        getPermissionLabel(
                                                            permission,
                                                        );
                                                    const isRequired =
                                                        role.name ===
                                                            'Super Admin' &&
                                                        permission ===
                                                            'roles.manage';
                                                    const checkboxId = `role-${role.id}-${permission.replaceAll('.', '-')}`;

                                                    return (
                                                        <FieldLabel
                                                            key={permission}
                                                            htmlFor={checkboxId}
                                                            className="flex min-h-11 w-full min-w-0 items-center gap-3 rounded-md border bg-background px-3 py-2 font-normal hover:bg-muted/40"
                                                        >
                                                            <Checkbox
                                                                id={checkboxId}
                                                                checked={form.data.permissions.includes(
                                                                    permission,
                                                                )}
                                                                disabled={
                                                                    form.processing ||
                                                                    isRequired
                                                                }
                                                                onCheckedChange={(
                                                                    checked,
                                                                ) =>
                                                                    togglePermission(
                                                                        permission,
                                                                        checked ===
                                                                            true,
                                                                    )
                                                                }
                                                            />
                                                            <span className="min-w-0 flex-1 text-sm leading-snug">
                                                                {label.action}
                                                            </span>
                                                            {isRequired && (
                                                                <span className="shrink-0 rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                                                                    Wajib
                                                                </span>
                                                            )}
                                                        </FieldLabel>
                                                    );
                                                },
                                            )}
                                        </div>
                                    </section>
                                );
                            })}
                        </div>
                    ) : (
                        <p className="rounded-md bg-muted px-3 py-4 text-sm text-muted-foreground">
                            Tidak ada fitur yang cocok dengan pencarian.
                        </p>
                    )}
                </CardContent>
            </Card>
        </form>
    );
}
