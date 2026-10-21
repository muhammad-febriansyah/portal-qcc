import { Form, Head, usePage } from '@inertiajs/react';
import { Building2, Fingerprint, Mail } from 'lucide-react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { AvatarUploader } from '@/components/avatar-uploader';
import { FormField } from '@/components/form-field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
    profile: {
        sim_id: string | null;
        organization_name: string | null;
    };
};

function SummaryRow({
    icon: Icon,
    label,
    value,
}: {
    icon: typeof Mail;
    label: string;
    value: string;
}) {
    return (
        <div className="flex items-start gap-3">
            <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                <Icon className="size-4" aria-hidden="true" />
            </span>
            <div className="grid min-w-0 gap-0.5">
                <span className="text-xs text-muted-foreground">{label}</span>
                <span className="text-sm font-medium break-words">{value}</span>
            </div>
        </div>
    );
}

export default function Profile() {
    const { auth, profile } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Profil Saya" />

            <div className="grid gap-6 lg:grid-cols-3">
                <Card className="h-fit gap-6 py-6 lg:col-span-1">
                    <CardContent className="grid gap-6">
                        <AvatarUploader
                            name={auth.user.name}
                            avatarUrl={auth.user.avatar ?? null}
                        />

                        <div className="grid gap-2 text-center">
                            <p className="text-lg font-semibold">
                                {auth.user.name}
                            </p>
                            <div className="flex flex-wrap justify-center gap-1.5">
                                {auth.roles.map((role) => (
                                    <Badge key={role} variant="outline">
                                        {role}
                                    </Badge>
                                ))}
                            </div>
                        </div>

                        <div className="grid gap-4 border-t border-border/70 pt-6">
                            <SummaryRow
                                icon={Mail}
                                label="Email"
                                value={auth.user.email}
                            />
                            <SummaryRow
                                icon={Fingerprint}
                                label="SIM ID"
                                value={profile.sim_id ?? 'Belum diisi'}
                            />
                            <SummaryRow
                                icon={Building2}
                                label="LOB / Divisi"
                                value={
                                    profile.organization_name ??
                                    'Belum ditetapkan'
                                }
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card className="h-fit gap-6 py-6 lg:col-span-2">
                    <CardHeader>
                        <CardTitle className="text-base font-semibold">
                            Informasi Profil
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...ProfileController.update.form()}
                            options={{ preserveScroll: true }}
                            className="grid gap-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <FormField
                                        id="name"
                                        label="Nama Lengkap"
                                        required
                                        error={errors.name}
                                    >
                                        <Input
                                            defaultValue={auth.user.name}
                                            name="name"
                                            autoComplete="name"
                                            placeholder="Masukkan nama lengkap"
                                        />
                                    </FormField>

                                    <FormField
                                        id="email"
                                        label="Email Kerja"
                                        required
                                        error={errors.email}
                                    >
                                        <Input
                                            type="email"
                                            defaultValue={auth.user.email}
                                            name="email"
                                            autoComplete="username"
                                            placeholder="nama@perusahaan.co.id"
                                        />
                                    </FormField>

                                    <div className="grid gap-6 sm:grid-cols-2">
                                        <FormField id="sim-id" label="SIM ID">
                                            <Input
                                                value={
                                                    profile.sim_id ??
                                                    'Belum diisi'
                                                }
                                                readOnly
                                                disabled
                                            />
                                        </FormField>
                                        <FormField
                                            id="organization"
                                            label="LOB / Divisi"
                                        >
                                            <Input
                                                value={
                                                    profile.organization_name ??
                                                    'Belum ditetapkan'
                                                }
                                                readOnly
                                                disabled
                                            />
                                        </FormField>
                                    </div>

                                    <div>
                                        <Button
                                            type="submit"
                                            className="h-10 px-4 text-sm"
                                            disabled={processing}
                                            data-test="update-profile-button"
                                        >
                                            Simpan Perubahan
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
