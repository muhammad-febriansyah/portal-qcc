import { Head, Link, router, useForm, usePoll } from '@inertiajs/react';
import {
    FileArchive,
    FileImage,
    FileText,
    Link2,
    Search,
    ShieldCheck,
    UploadCloud,
} from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';
import { FileUploader } from '@/components/file-uploader';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Empty,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
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
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
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

    function changeSearch(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        router.get(
            evidenceIndex.url(
                { project: project.id },
                search ? { query: { q: search } } : {},
            ),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }

    const uploadItems = selectedFiles.map((item) => ({
        ...item,
        status: form.processing ? ('uploading' as const) : ('queued' as const),
        progress: form.progress?.percentage ?? 0,
    }));

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
                                                                      id: crypto.randomUUID(),
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
                    <CardHeader className="gap-4 border-b sm:flex-row sm:items-center sm:justify-between">
                        <CardTitle>
                            Daftar Evidence{' '}
                            <span className="text-sm font-normal text-muted-foreground">
                                ({evidence.total})
                            </span>
                        </CardTitle>
                        <form
                            onSubmit={changeSearch}
                            className="flex w-full gap-2 sm:max-w-sm"
                        >
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Cari judul atau nama file..."
                                aria-label="Cari evidence"
                                className="h-9"
                            />
                            <Button
                                type="submit"
                                variant="outline"
                                size="icon"
                                aria-label="Cari evidence"
                            >
                                <Search />
                            </Button>
                        </form>
                    </CardHeader>
                    <CardContent className="grid gap-3">
                        {evidence.data.length === 0 ? (
                            <Empty className="min-h-56 border">
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <FileText aria-hidden="true" />
                                    </EmptyMedia>
                                    <EmptyTitle>
                                        {search
                                            ? 'Evidence tidak ditemukan'
                                            : 'Belum ada evidence'}
                                    </EmptyTitle>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            evidence.data.map((item) => {
                                const Icon =
                                    item.source_type === 'FILE'
                                        ? fileIcon(item.mime_type ?? '')
                                        : Link2;

                                return (
                                    <article
                                        key={item.id}
                                        className="flex min-w-0 flex-col gap-4 rounded-xl border p-4 sm:flex-row sm:items-center"
                                    >
                                        <div className="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-muted">
                                            {item.thumbnail_url ? (
                                                <img
                                                    src={item.thumbnail_url}
                                                    alt=""
                                                    loading="lazy"
                                                    className="size-full object-cover"
                                                />
                                            ) : (
                                                <Icon
                                                    className="size-6 text-muted-foreground"
                                                    aria-hidden="true"
                                                />
                                            )}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h2 className="font-medium">
                                                    {item.title}
                                                </h2>
                                                <Badge variant="outline">
                                                    v{item.version_number}
                                                </Badge>
                                                <Badge variant="outline">
                                                    {sourceTypes.find(
                                                        ([value]) =>
                                                            value ===
                                                            item.source_type,
                                                    )?.[1] ?? item.source_type}
                                                </Badge>
                                                <Badge
                                                    variant={
                                                        scanVariants[
                                                            item.scan_status
                                                        ] ?? 'secondary'
                                                    }
                                                >
                                                    {item.scan_status ===
                                                        'PASSED' && (
                                                        <ShieldCheck data-icon="inline-start" />
                                                    )}
                                                    {scanLabels[
                                                        item.scan_status
                                                    ] ?? item.scan_status}
                                                </Badge>
                                            </div>
                                            {item.source_type === 'FILE' ? (
                                                <p className="mt-1 truncate text-sm text-muted-foreground">
                                                    {item.original_name} ·{' '}
                                                    {formatBytes(
                                                        item.size_bytes ?? 0,
                                                    )}
                                                </p>
                                            ) : (
                                                item.url && (
                                                    <a
                                                        href={item.url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="mt-1 block truncate text-sm text-primary underline-offset-4 hover:underline"
                                                    >
                                                        {item.url}
                                                    </a>
                                                )
                                            )}
                                            {item.source_type ===
                                                'AI_CHAT_LINK' &&
                                                item.summary && (
                                                    <div className="mt-2 rounded-lg border border-info/30 bg-info/5 p-3 text-sm">
                                                        <p className="text-info-foreground font-medium">
                                                            Ringkasan percakapan
                                                            AI
                                                        </p>
                                                        <p className="mt-1 whitespace-pre-wrap text-muted-foreground">
                                                            {item.summary}
                                                        </p>
                                                        <p className="mt-2 text-xs text-muted-foreground">
                                                            Isi tautan tidak
                                                            dibaca sistem.
                                                        </p>
                                                    </div>
                                                )}
                                            {item.description && (
                                                <p className="mt-1 line-clamp-2 text-sm text-muted-foreground">
                                                    {item.description}
                                                </p>
                                            )}
                                            <div className="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                                <span>
                                                    {categories.find(
                                                        ([value]) =>
                                                            value ===
                                                            item.category,
                                                    )?.[1] ?? item.category}
                                                </span>
                                                {item.targets.map((target) => (
                                                    <span key={target}>
                                                        {target}
                                                    </span>
                                                ))}
                                                <span>
                                                    {item.uploader ??
                                                        'Pengguna'}
                                                </span>
                                                <span>
                                                    {item.source_type !== 'FILE'
                                                        ? 'Tidak memerlukan pemrosesan file'
                                                        : item.processing_status ===
                                                            'READY'
                                                          ? 'Pemrosesan siap'
                                                          : item.processing_status ===
                                                              'FAILED'
                                                            ? 'Pemrosesan gagal'
                                                            : 'Sedang diproses'}
                                                </span>
                                                {item.source_type === 'FILE' &&
                                                    item.extraction_status ===
                                                        'READY' && (
                                                        <span>
                                                            Teks PDF tersedia
                                                        </span>
                                                    )}
                                            </div>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-2">
                                            {item.preview_url && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    render={
                                                        <a
                                                            href={
                                                                item.preview_url
                                                            }
                                                            target="_blank"
                                                            rel="noreferrer"
                                                        />
                                                    }
                                                    nativeButton={false}
                                                >
                                                    Pratinjau
                                                </Button>
                                            )}
                                            {item.download_url && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    render={
                                                        <a
                                                            href={
                                                                item.download_url
                                                            }
                                                        />
                                                    }
                                                    nativeButton={false}
                                                >
                                                    Unduh
                                                </Button>
                                            )}
                                        </div>
                                    </article>
                                );
                            })
                        )}
                        {evidence.last_page > 1 && (
                            <nav
                                aria-label="Halaman evidence"
                                className="flex flex-wrap justify-center gap-1 border-t pt-4"
                            >
                                {evidence.links.map((item, index) =>
                                    item.url ? (
                                        <Button
                                            key={`${item.label}-${index}`}
                                            variant={
                                                item.active
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            size="sm"
                                            render={
                                                <Link
                                                    href={item.url}
                                                    preserveScroll
                                                />
                                            }
                                            nativeButton={false}
                                        >
                                            {item.label.replace(
                                                /&laquo;|&raquo;/g,
                                                '',
                                            )}
                                        </Button>
                                    ) : (
                                        <Button
                                            key={`${item.label}-${index}`}
                                            variant="outline"
                                            size="sm"
                                            disabled
                                        >
                                            {item.label.replace(
                                                /&laquo;|&raquo;/g,
                                                '',
                                            )}
                                        </Button>
                                    ),
                                )}
                            </nav>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
