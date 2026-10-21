import { Head } from '@inertiajs/react';
import { Info, KeyRound, ShieldCheck } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { EmptyState } from '@/components/ui-states';
import { dashboard } from '@/routes';
import { index as rolesIndex } from '@/routes/admin/roles';

type RoleSummary = {
    name: string;
    permissions: string[];
};

type Props = {
    roles: RoleSummary[];
    permissionCatalog: string[];
};

export default function RolesIndex({ roles, permissionCatalog }: Props) {
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
                    description="Lihat lima role resmi dan permission yang sudah aktif."
                />

                <div className="space-y-6">
                    <Alert>
                        <Info aria-hidden="true" />
                        <AlertTitle>
                            Pengaturan grant menunggu persetujuan
                        </AlertTitle>
                        <AlertDescription>
                            Matriks akses masih menunggu keputusan QCC-002
                            (DEC-03, DEC-05 sampai DEC-09, dan DEC-14). Daftar
                            permission hanya menampilkan grant aktif; perubahan
                            hak akses belum dibuka.
                        </AlertDescription>
                    </Alert>

                    <section
                        aria-label="Role resmi"
                        className="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
                    >
                        {roles.map((role) => (
                            <Card key={role.name} className="gap-0">
                                <CardHeader className="border-b">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="space-y-1">
                                            <CardTitle className="flex items-center gap-2">
                                                <ShieldCheck
                                                    aria-hidden="true"
                                                    className="size-4 text-primary"
                                                />
                                                {role.name}
                                            </CardTitle>
                                            <CardDescription>
                                                {role.permissions.length}{' '}
                                                permission aktif
                                            </CardDescription>
                                        </div>
                                        {role.name === 'Super Admin' && (
                                            <Badge variant="secondary">
                                                Sistem
                                            </Badge>
                                        )}
                                    </div>
                                </CardHeader>
                                <CardContent className="pt-4">
                                    {role.permissions.length > 0 ? (
                                        <ul className="flex flex-wrap gap-2">
                                            {role.permissions.map(
                                                (permission) => (
                                                    <li key={permission}>
                                                        <Badge
                                                            variant="outline"
                                                            className="font-mono text-xs"
                                                        >
                                                            {permission}
                                                        </Badge>
                                                    </li>
                                                ),
                                            )}
                                        </ul>
                                    ) : (
                                        <EmptyState
                                            icon={KeyRound}
                                            title="Belum ada grant"
                                            description="Role ini belum memiliki permission yang aktif."
                                        />
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </section>

                    <Card className="gap-0">
                        <CardHeader className="border-b">
                            <CardTitle>Katalog permission terdaftar</CardTitle>
                            <CardDescription>
                                {permissionCatalog.length} permission sudah
                                terdaftar di aplikasi.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="pt-4">
                            {permissionCatalog.length > 0 ? (
                                <ul className="flex flex-wrap gap-2">
                                    {permissionCatalog.map((permission) => (
                                        <li key={permission}>
                                            <Badge
                                                variant="secondary"
                                                className="font-mono text-xs"
                                            >
                                                {permission}
                                            </Badge>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <EmptyState
                                    icon={KeyRound}
                                    title="Katalog masih kosong"
                                    description="Permission akan muncul setelah terdaftar di aplikasi."
                                />
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
