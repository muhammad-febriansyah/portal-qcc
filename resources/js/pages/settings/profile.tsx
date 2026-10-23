import { Form, Head, usePage } from '@inertiajs/react';
import { Building2, Fingerprint, Mail, UserRoundCheck } from 'lucide-react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { AvatarUploader } from '@/components/avatar-uploader';
import { ChangePasswordForm } from '@/components/change-password-form';
import { FormField } from '@/components/form-field';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { roleBadgeVariant } from '@/lib/badge-variants';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import InputError from '@/components/input-error';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
    profileComplete: boolean;
    profile: {
        sim_id: string | null;
        position: string | null;
        work_location: string | null;
        organization_id: number | null;
        organization_name: string | null;
        availability_status: 'AVAILABLE' | 'UNAVAILABLE' | null;
        qcc_experience_batches: string[];
    };
    organizations: Array<{ id: number; name: string }>;
    passwordRules: string;
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
    const { auth, profile, profileComplete, organizations, passwordRules } =
        usePage<PageProps>().props;

    return (
        <>
            <Head title="Profil Saya" />

            {!profileComplete && (
                <Alert className="mb-6">
                    <UserRoundCheck aria-hidden="true" />
                    <AlertTitle>Lengkapi profil untuk mulai</AlertTitle>
                    <AlertDescription>
                        Isi LOB / Divisi, jabatan, lokasi kerja, dan
                        ketersediaan. Menu lain terbuka setelah profil lengkap.
                    </AlertDescription>
                </Alert>
            )}

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
                                    <Badge
                                        key={role}
                                        variant={roleBadgeVariant(role)}
                                    >
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

                <div className="grid h-fit gap-6 lg:col-span-2">
                    <Card className="h-fit gap-6 py-6">
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
                                            <FormField
                                                id="position"
                                                label="Jabatan"
                                                required
                                                error={errors.position}
                                            >
                                                <Input
                                                    defaultValue={
                                                        profile.position ?? ''
                                                    }
                                                    name="position"
                                                    autoComplete="organization-title"
                                                    placeholder="Contoh: Staff Operasional"
                                                    required
                                                />
                                            </FormField>
                                            <FormField
                                                id="work_location"
                                                label="Lokasi Kerja"
                                                required
                                                error={errors.work_location}
                                            >
                                                <Input
                                                    defaultValue={
                                                        profile.work_location ??
                                                        ''
                                                    }
                                                    name="work_location"
                                                    placeholder="Contoh: Jakarta"
                                                    required
                                                />
                                            </FormField>
                                        </div>

                                        <FormField
                                            id="availability_status"
                                            label="Ketersediaan"
                                            required
                                            error={errors.availability_status}
                                        >
                                            <Select
                                                name="availability_status"
                                                defaultValue={
                                                    profile.availability_status ??
                                                    undefined
                                                }
                                                required
                                            >
                                                <SelectTrigger className="h-10 w-full">
                                                    <SelectValue placeholder="Pilih ketersediaan" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectGroup>
                                                        <SelectItem value="AVAILABLE">
                                                            Tersedia untuk
                                                            proyek QCC
                                                        </SelectItem>
                                                        <SelectItem value="UNAVAILABLE">
                                                            Belum tersedia untuk
                                                            proyek QCC
                                                        </SelectItem>
                                                    </SelectGroup>
                                                </SelectContent>
                                            </Select>
                                        </FormField>

                                        <fieldset className="grid gap-2.5">
                                            <legend className="text-sm font-medium">
                                                Experience QCC
                                            </legend>
                                            <p className="text-xs text-muted-foreground">
                                                Pilih semua batch QCC yang
                                                pernah Anda ikuti.
                                            </p>
                                            <div className="grid gap-3 sm:grid-cols-3">
                                                {[
                                                    'QCC Batch 1',
                                                    'QCC Batch 2',
                                                    'QCC Batch 3',
                                                ].map((batch, index) => (
                                                    <label
                                                        key={batch}
                                                        htmlFor={`qcc-experience-${index + 1}`}
                                                        className="flex cursor-pointer items-center gap-2.5 rounded-lg border border-border bg-muted/30 px-3 py-2.5 text-sm font-medium transition-colors hover:bg-muted/60"
                                                    >
                                                        <input
                                                            id={`qcc-experience-${index + 1}`}
                                                            type="checkbox"
                                                            name="qcc_experience_batches[]"
                                                            value={batch}
                                                            defaultChecked={profile.qcc_experience_batches.includes(
                                                                batch,
                                                            )}
                                                            className="size-4 accent-primary"
                                                        />
                                                        {batch}
                                                    </label>
                                                ))}
                                            </div>
                                            <InputError
                                                message={
                                                    errors.qcc_experience_batches ??
                                                    errors[
                                                        'qcc_experience_batches.0'
                                                    ] ??
                                                    errors[
                                                        'qcc_experience_batches.1'
                                                    ] ??
                                                    errors[
                                                        'qcc_experience_batches.2'
                                                    ]
                                                }
                                            />
                                        </fieldset>

                                        <div className="grid gap-6 sm:grid-cols-2">
                                            <FormField
                                                id="sim-id"
                                                label="SIM ID"
                                            >
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
                                                id="organization_id"
                                                label="LOB / Divisi"
                                                required
                                                error={errors.organization_id}
                                            >
                                                <Select
                                                    name="organization_id"
                                                    defaultValue={
                                                        profile.organization_id?.toString() ??
                                                        undefined
                                                    }
                                                    required
                                                >
                                                    <SelectTrigger className="h-10 w-full">
                                                        <SelectValue placeholder="Pilih LOB / Divisi" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectGroup>
                                                            {organizations.map(
                                                                (
                                                                    organization,
                                                                ) => (
                                                                    <SelectItem
                                                                        key={
                                                                            organization.id
                                                                        }
                                                                        value={organization.id.toString()}
                                                                    >
                                                                        {
                                                                            organization.name
                                                                        }
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectGroup>
                                                    </SelectContent>
                                                </Select>
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
                    <ChangePasswordForm passwordRules={passwordRules} />
                </div>
            </div>
        </>
    );
}
