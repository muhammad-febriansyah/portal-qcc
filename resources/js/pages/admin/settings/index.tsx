import { Form, Head, useForm } from '@inertiajs/react';
import {
    Bell,
    Brain,
    FileLock2,
    Globe2,
    Image,
    LockKeyhole,
    Mail,
    Send,
    Settings2,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Combobox,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
} from '@/components/ui/combobox';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';
import {
    index as systemSettingsIndex,
    testEmail as testSystemEmail,
    update as updateSystemSettings,
} from '@/routes/admin/settings';
import { update as updateSystemPolicies } from '@/routes/admin/settings/policies';

type PolicySettings = {
    notifications: {
        inApp: boolean;
        email: boolean;
    };
    attachments: {
        storageDisk: string;
        storageOptions: string[];
        allowedTypes: string[];
        maxSizeMb: number;
        maxFiles: number;
        retentionDays: number;
    };
    ai: {
        enabled: boolean;
        model: string;
        modelOptions: ModelOption[];
        hasApiKey: boolean;
    };
    sso: {
        title: string;
        decision: string;
        description: string;
    };
};

type ModelOption = {
    id: string;
    name: string;
};

type PolicyForm = {
    notifications_in_app: boolean;
    notifications_email: boolean;
    attachments_storage_disk: string;
    attachments_allowed_types: string[];
    attachments_max_size_mb: number;
    attachments_max_files: number;
    attachments_retention_days: number;
    ai_enabled: boolean;
    ai_model: string;
    ai_api_key: string;
};

type Props = {
    general: {
        applicationName: string;
        keywords: string;
        description: string;
        language: string;
        timezone: string;
        logoUrl: string | null;
        faviconUrl: string | null;
    };
    policies: PolicySettings;
    canManage: boolean;
};

const attachmentTypes = [
    { value: 'pdf', label: 'PDF' },
    { value: 'docx', label: 'Word' },
    { value: 'xlsx', label: 'Excel' },
    { value: 'png', label: 'PNG' },
    { value: 'jpg', label: 'JPG' },
    { value: 'jpeg', label: 'JPEG' },
    { value: 'webp', label: 'WebP' },
];

export default function SystemSettingsIndex({
    general,
    policies,
    canManage,
}: Props) {
    const [apiKeyConfigured, setApiKeyConfigured] = useState(
        policies.ai.hasApiKey,
    );
    const policyForm = useForm<PolicyForm>({
        notifications_in_app: policies.notifications.inApp,
        notifications_email: policies.notifications.email,
        attachments_storage_disk: policies.attachments.storageDisk,
        attachments_allowed_types: policies.attachments.allowedTypes,
        attachments_max_size_mb: policies.attachments.maxSizeMb,
        attachments_max_files: policies.attachments.maxFiles,
        attachments_retention_days: policies.attachments.retentionDays,
        ai_enabled: policies.ai.enabled,
        ai_model: policies.ai.model,
        ai_api_key: '',
    });
    const testEmailForm = useForm({ email: '' });
    const [isTestEmailDialogOpen, setIsTestEmailDialogOpen] = useState(false);

    function submitTestEmail(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        testEmailForm.post(testSystemEmail.url(), {
            preserveScroll: true,
            onSuccess: () => {
                setIsTestEmailDialogOpen(false);
                testEmailForm.reset();
            },
        });
    }

    function submitPolicies(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        policyForm.patch(updateSystemPolicies.url(), {
            preserveScroll: true,
            onSuccess: () => {
                setApiKeyConfigured(
                    apiKeyConfigured || policyForm.data.ai_api_key.length > 0,
                );
                policyForm.reset('ai_api_key');
            },
        });
    }
    return (
        <>
            <Head title="Pengaturan Sistem" />
            <div className="mx-auto w-full max-w-[1440px] px-4 py-6 md:px-6 md:py-8">
                <PageHeader
                    breadcrumbs={[
                        { title: 'Dashboard', href: dashboard() },
                        {
                            title: 'Pengaturan Sistem',
                            href: systemSettingsIndex(),
                        },
                    ]}
                    title="Pengaturan Sistem"
                    description="Atur identitas website, notifikasi, lampiran, dan integrasi AI."
                />

                <Tabs defaultValue="general" className="gap-5">
                    <TabsList className="w-full sm:w-fit">
                        <TabsTrigger value="general">
                            <Settings2 aria-hidden="true" />
                            Identitas Website
                        </TabsTrigger>
                        <TabsTrigger value="policies">
                            <LockKeyhole aria-hidden="true" />
                            Kebijakan Sistem
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="general">
                        <Card>
                            <CardHeader>
                                <div className="flex items-center gap-2">
                                    <Settings2
                                        aria-hidden="true"
                                        className="size-4 text-primary"
                                    />
                                    <CardTitle>Konfigurasi umum</CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    {...updateSystemSettings.form()}
                                    options={{ preserveScroll: true }}
                                    className="grid gap-6"
                                >
                                    {({ errors, processing, progress }) => (
                                        <>
                                            <div className="grid gap-6 md:grid-cols-2">
                                                <FormField
                                                    id="website-name"
                                                    label="Nama Website"
                                                    required
                                                    error={errors.app_name}
                                                >
                                                    <Input
                                                        name="app_name"
                                                        defaultValue={
                                                            general.applicationName
                                                        }
                                                        placeholder="Contoh: Portal QCC SIMGROUP"
                                                        maxLength={100}
                                                        autoComplete="organization"
                                                    />
                                                </FormField>
                                                <FormField
                                                    id="website-keywords"
                                                    label="Keyword Website"
                                                    error={errors.keywords}
                                                >
                                                    <Input
                                                        name="keywords"
                                                        defaultValue={
                                                            general.keywords
                                                        }
                                                        placeholder="Contoh: QCC, SIMGROUP, continuous improvement"
                                                        maxLength={255}
                                                    />
                                                </FormField>
                                            </div>

                                            <FormField
                                                id="website-description"
                                                label="Deskripsi Website"
                                                error={errors.site_description}
                                            >
                                                <Textarea
                                                    name="site_description"
                                                    defaultValue={
                                                        general.description
                                                    }
                                                    placeholder="Jelaskan portal secara singkat untuk metadata website."
                                                    maxLength={255}
                                                    rows={3}
                                                />
                                            </FormField>

                                            <FormField
                                                id="application-timezone"
                                                label="Zona Waktu"
                                                required
                                                error={errors.timezone}
                                            >
                                                <Input
                                                    name="timezone"
                                                    defaultValue={
                                                        general.timezone
                                                    }
                                                    placeholder="Contoh: Asia/Jakarta"
                                                    list="available-timezones"
                                                    autoComplete="off"
                                                />
                                            </FormField>
                                            <datalist id="available-timezones">
                                                <option value="Asia/Jakarta" />
                                                <option value="Asia/Makassar" />
                                                <option value="Asia/Jayapura" />
                                                <option value="UTC" />
                                            </datalist>

                                            <div className="grid gap-6 lg:grid-cols-2">
                                                <FormField
                                                    id="website-logo"
                                                    label="Logo Website"
                                                    error={errors.logo}
                                                >
                                                    <Input
                                                        name="logo"
                                                        type="file"
                                                        accept="image/png,image/jpeg,image/webp"
                                                    />
                                                </FormField>
                                                <FormField
                                                    id="website-favicon"
                                                    label="Favicon"
                                                    error={errors.favicon}
                                                >
                                                    <Input
                                                        name="favicon"
                                                        type="file"
                                                        accept="image/png,image/x-icon"
                                                    />
                                                </FormField>
                                            </div>

                                            <div className="grid gap-4 rounded-lg border bg-muted/30 p-4 sm:grid-cols-2">
                                                <div className="flex min-w-0 items-center gap-3">
                                                    <div className="flex size-16 shrink-0 items-center justify-center rounded-md border bg-background p-2">
                                                        {general.logoUrl ? (
                                                            <img
                                                                src={
                                                                    general.logoUrl
                                                                }
                                                                alt="Preview logo website"
                                                                className="max-h-full max-w-full object-contain"
                                                            />
                                                        ) : (
                                                            <Image
                                                                aria-hidden="true"
                                                                className="size-6 text-muted-foreground"
                                                            />
                                                        )}
                                                    </div>
                                                    <div className="min-w-0">
                                                        <p className="font-medium">
                                                            Logo aktif
                                                        </p>
                                                        <p className="text-sm text-muted-foreground">
                                                            PNG, JPG, atau WebP
                                                            · maks. 2 MB
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="flex min-w-0 items-center gap-3">
                                                    <div className="flex size-16 shrink-0 items-center justify-center rounded-md border bg-background p-2">
                                                        {general.faviconUrl ? (
                                                            <img
                                                                src={
                                                                    general.faviconUrl
                                                                }
                                                                alt="Preview favicon website"
                                                                className="size-8 object-contain"
                                                            />
                                                        ) : (
                                                            <Globe2
                                                                aria-hidden="true"
                                                                className="size-6 text-muted-foreground"
                                                            />
                                                        )}
                                                    </div>
                                                    <div className="min-w-0">
                                                        <p className="font-medium">
                                                            Favicon aktif
                                                        </p>
                                                        <p className="text-sm text-muted-foreground">
                                                            ICO atau PNG · maks.
                                                            512 KB
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>

                                            <div className="flex flex-wrap items-center justify-between gap-4 border-t pt-4">
                                                <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                                    <Globe2
                                                        aria-hidden="true"
                                                        className="size-4"
                                                    />
                                                    Bahasa: {general.language}
                                                </p>
                                                <div className="flex items-center gap-3">
                                                    {progress && (
                                                        <span className="text-sm text-muted-foreground">
                                                            Upload{' '}
                                                            {
                                                                progress.percentage
                                                            }
                                                            %
                                                        </span>
                                                    )}
                                                    <Button
                                                        type="submit"
                                                        disabled={processing}
                                                    >
                                                        {processing && (
                                                            <Spinner />
                                                        )}
                                                        Simpan Perubahan
                                                    </Button>
                                                </div>
                                            </div>
                                        </>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="policies">
                        <form onSubmit={submitPolicies} className="grid gap-5">
                            <p className="text-sm text-muted-foreground">
                                Nilai awal adalah baseline lokal dan bisa
                                disesuaikan. Keputusan resmi DEC-16 sampai
                                DEC-19 tetap perlu dicatat sebelum production.
                            </p>
                            <Card>
                                <CardHeader>
                                    <div className="flex items-center gap-2">
                                        <Bell
                                            aria-hidden="true"
                                            className="size-4 text-primary"
                                        />
                                        <CardTitle>Notifikasi</CardTitle>
                                    </div>
                                    <CardDescription>
                                        Pilih kanal yang akan digunakan saat
                                        fitur notifikasi aktif.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-4 sm:grid-cols-2">
                                    <div className="flex items-center justify-between gap-4 rounded-lg border p-4">
                                        <div className="flex items-start gap-3">
                                            <Bell
                                                aria-hidden="true"
                                                className="mt-0.5 size-4 text-muted-foreground"
                                            />
                                            <div>
                                                <p className="text-sm font-medium">
                                                    Notifikasi dalam aplikasi
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    Tampilkan pembaruan di
                                                    portal.
                                                </p>
                                            </div>
                                        </div>
                                        <Switch
                                            checked={
                                                policyForm.data
                                                    .notifications_in_app
                                            }
                                            onCheckedChange={(checked) =>
                                                policyForm.setData(
                                                    'notifications_in_app',
                                                    checked,
                                                )
                                            }
                                            aria-label="Aktifkan notifikasi dalam aplikasi"
                                        />
                                    </div>
                                    <div className="flex flex-col gap-4 rounded-lg border p-4">
                                        <div className="flex items-start justify-between gap-4">
                                            <div className="flex items-start gap-3">
                                                <Mail
                                                    aria-hidden="true"
                                                    className="mt-0.5 size-4 text-muted-foreground"
                                                />
                                                <div>
                                                    <p className="text-sm font-medium">
                                                        Email kantor
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        Kirim pemberitahuan ke
                                                        email akun.
                                                    </p>
                                                </div>
                                            </div>
                                            <Switch
                                                checked={
                                                    policyForm.data
                                                        .notifications_email
                                                }
                                                onCheckedChange={(checked) =>
                                                    policyForm.setData(
                                                        'notifications_email',
                                                        checked,
                                                    )
                                                }
                                                aria-label="Aktifkan notifikasi email"
                                            />
                                        </div>
                                        {canManage && (
                                            <Button
                                                type="button"
                                                variant="secondary"
                                                className="w-fit"
                                                onClick={() =>
                                                    setIsTestEmailDialogOpen(
                                                        true,
                                                    )
                                                }
                                            >
                                                <Send aria-hidden="true" />
                                                Test email SMTP
                                            </Button>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <div className="flex items-center gap-2">
                                        <FileLock2
                                            aria-hidden="true"
                                            className="size-4 text-primary"
                                        />
                                        <CardTitle>
                                            Lampiran dan penyimpanan
                                        </CardTitle>
                                    </div>
                                    <CardDescription>
                                        Atur default unggahan lampiran QCC.
                                        Modul lampiran proyek belum aktif; masa
                                        retensi juga belum menghapus file
                                        otomatis.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-6">
                                    <div className="grid gap-6 md:grid-cols-2">
                                        <FormField
                                            id="attachments-storage-disk"
                                            label="Lokasi penyimpanan"
                                            error={
                                                policyForm.errors
                                                    .attachments_storage_disk
                                            }
                                        >
                                            <select
                                                id="attachments-storage-disk"
                                                value={
                                                    policyForm.data
                                                        .attachments_storage_disk
                                                }
                                                onChange={(event) =>
                                                    policyForm.setData(
                                                        'attachments_storage_disk',
                                                        event.target.value,
                                                    )
                                                }
                                                className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                                            >
                                                {policies.attachments.storageOptions.map(
                                                    (disk) => (
                                                        <option
                                                            key={disk}
                                                            value={disk}
                                                        >
                                                            {disk === 's3'
                                                                ? 'Amazon S3'
                                                                : 'Penyimpanan lokal'}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                        </FormField>
                                        <FormField
                                            id="attachments-allowed-types"
                                            label="Tipe file yang diizinkan"
                                            error={
                                                policyForm.errors
                                                    .attachments_allowed_types
                                            }
                                        >
                                            <div className="flex min-h-10 flex-wrap items-center gap-x-5 gap-y-2 rounded-md border border-input px-3 py-2">
                                                {attachmentTypes.map((type) => (
                                                    <label
                                                        key={type.value}
                                                        className="flex items-center gap-2 text-sm"
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            checked={policyForm.data.attachments_allowed_types.includes(
                                                                type.value,
                                                            )}
                                                            onChange={(
                                                                event,
                                                            ) => {
                                                                const selectedTypes =
                                                                    new Set(
                                                                        policyForm
                                                                            .data
                                                                            .attachments_allowed_types,
                                                                    );
                                                                if (
                                                                    event.target
                                                                        .checked
                                                                ) {
                                                                    selectedTypes.add(
                                                                        type.value,
                                                                    );
                                                                } else {
                                                                    selectedTypes.delete(
                                                                        type.value,
                                                                    );
                                                                }
                                                                policyForm.setData(
                                                                    'attachments_allowed_types',
                                                                    [
                                                                        ...selectedTypes,
                                                                    ],
                                                                );
                                                            }}
                                                            className="size-4 accent-primary"
                                                        />
                                                        {type.label}
                                                    </label>
                                                ))}
                                            </div>
                                        </FormField>
                                        <FormField
                                            id="attachments-max-size"
                                            label="Ukuran maksimum per file (MB)"
                                            hint="Maksimum yang dapat disimpan: 50 MB."
                                            error={
                                                policyForm.errors
                                                    .attachments_max_size_mb
                                            }
                                        >
                                            <Input
                                                id="attachments-max-size"
                                                type="number"
                                                min={1}
                                                max={50}
                                                value={
                                                    policyForm.data
                                                        .attachments_max_size_mb
                                                }
                                                onChange={(event) =>
                                                    policyForm.setData(
                                                        'attachments_max_size_mb',
                                                        Number(
                                                            event.target.value,
                                                        ),
                                                    )
                                                }
                                            />
                                        </FormField>
                                        <FormField
                                            id="attachments-max-files"
                                            label="Maksimum file per unggahan"
                                            error={
                                                policyForm.errors
                                                    .attachments_max_files
                                            }
                                        >
                                            <Input
                                                id="attachments-max-files"
                                                type="number"
                                                min={1}
                                                max={10}
                                                value={
                                                    policyForm.data
                                                        .attachments_max_files
                                                }
                                                onChange={(event) =>
                                                    policyForm.setData(
                                                        'attachments_max_files',
                                                        Number(
                                                            event.target.value,
                                                        ),
                                                    )
                                                }
                                            />
                                        </FormField>
                                        <FormField
                                            id="attachments-retention"
                                            label="Masa retensi (hari)"
                                            hint="Mengatur kebijakan; pembersihan otomatis belum berjalan."
                                            error={
                                                policyForm.errors
                                                    .attachments_retention_days
                                            }
                                        >
                                            <Input
                                                id="attachments-retention"
                                                type="number"
                                                min={1}
                                                max={3650}
                                                value={
                                                    policyForm.data
                                                        .attachments_retention_days
                                                }
                                                onChange={(event) =>
                                                    policyForm.setData(
                                                        'attachments_retention_days',
                                                        Number(
                                                            event.target.value,
                                                        ),
                                                    )
                                                }
                                            />
                                        </FormField>
                                    </div>
                                    {policyForm.errors
                                        .attachments_allowed_types && (
                                        <p
                                            role="alert"
                                            className="text-sm text-destructive"
                                        >
                                            {
                                                policyForm.errors
                                                    .attachments_allowed_types
                                            }
                                        </p>
                                    )}
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <div className="flex items-center gap-2">
                                        <Brain
                                            aria-hidden="true"
                                            className="size-4 text-primary"
                                        />
                                        <CardTitle>Integrasi AI</CardTitle>
                                    </div>
                                    <CardDescription>
                                        API key disimpan terenkripsi di server.
                                        Permintaan scoring memakai store=false;
                                        mengaktifkan AI mengirim evidence proyek
                                        ke penyedia.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="grid gap-6">
                                    <div className="flex items-center justify-between gap-4 rounded-lg border p-4">
                                        <div>
                                            <p className="text-sm font-medium">
                                                Aktifkan scoring AI
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                Nonaktifkan untuk mencegah
                                                pengiriman data baru.
                                            </p>
                                        </div>
                                        <Switch
                                            checked={policyForm.data.ai_enabled}
                                            onCheckedChange={(checked) =>
                                                policyForm.setData(
                                                    'ai_enabled',
                                                    checked,
                                                )
                                            }
                                            aria-label="Aktifkan integrasi AI"
                                        />
                                    </div>
                                    <div className="grid gap-6 md:grid-cols-2">
                                        <FormField
                                            id="ai-model"
                                            label="Model OpenAI"
                                            required={
                                                policyForm.data.ai_enabled
                                            }
                                            hint="Pilih model yang mendukung structured output. Cari berdasarkan nama atau ID."
                                            error={policyForm.errors.ai_model}
                                        >
                                            <Combobox
                                                items={policies.ai.modelOptions}
                                                value={
                                                    policies.ai.modelOptions.find(
                                                        (model) =>
                                                            model.id ===
                                                            policyForm.data
                                                                .ai_model,
                                                    ) ?? null
                                                }
                                                onValueChange={(
                                                    model: ModelOption | null,
                                                ) =>
                                                    policyForm.setData(
                                                        'ai_model',
                                                        model?.id ?? '',
                                                    )
                                                }
                                                itemToStringLabel={(
                                                    model: ModelOption,
                                                ) =>
                                                    `${model.name} · ${model.id}`
                                                }
                                                isItemEqualToValue={(
                                                    a: ModelOption,
                                                    b: ModelOption,
                                                ) => a.id === b.id}
                                            >
                                                <ComboboxInput
                                                    id="ai-model"
                                                    placeholder="Pilih atau cari model OpenAI"
                                                    aria-label="Pilih model OpenAI"
                                                    aria-invalid={Boolean(
                                                        policyForm.errors
                                                            .ai_model,
                                                    )}
                                                    showClear
                                                />
                                                <ComboboxContent>
                                                    <ComboboxEmpty>
                                                        Model tidak ditemukan.
                                                    </ComboboxEmpty>
                                                    <ComboboxList>
                                                        {(
                                                            model: ModelOption,
                                                        ) => (
                                                            <ComboboxItem
                                                                key={model.id}
                                                                value={model}
                                                            >
                                                                <span className="flex min-w-0 flex-col">
                                                                    <span>
                                                                        {
                                                                            model.name
                                                                        }
                                                                    </span>
                                                                    <span className="text-xs text-muted-foreground">
                                                                        {
                                                                            model.id
                                                                        }
                                                                    </span>
                                                                </span>
                                                            </ComboboxItem>
                                                        )}
                                                    </ComboboxList>
                                                </ComboboxContent>
                                            </Combobox>
                                        </FormField>
                                        <FormField
                                            id="ai-api-key"
                                            label="API key"
                                            hint={
                                                apiKeyConfigured
                                                    ? 'Key sudah tersimpan. Isi hanya untuk mengganti.'
                                                    : 'Key tidak pernah dikirim ke browser setelah disimpan.'
                                            }
                                            error={policyForm.errors.ai_api_key}
                                        >
                                            <Input
                                                id="ai-api-key"
                                                type="password"
                                                autoComplete="new-password"
                                                value={
                                                    policyForm.data.ai_api_key
                                                }
                                                onChange={(event) =>
                                                    policyForm.setData(
                                                        'ai_api_key',
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder={
                                                    apiKeyConfigured
                                                        ? '••••••••••••••••'
                                                        : 'Masukkan API key'
                                                }
                                            />
                                        </FormField>
                                    </div>
                                    {policyForm.errors.ai_api_key && (
                                        <p
                                            role="alert"
                                            className="text-sm text-destructive"
                                        >
                                            {policyForm.errors.ai_api_key}
                                        </p>
                                    )}
                                </CardContent>
                            </Card>

                            <div className="flex justify-end border-t pt-4">
                                <Button
                                    type="submit"
                                    disabled={policyForm.processing}
                                >
                                    {policyForm.processing && <Spinner />}
                                    Simpan Kebijakan
                                </Button>
                            </div>
                        </form>
                    </TabsContent>
                </Tabs>
            </div>
            <Dialog
                open={isTestEmailDialogOpen}
                onOpenChange={setIsTestEmailDialogOpen}
            >
                <DialogContent className="gap-6 sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Kirim test email SMTP</DialogTitle>
                        <p className="text-sm text-muted-foreground">
                            Email dikirim memakai konfigurasi SMTP aplikasi.
                        </p>
                    </DialogHeader>
                    <form onSubmit={submitTestEmail} className="grid gap-6">
                        <FormField
                            id="smtp-test-email"
                            label="Email tujuan"
                            required
                            error={testEmailForm.errors.email}
                        >
                            <Input
                                id="smtp-test-email"
                                type="email"
                                autoComplete="email"
                                placeholder="nama@perusahaan.com"
                                value={testEmailForm.data.email}
                                onChange={(event) =>
                                    testEmailForm.setData(
                                        'email',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={testEmailForm.processing}
                                onClick={() => setIsTestEmailDialogOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={testEmailForm.processing}
                            >
                                {testEmailForm.processing && <Spinner />}
                                Kirim email uji
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
