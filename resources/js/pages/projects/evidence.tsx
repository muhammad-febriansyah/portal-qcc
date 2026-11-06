import { Head, router, useForm, usePoll } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import {
    Download,
    ExternalLink,
    Eye,
    FileArchive,
    FileImage,
    FileText,
    Link2,
    ShieldCheck,
    UploadCloud,
} from 'lucide-react';
import { useEffect, useMemo, useState, type FormEvent } from 'react';
import { FileUploader } from '@/components/file-uploader';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { DataTable, type DataTableColumnDef } from '@/components/data-table';
import { DataTableRowActions } from '@/components/data-table-row-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { UploadItem } from '@/components/file-uploader';
import { dashboard } from '@/routes';
import { index as proposalIndex } from '@/routes/proposals';
import {
    index as evidenceIndex,
    store as evidenceStore,
} from '@/routes/projects/evidence';
import { randomId } from '@/lib/utils';

type StepOption = { id: number; number: number; name: string };
type EvidenceTargetType =
    | 'PROJECT'
    | 'QCC_STEP'
    | 'BIWEEKLY_REPORT'
    | 'MENTORING_REPORT';
type EvidenceSourceType = 'FILE' | 'LINK' | 'AI_CHAT_LINK';
type ReportOption = {
    id: number;
    type: 'BIWEEKLY_REPORT' | 'MENTORING_REPORT';
    label: string;
};
type EvidenceItem = {
    id: number;
    title: string;
    category: string;
    description: string | null;
    source_type: EvidenceSourceType;
    url: string | null;
    summary: string | null;
    original_name: string | null;
    mime_type: string | null;
    size_bytes: number | null;
    version_number: number;
    scan_status: string;
    processing_status: string;
    extraction_status: string;
    uploader: string | null;
    uploaded_at: string | null;
    targets: string[];
    preview_url: string | null;
    thumbnail_url: string | null;
    download_url: string | null;
};
type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};
type Props = {
    project: { id: number; code: string; title: string };
    evidence: Paginator<EvidenceItem>;
    steps: StepOption[];
    reports: ReportOption[];
    selected_step: number | null;
    can_upload: boolean;
    search: string;
    max_file_size_bytes: number;
    accept: string;
};
type EvidenceForm = {
    title: string;
    category: string;
    description: string;
    target_type: EvidenceTargetType;
    target_id: string;
    source_type: EvidenceSourceType;
    url: string;
    summary: string;
    file: File | null;
};

const categories = [
    ['PROPOSAL', 'Dokumen proposal'],
    ['QCC_STEP', '8 Step QCC'],
    ['BIWEEKLY', 'Bi-weekly'],
    ['MENTORING', 'Mentoring'],
    ['ACTION', 'Action item'],
    ['OTHER', 'Lainnya'],
] as const;
const sourceTypes: [EvidenceSourceType, string][] = [
    ['FILE', 'File'],
    ['LINK', 'Tautan materi'],
    ['AI_CHAT_LINK', 'Tautan percakapan AI'],
];

const scanLabels: Record<string, string> = {
    PENDING: 'Menunggu pemeriksaan',
    PROCESSING: 'Memeriksa file',
    PASSED: 'Format lolos',
    REJECTED: 'Format ditolak',
    FAILED: 'Pemeriksaan gagal',
    NOT_REQUIRED: 'Tidak perlu pemeriksaan file',
};
const scanVariants: Record<
    string,
    'secondary' | 'info' | 'success' | 'warning' | 'destructive'
> = {
    PENDING: 'secondary',
    PROCESSING: 'info',
    PASSED: 'success',
    REJECTED: 'destructive',
    FAILED: 'warning',
    NOT_REQUIRED: 'secondary',
};
const scanBadgeClasses: Record<string, string> = {
    PENDING: 'border-transparent bg-slate-100 text-slate-700',
    PROCESSING: 'border-transparent bg-cyan-100 text-cyan-800',
    PASSED: 'border-transparent bg-emerald-100 text-emerald-800',
    REJECTED: 'border-transparent bg-rose-100 text-rose-800',
    FAILED: 'border-transparent bg-amber-100 text-amber-900',
    NOT_REQUIRED: 'border-transparent bg-slate-100 text-slate-700',
};

function formatBytes(bytes: number): string {
    if (bytes < 1024 * 1024) return `${Math.ceil(bytes / 1024)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function fileIcon(mimeType: string) {
    if (mimeType.startsWith('image/')) return FileImage;
    if (mimeType === 'application/pdf') return FileText;
    return FileArchive;
}

function targetValue(type: EvidenceTargetType, id: number): string {
    return `${type}:${id}`;
}

export default function ProjectEvidencePage({
    project,
    evidence,
    steps,
    reports,
    selected_step,
    can_upload,
    search: initialSearch,
    max_file_size_bytes,
    accept,
}: Props) {
    const firstStep = steps.find((step) => step.number === selected_step);
    const [search, setSearch] = useState(initialSearch);
    const [selectedFiles, setSelectedFiles] = useState<UploadItem[]>([]);
    const initialTargetType = firstStep ? 'QCC_STEP' : 'PROJECT';
    const initialTargetId = firstStep?.id ?? project.id;
    const form = useForm<EvidenceForm>({
        title: '',
        category: firstStep ? 'QCC_STEP' : 'OTHER',
        description: '',
        target_type: initialTargetType,
        target_id: String(initialTargetId),
        source_type: 'FILE',
        url: '',
        summary: '',
        file: null,
    });
    const hasPendingProcessing = evidence.data.some(
        (item) =>
            ['PENDING', 'PROCESSING'].includes(item.scan_status) ||
            ['PENDING', 'PROCESSING'].includes(item.processing_status),
    );
    const { start, stop } = usePoll(
        3000,
        { only: ['evidence'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (hasPendingProcessing) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [hasPendingProcessing, start, stop]);

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.post(evidenceStore.url({ project: project.id }), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset('title', 'description', 'url', 'summary', 'file');
                setSelectedFiles([]);
            },
        });
    }

    function changeTarget(value: string | null): void {
        if (value === null) return;
        const [type, id] = value.split(':');
        const targetType: EvidenceTargetType =
            type === 'QCC_STEP' ||
            type === 'BIWEEKLY_REPORT' ||
            type === 'MENTORING_REPORT'
                ? type
                : 'PROJECT';
        form.setData((current) => ({
            ...current,
            target_type: targetType,
            target_id: id,
        }));
    }

    function visitEvidence(nextSearch: string, page: number): void {
        router.get(
            evidenceIndex.url({ project: project.id }),
            { q: nextSearch, page },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['evidence', 'search'],
            },
        );
    }

    useEffect(() => setSearch(initialSearch), [initialSearch]);
    useEffect(() => {
        if (search === initialSearch) return;

        const timeout = window.setTimeout(() => visitEvidence(search, 1), 350);

        return () => window.clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const uploadItems = selectedFiles.map((item) => ({
        ...item,
        status: form.processing ? ('uploading' as const) : ('queued' as const),
        progress: form.progress?.percentage ?? 0,
    }));

    const columns = useMemo<DataTableColumnDef<EvidenceItem>[]>(
        () => [
            {
                id: 'evidence',
                accessorKey: 'title',
                header: 'Evidence',
                cell: ({ row }) => {
                    const item = row.original;
                    const Icon =
                        item.source_type === 'FILE'
                            ? fileIcon(item.mime_type ?? '')
                            : Link2;

                    return (
                        <div className="flex min-w-56 items-start gap-3">
                            <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                                {item.thumbnail_url ? (
                                    <img
                                        src={item.thumbnail_url}
                                        alt=""
                                        loading="lazy"
                                        className="size-full rounded-lg object-cover"
                                    />
                                ) : (
                                    <Icon aria-hidden="true" />
                                )}
                            </span>
                            <div className="grid min-w-0 gap-1">
                                <div className="flex flex-wrap items-center gap-1.5">
                                    <span className="font-medium text-foreground">
                                        {item.title}
                                    </span>
                                    <Badge variant="outline">
                                        v{item.version_number}
                                    </Badge>
                                    <Badge variant="outline">
                                        {sourceTypes.find(
                                            ([value]) =>
                                                value === item.source_type,
                                        )?.[1] ?? item.source_type}
                                    </Badge>
                                </div>
                                <span className="truncate text-xs text-muted-foreground">
                                    {item.source_type === 'FILE'
                                        ? `${item.original_name ?? 'File'} · ${formatBytes(item.size_bytes ?? 0)}`
                                        : (item.url ?? 'Tautan')}
                                </span>
                                {item.description && (
                                    <span className="line-clamp-2 max-w-md text-xs text-muted-foreground">
                                        {item.description}
                                    </span>
                                )}
                                {item.summary && (
                                    <span
                                        className="line-clamp-2 max-w-md text-xs text-muted-foreground"
                                        title={item.summary}
                                    >
                                        Ringkasan AI: {item.summary}
                                    </span>
                                )}
                                {item.source_type === 'AI_CHAT_LINK' && (
                                    <span className="text-xs text-muted-foreground">
                                        Isi tautan percakapan tidak dibaca
                                        sistem.
                                    </span>
                                )}
                            </div>
                        </div>
                    );
                },
            },
            {
                accessorKey: 'category',
                header: 'Kategori',
                cell: ({ row }) =>
                    categories.find(
                        ([value]) => value === row.original.category,
                    )?.[1] ?? row.original.category,
            },
            {
                id: 'targets',
                header: 'Terkait',
                cell: ({ row }) => (
                    <div className="grid gap-1">
                        {row.original.targets.map((target) => (
                            <span key={target} className="text-sm">
                                {target}
                            </span>
                        ))}
                    </div>
                ),
            },
            {
                id: 'uploader',
                header: 'Diunggah oleh',
                cell: ({ row }) => (
                    <div className="grid min-w-28 gap-1">
                        <span>{row.original.uploader ?? 'Pengguna'}</span>
                        <span className="text-xs text-muted-foreground">
                            {row.original.uploaded_at
                                ? format(
                                      parseISO(row.original.uploaded_at),
                                      'd MMM yyyy',
                                      { locale: indonesianLocale },
                                  )
                                : '—'}
                        </span>
                    </div>
                ),
            },
            {
                id: 'status',
                header: 'Status file',
                cell: ({ row }) => (
                    <div className="grid justify-items-start gap-1">
                        <Badge
                            className={
                                scanBadgeClasses[row.original.scan_status] ??
                                'border-transparent bg-slate-100 text-slate-700'
                            }
                            variant={
                                scanVariants[row.original.scan_status] ??
                                'secondary'
                            }
                        >
                            {row.original.scan_status === 'PASSED' && (
                                <ShieldCheck data-icon="inline-start" />
                            )}
                            {scanLabels[row.original.scan_status] ??
                                row.original.scan_status}
                        </Badge>
                        <span className="text-xs text-muted-foreground">
                            {row.original.source_type !== 'FILE'
                                ? 'Tautan tersimpan'
                                : row.original.processing_status === 'READY'
                                  ? 'Pemrosesan siap'
                                  : row.original.processing_status === 'FAILED'
                                    ? 'Pemrosesan gagal'
                                    : 'Sedang diproses'}
                            {row.original.source_type === 'FILE' &&
                                row.original.extraction_status === 'READY' &&
                                ' · Teks tersedia'}
                        </span>
                    </div>
                ),
            },
            {
                id: 'actions',
                header: 'Aksi',
                cell: ({ row }) => {
                    const item = row.original;
                    const hasAvailableAction =
                        item.preview_url !== null ||
                        item.download_url !== null ||
                        (item.source_type !== 'FILE' && item.url !== null);

                    if (!hasAvailableAction) {
                        return <span className="text-muted-foreground">—</span>;
                    }

                    return (
                        <div className="flex justify-end">
                            <DataTableRowActions
                                label={`Aksi evidence ${item.title}`}
                                actions={[
                                    {
                                        label: 'Pratinjau',
                                        icon: Eye,
                                        onSelect: () =>
                                            window.open(
                                                item.preview_url!,
                                                '_blank',
                                                'noopener,noreferrer',
                                            ),
                                        hidden: !item.preview_url,
                                    },
                                    {
                                        label: 'Unduh',
                                        icon: Download,
                                        onSelect: () =>
                                            window.location.assign(
                                                item.download_url!,
                                            ),
                                        hidden: !item.download_url,
                                    },
                                    {
                                        label: 'Buka tautan',
                                        icon: ExternalLink,
                                        onSelect: () =>
                                            window.open(
                                                item.url!,
                                                '_blank',
                                                'noopener,noreferrer',
                                            ),
                                        hidden:
                                            item.source_type === 'FILE' ||
                                            !item.url,
                                    },
                                ]}
                            />
                        </div>
                    );
                },
            },
        ],
        [],
    );

    return (
        <>
            <Head title="Evidence Proyek" />
            <div className="mx-auto flex w-full max-w-[1440px] flex-col gap-6 px-4 py-6 md:px-6 md:py-8">
                <PageHeader
                    breadcrumbs={[
                        { title: 'Dashboard', href: dashboard() },
                        { title: 'Proposal QCC', href: proposalIndex() },
                        {
                            title: 'Evidence',
                            href: evidenceIndex({ project: project.id }),
                        },
                    ]}
                    title="Evidence Proyek"
                    description={`${project.code} · ${project.title}`}
                />

                {can_upload && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <UploadCloud
                                    className="size-4 text-primary"
                                    aria-hidden="true"
                                />
                                Tambah Evidence
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="grid gap-5">
                                <div className="grid gap-5 md:grid-cols-2">
                                    <FormField
                                        id="evidence-title"
                                        label="Judul Evidence"
                                        required
                                        error={form.errors.title}
                                    >
                                        <Input
                                            value={form.data.title}
                                            onChange={(event) =>
                                                form.setData(
                                                    'title',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Contoh: Hasil Evaluasi Step 7"
                                            maxLength={200}
                                            className="h-10"
                                        />
                                    </FormField>
                                    <div className="grid gap-2.5">
                                        <label
                                            htmlFor="evidence-source-type"
                                            className="flex items-center gap-0.5 text-sm font-medium"
                                        >
                                            Jenis Evidence{' '}
                                            <span
                                                className="text-destructive"
                                                aria-hidden="true"
                                            >
                                                *
                                            </span>
                                        </label>
                                        <Select
                                            value={form.data.source_type}
                                            onValueChange={(value) => {
                                                if (!value) return;
                                                const sourceType =
                                                    value as EvidenceSourceType;
                                                form.setData((current) => ({
                                                    ...current,
                                                    source_type: sourceType,
                                                    file:
                                                        sourceType === 'FILE'
                                                            ? current.file
                                                            : null,
                                                    url:
                                                        sourceType === 'FILE'
                                                            ? ''
                                                            : current.url,
                                                    summary:
                                                        sourceType ===
                                                        'AI_CHAT_LINK'
                                                            ? current.summary
                                                            : '',
                                                }));
                                                if (sourceType !== 'FILE')
                                                    setSelectedFiles([]);
                                            }}
                                        >
                                            <SelectTrigger
                                                id="evidence-source-type"
                                                className="h-10 w-full"
                                                aria-invalid={Boolean(
                                                    form.errors.source_type,
                                                )}
                                            >
                                                <SelectValue placeholder="Pilih jenis evidence" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {sourceTypes.map(
                                                        ([value, label]) => (
                                                            <SelectItem
                                                                key={value}
                                                                value={value}
                                                            >
                                                                {label}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        {form.errors.source_type && (
                                            <p
                                                role="alert"
                                                className="text-sm text-destructive"
                                            >
                                                {form.errors.source_type}
                                            </p>
                                        )}
                                    </div>
                                    <div className="grid gap-2.5">
                                        <label
                                            htmlFor="evidence-category"
                                            className="flex items-center gap-0.5 text-sm font-medium"
                                        >
                                            Kategori{' '}
                                            <span
                                                className="text-destructive"
                                                aria-hidden="true"
                                            >
                                                *
                                            </span>
                                        </label>
                                        <Select
                                            value={form.data.category}
                                            onValueChange={(value) =>
                                                value &&
                                                form.setData('category', value)
                                            }
                                        >
                                            <SelectTrigger
                                                id="evidence-category"
                                                className="h-10 w-full"
                                                aria-invalid={Boolean(
                                                    form.errors.category,
                                                )}
                                            >
                                                <SelectValue placeholder="Pilih kategori" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {categories.map(
                                                        ([value, label]) => (
                                                            <SelectItem
                                                                key={value}
                                                                value={value}
                                                            >
                                                                {label}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        {form.errors.category && (
                                            <p
                                                role="alert"
                                                className="text-sm text-destructive"
                                            >
                                                {form.errors.category}
                                            </p>
                                        )}
                                    </div>
                                    <div className="grid gap-2.5 md:col-span-2">
                                        <label
                                            htmlFor="evidence-target"
                                            className="flex items-center gap-0.5 text-sm font-medium"
                                        >
                                            Terkait dengan{' '}
                                            <span
                                                className="text-destructive"
                                                aria-hidden="true"
                                            >
                                                *
                                            </span>
                                        </label>
                                        <Select
                                            value={targetValue(
                                                form.data.target_type,
                                                Number(form.data.target_id),
                                            )}
                                            onValueChange={changeTarget}
                                        >
                                            <SelectTrigger
                                                id="evidence-target"
                                                className="h-10 w-full"
                                                aria-invalid={Boolean(
                                                    form.errors.target_id,
                                                )}
                                            >
                                                <SelectValue placeholder="Pilih proyek, step, atau laporan" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    <SelectItem
                                                        value={targetValue(
                                                            'PROJECT',
                                                            project.id,
                                                        )}
                                                    >
                                                        Proyek ini
                                                    </SelectItem>
                                                    {steps.map((step) => (
                                                        <SelectItem
                                                            key={step.id}
                                                            value={targetValue(
                                                                'QCC_STEP',
                                                                step.id,
                                                            )}
                                                        >
                                                            Step {step.number} ·{' '}
                                                            {step.name}
                                                        </SelectItem>
                                                    ))}
                                                    {reports.map((report) => (
                                                        <SelectItem
                                                            key={`${report.type}:${report.id}`}
                                                            value={targetValue(
                                                                report.type,
                                                                report.id,
                                                            )}
                                                        >
                                                            {report.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        {form.errors.target_id && (
                                            <p
                                                role="alert"
                                                className="text-sm text-destructive"
                                            >
                                                {form.errors.target_id}
                                            </p>
                                        )}
                                    </div>
                                    {form.data.source_type !== 'FILE' && (
                                        <div className="md:col-span-2">
                                            <FormField
                                                id="evidence-url"
                                                label="URL Evidence"
                                                required
                                                error={form.errors.url}
                                            >
                                                <Input
                                                    type="url"
                                                    value={form.data.url}
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'url',
                                                            event.target.value,
                                                        )
                                                    }
                                                    placeholder="https://contoh.com/materi"
                                                    maxLength={2048}
                                                    className="h-10"
                                                />
                                            </FormField>
                                        </div>
                                    )}
                                    {form.data.source_type ===
                                        'AI_CHAT_LINK' && (
                                        <div className="md:col-span-2">
                                            <FormField
                                                id="evidence-summary"
                                                label="Ringkasan percakapan AI"
                                                required
                                                error={form.errors.summary}
                                            >
                                                <Textarea
                                                    value={form.data.summary}
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'summary',
                                                            event.target.value,
                                                        )
                                                    }
                                                    placeholder="Ringkas konteks, pertanyaan, jawaban penting, dan hasil percakapan (minimal 30 karakter)."
                                                    minLength={30}
                                                    maxLength={10000}
                                                    className="min-h-24"
                                                />
                                            </FormField>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                Isi tautan tidak dibaca sistem.
                                                Hanya ringkasan ini yang dapat
                                                digunakan sebagai evidence.
                                            </p>
                                        </div>
                                    )}
                                    {form.data.source_type === 'FILE' && (
                                        <div className="md:col-span-2">
                                            <FormField
                                                id="evidence-description"
                                                label="Deskripsi"
                                                error={form.errors.description}
                                            >
                                                <Textarea
                                                    value={
                                                        form.data.description
                                                    }
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'description',
                                                            event.target.value,
                                                        )
                                                    }
                                                    placeholder="Jelaskan isi evidence"
                                                    maxLength={5000}
                                                    className="min-h-20"
                                                />
                                            </FormField>
                                        </div>
                                    )}
                                    <div className="md:col-span-2">
                                        <div className="grid gap-2.5">
                                            <label
                                                htmlFor="evidence-file"
                                                className="flex items-center gap-0.5 text-sm font-medium"
                                            >
                                                File Evidence{' '}
                                                <span
                                                    className="text-destructive"
                                                    aria-hidden="true"
                                                >
                                                    *
                                                </span>
                                            </label>
                                            <FileUploader
                                                id="evidence-file"
                                                files={uploadItems}
                                                accept={accept}
                                                maxFiles={1}
                                                maxFileSizeBytes={
                                                    max_file_size_bytes
                                                }
                                                disabled={form.processing}
                                                error={form.errors.file}
                                                onFilesAdded={(files) => {
                                                    const file =
                                                        files[0] ?? null;
                                                    form.setData('file', file);
                                                    setSelectedFiles(
                                                        file
                                                            ? [
                                                                  {
                                                                      id: randomId(),
                                                                      file,
                                                                  },
                                                              ]
                                                            : [],
                                                    );
                                                }}
                                                onRemove={() => {
                                                    form.setData('file', null);
                                                    setSelectedFiles([]);
                                                }}
                                            />
                                        </div>
                                    </div>
                                </div>
                                <div className="flex justify-end border-t pt-4">
                                    <Button
                                        type="submit"
                                        disabled={
                                            form.processing ||
                                            (form.data.source_type === 'FILE' &&
                                                !form.data.file) ||
                                            (form.data.source_type !== 'FILE' &&
                                                !form.data.url.trim()) ||
                                            (form.data.source_type ===
                                                'AI_CHAT_LINK' &&
                                                form.data.summary.trim()
                                                    .length < 30) ||
                                            !form.data.title.trim()
                                        }
                                    >
                                        <UploadCloud data-icon="inline-start" />
                                        {form.processing
                                            ? `Mengunggah ${form.progress?.percentage ?? 0}%`
                                            : form.data.source_type === 'FILE'
                                              ? 'Unggah Evidence'
                                              : 'Simpan Tautan'}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader className="border-b">
                        <CardTitle>
                            Daftar Evidence{' '}
                            <span className="text-sm font-normal text-muted-foreground">
                                ({evidence.total})
                            </span>
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <DataTable
                            embedded
                            columns={columns}
                            data={evidence.data}
                            rowId={(item) => String(item.id)}
                            page={evidence.current_page}
                            pageSize={evidence.per_page}
                            total={evidence.total}
                            onPageChange={(page) => visitEvidence(search, page)}
                            search={search}
                            onSearchChange={setSearch}
                            searchPlaceholder="Cari judul atau nama file..."
                            emptyTitle={
                                search
                                    ? 'Evidence tidak ditemukan'
                                    : 'Belum ada evidence'
                            }
                            emptyDescription={
                                search
                                    ? 'Coba kata kunci lain atau hapus pencarian.'
                                    : 'Evidence proyek, tautan, dan dokumen akan tampil di sini.'
                            }
                        />
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
