import { Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    FileText,
    Save,
    Trash2,
    TriangleAlert,
    UsersRound,
} from 'lucide-react';
import { isValid, parseISO } from 'date-fns';
import { useEffect, useMemo, useRef, useState } from 'react';
import { DatePicker } from '@/components/date-picker';
import { FileUploader, type UploadItem } from '@/components/file-uploader';
import { FormField } from '@/components/form-field';
import { FormattedText } from '@/components/formatted-text';
import { PageHeader } from '@/components/page-header';
import {
    ProjectRequirementsField,
    type ProjectRequirement,
    type RequirementCompetencyOption,
} from '@/components/project-requirements-field';
import { RichTextarea } from '@/components/rich-textarea';
import { TimelineTable } from '@/components/step-timeline-table';
import { WizardStepper } from '@/components/wizard-stepper';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
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
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import {
    create as proposalCreate,
    index as proposalsIndex,
    submit as proposalSubmit,
    store as proposalStore,
    update as proposalUpdate,
} from '@/routes/proposals';
import {
    destroy as destroyAttachment,
    store as storeAttachments,
} from '@/routes/proposals/attachments';
import { randomId } from '@/lib/utils';

type BatchOption = { id: number; name: string; starts_on: string };
type DraftOption = {
    id: number;
    title: string;
    batch: string;
    wizard_step: number;
    updated_at: string | null;
};
type ExistingAttachment = {
    id: number;
    original_name: string;
    mime_type: string;
    size_bytes: number;
    scan_status: string;
    preview_url: string | null;
};
const attachmentStatusLabels: Record<string, string> = {
    PENDING_SCAN: 'Menunggu pemeriksaan',
    PASSED: 'Siap dibuka',
    REJECTED: 'Ditolak',
    FAILED: 'Gagal diperiksa',
};
type ProjectDraft = {
    id: number;
    batch_id: number;
    title: string;
    theme: string | null;
    theme_category: string | null;
    background: string | null;
    current_situation: string | null;
    improvement_plan: string | null;
    target: string | null;
    scope: string | null;
    supplementary_information: string | null;
    cost_projection: string | null;
    learning_goals: string[];
    step_start_dates: string[];
    step_timeline: string[];
    step_deliverables: string[];
    wizard_step: number;
    updated_at: string | null;
    attachments: ExistingAttachment[];
    can_submit: boolean;
};
type QccStepOption = { number: number; name: string };
type Props = {
    project: ProjectDraft | null;
    step: number;
    batches: BatchOption[];
    drafts: DraftOption[];
    qccSteps: QccStepOption[];
    themeCategories?: { value: string; label: string; description: string }[];
    competencyOptions?: RequirementCompetencyOption[];
    requirements?: ProjectRequirement[];
    levelOptions?: { value: number; label: string }[];
    attachmentLimit?: { maxFiles: number; maxSizeMb: number };
};
type DraftForm = {
    batch_id: string;
    title: string;
    theme: string;
    theme_category: string;
    background: string;
    current_situation: string;
    improvement_plan: string;
    target: string;
    scope: string;
    supplementary_information: string;
    cost_projection: string;
    learning_goals: string[];
    step_start_dates: string[];
    step_timeline: string[];
    step_deliverables: string[];
    wizard_step: number;
    advance: boolean;
};
type DraftField = Exclude<
    keyof DraftForm,
    | 'batch_id'
    | 'learning_goals'
    | 'step_start_dates'
    | 'step_timeline'
    | 'step_deliverables'
    | 'wizard_step'
    | 'advance'
>;
type StepDefinition = {
    number: number;
    label: string;
    description: string;
};

const steps: StepDefinition[] = [
    {
        number: 1,
        label: 'Informasi Dasar',
        description:
            'Tentukan judul, batch, dan tema improvement yang kamu ajukan.',
    },
    {
        number: 2,
        label: 'Latar Belakang & Situasi',
        description:
            'Jelaskan latar belakang serta situasi saat ini beserta data baseline-nya.',
    },
    {
        number: 3,
        label: 'Rencana & Target',
        description:
            'Uraikan arah solusi, target yang ingin dicapai, dan ruang lingkup proyek.',
    },
    {
        number: 4,
        label: 'Data Pelengkap & Biaya',
        description:
            'Tambahkan informasi pendukung, lampiran, dan proyeksi kebutuhan biaya proyek.',
    },
    {
        number: 5,
        label: 'Pembelajaran & Timeline',
        description:
            'Tulis tiga hal yang akan dipelajari serta tanggal mulai dan target selesai setiap step QCC.',
    },
    {
        number: 6,
        label: 'Kebutuhan Kompetensi',
        description:
            'Pilih kompetensi yang dibutuhkan proyek. Owner QCC memakainya untuk menyusun tim.',
    },
    {
        number: 7,
        label: 'Pratinjau & Ajukan',
        description:
            'Periksa seluruh isi proposal sebelum diajukan ke Owner QCC.',
    },
];

/** Where each validated field lives, so an error can point to its step. */
const fieldSteps: Record<string, { step: number; label: string }> = {
    title: { step: 1, label: 'Judul Proposal' },
    batch_id: { step: 1, label: 'Batch QCC' },
    theme_category: { step: 1, label: 'Kategori Tema' },
    theme: { step: 1, label: 'Tema Improvement' },
    background: { step: 2, label: 'Latar Belakang' },
    current_situation: { step: 2, label: 'Situasi Saat Ini & Baseline' },
    improvement_plan: { step: 3, label: 'Rencana Perbaikan' },
    target: { step: 3, label: 'Target/Tujuan' },
    scope: { step: 3, label: 'Ruang Lingkup Proyek' },
    supplementary_information: {
        step: 4,
        label: 'Informasi dan Data Pelengkap',
    },
    cost_projection: { step: 4, label: 'Proyeksi Kebutuhan Biaya Proyek' },
    learning_goals: { step: 5, label: 'Tiga Hal yang akan Dipelajari' },
    step_start_dates: { step: 5, label: 'Tanggal Mulai Timeline' },
    step_timeline: { step: 5, label: 'Target Selesai Timeline' },
    step_deliverables: { step: 5, label: 'Deliverable Timeline' },
    requirements: { step: 6, label: 'Kebutuhan Kompetensi' },
};

function errorLocation(field: string): { step: number; label: string } | null {
    return fieldSteps[field.split('.')[0]] ?? null;
}

/** Same height for every control, so fields in one row line up. */
const controlClassName = 'h-11';
const selectTriggerClassName = 'h-11 w-full data-[size=default]:h-11';

const savedAtFormat = new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

function SectionTitle({
    title,
    description,
}: {
    title: string;
    description?: string;
}): React.ReactNode {
    return (
        <div className="grid gap-0.5 border-b pb-3">
            <h3 className="text-sm font-semibold text-foreground">{title}</h3>
            {description && (
                <p className="text-xs text-muted-foreground">{description}</p>
            )}
        </div>
    );
}

function initialData(
    project: ProjectDraft | null,
    step: number,
    stepCount: number,
    defaultBatchId = '',
): DraftForm {
    const startDates = project?.step_start_dates ?? [];
    const timeline = project?.step_timeline ?? [];
    const deliverables = project?.step_deliverables ?? [];

    return {
        batch_id: project ? String(project.batch_id) : defaultBatchId,
        title: project?.title ?? '',
        theme: project?.theme ?? '',
        theme_category: project?.theme_category ?? '',
        background: project?.background ?? '',
        current_situation: project?.current_situation ?? '',
        improvement_plan: project?.improvement_plan ?? '',
        target: project?.target ?? '',
        scope: project?.scope ?? '',
        supplementary_information: project?.supplementary_information ?? '',
        cost_projection: project?.cost_projection ?? '',
        learning_goals:
            project?.learning_goals?.length === 3
                ? project.learning_goals.map((goal) => goal ?? '')
                : ['', '', ''],
        step_start_dates: Array.from(
            { length: stepCount },
            (_, index) => startDates[index] ?? '',
        ),
        step_timeline: Array.from(
            { length: stepCount },
            (_, index) => timeline[index] ?? '',
        ),
        step_deliverables: Array.from(
            { length: stepCount },
            (_, index) => deliverables[index] ?? '',
        ),
        wizard_step: project?.wizard_step ?? step,
        advance: false,
    };
}

/** Days before a step starts cannot be its target finish date. */
function startDateMatcher(startDate: string): { before: Date } | undefined {
    const date = startDate ? parseISO(startDate) : null;

    return date && isValid(date) ? { before: date } : undefined;
}

function fileSize(bytes: number): string {
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/** What each QCC step usually produces, shown above the step's deliverable field. */
const stepDeliverableExamples: Record<number, string> = {
    1: 'Data kondisi aktual, tema improvement terpilih',
    2: 'Target SMART, KPI keberhasilan',
    3: 'Daftar potensi penyebab, diagram Fishbone (5M+1E)',
    4: 'Hasil validasi penyebab, diagram Pareto',
    5: 'Action plan 5W+1H',
    6: 'Bukti implementasi, dokumentasi before-after',
    7: 'Perbandingan baseline vs aktual, pencapaian target, benefit improvement',
    8: 'SOP/standar kerja terbaru, rencana sustainability, tindak lanjut',
};

export default function ProposalWizard({
    project,
    step,
    batches,
    drafts: _drafts,
    qccSteps,
    themeCategories = [],
    competencyOptions = [],
    requirements = [],
    levelOptions = [],
    attachmentLimit = { maxFiles: 5, maxSizeMb: 50 },
}: Props) {
    const [requirementsDirty, setRequirementsDirty] = useState(false);
    const [pendingFiles, setPendingFiles] = useState<UploadItem[]>([]);
    const [activeStep, setActiveStep] = useState(project?.wizard_step ?? step);
    const [submitting, setSubmitting] = useState(false);
    const draftSaveInFlight = useRef(false);
    const errorSummaryRef = useRef<HTMLDivElement>(null);
    // With a single open batch there is nothing to choose, so it is picked already.
    const onlyBatchId = batches.length === 1 ? String(batches[0].id) : '';
    const form = useForm<DraftForm>(
        initialData(project, step, qccSteps.length, onlyBatchId),
    );
    const uploadForm = useForm<{ files: File[] }>({ files: [] });
    const pageErrors = usePage().props.errors as Record<string, string>;
    const isEditing = project !== null;

    useEffect(() => {
        if (project) {
            form.setData(
                initialData(project, project.wizard_step, qccSteps.length),
            );
        }
        // Refresh form after a new draft is created or a persisted draft changes.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [project?.id, project?.wizard_step]);

    const existingAttachments = useMemo(
        () => project?.attachments ?? [],
        [project?.attachments],
    );
    const selectedFiles = pendingFiles.map((item) => item.file);
    const uploadError = Object.entries(uploadForm.errors).find(([key]) =>
        key.startsWith('files'),
    )?.[1];
    const uploadItems = pendingFiles.map((item) => ({
        ...item,
        status: uploadForm.processing
            ? ('uploading' as const)
            : uploadError
              ? ('error' as const)
              : item.status,
        error: typeof uploadError === 'string' ? uploadError : item.error,
        progress: uploadForm.progress?.percentage ?? item.progress,
    }));

    /** Draft and submit errors together, each pointing to its step. */
    const errorItems = useMemo(() => {
        const merged: Record<string, string> = {
            ...pageErrors,
            ...(form.errors as Record<string, string>),
        };

        return Object.entries(merged)
            .filter(([field]) => !field.startsWith('files'))
            .map(([field, message]) => ({
                field,
                message,
                location: errorLocation(field),
            }));
    }, [form.errors, pageErrors]);

    useEffect(() => {
        if (errorItems.length > 0) {
            errorSummaryRef.current?.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
            });
            errorSummaryRef.current?.focus({ preventScroll: true });
        }
    }, [errorItems.length]);

    function saveDraft(advance = false): void {
        if (draftSaveInFlight.current || form.processing) return;

        draftSaveInFlight.current = true;
        form.transform((data) => ({
            ...data,
            wizard_step: activeStep,
            advance,
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                form.setDefaults();
                if (advance) {
                    setActiveStep((current) =>
                        Math.min(current + 1, steps.length),
                    );
                }
            },
            onFinish: () => {
                draftSaveInFlight.current = false;
            },
        };

        if (project) {
            form.patch(proposalUpdate.url(project.id), options);
        } else {
            form.post(proposalStore.url(), options);
        }
    }

    function submitProposal(): void {
        if (!project) return;

        router.post(
            proposalSubmit.url(project.id),
            {},
            {
                preserveScroll: true,
                onStart: () => setSubmitting(true),
                onFinish: () => setSubmitting(false),
            },
        );
    }

    function addFiles(files: File[]): void {
        setPendingFiles((current) => [
            ...current,
            ...files.map((file) => ({
                id: `${file.name}-${file.size}-${file.lastModified}-${randomId()}`,
                file,
                status: 'queued' as const,
            })),
        ]);

        uploadFiles([...selectedFiles, ...files]);
    }

    function uploadFiles(filesToUpload: File[] = selectedFiles): void {
        if (!project || filesToUpload.length === 0 || uploadForm.processing) {
            return;
        }

        uploadForm.clearErrors();
        uploadForm.transform(() => ({ files: filesToUpload }));
        uploadForm.post(storeAttachments.url(project.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setPendingFiles((current) =>
                    current.filter(
                        (item) => !filesToUpload.includes(item.file),
                    ),
                );
                uploadForm.reset();
            },
        });
    }

    function removeAttachment(attachment: ExistingAttachment): void {
        if (!project) return;

        router.delete(
            destroyAttachment.url({
                project: project.id,
                attachment: attachment.id,
            }),
            {
                preserveScroll: true,
            },
        );
    }

    const hasTitle = form.data.title.trim() !== '';
    const canSaveDraft = isEditing || hasTitle;
    const savedAt = project?.updated_at
        ? savedAtFormat.format(new Date(project.updated_at))
        : null;
    const footerHint = form.processing
        ? 'Menyimpan...'
        : !hasTitle
          ? 'Isi judul proposal dulu untuk bisa menyimpan.'
          : requirementsDirty
            ? 'Simpan perubahan kompetensi sebelum menyimpan draft.'
            : savedAt
              ? `Draft terakhir disimpan ${savedAt}.`
              : 'Draft belum disimpan.';

    function setField(field: DraftField, value: string): void {
        form.setData(field, value);
    }

    function fieldError(field: string): string | undefined {
        return (
            (form.errors as Record<string, string>)[field] ?? pageErrors[field]
        );
    }

    function setTimelineValue(
        field: 'step_start_dates' | 'step_timeline' | 'step_deliverables',
        index: number,
        value: string | null,
    ): void {
        form.setData(
            field,
            form.data[field].map((current, currentIndex) =>
                currentIndex === index ? (value ?? '') : current,
            ),
        );
    }

    const timelineRows = qccSteps.map((qccStep, index) => ({
        number: qccStep.number,
        name: qccStep.name,
        startDate: form.data.step_start_dates[index] || null,
        date: form.data.step_timeline[index] || null,
        deliverable: form.data.step_deliverables[index] || null,
    }));

    const competencyNames = new Map(
        competencyOptions.map((option) => [option.id, option.label]),
    );
    const levelNames = new Map(
        levelOptions.map((option) => [option.value, option.label]),
    );
    const selectedBatch = batches.find(
        (batch) => String(batch.id) === form.data.batch_id,
    );
    const batchName = selectedBatch?.name ?? '';
    const selectedThemeCategory = themeCategories.find(
        (category) => category.value === form.data.theme_category,
    );

    function renderStepFields(stepNumber: number): React.ReactNode {
        if (stepNumber === 1) {
            return (
                <div className="grid gap-6">
                    <FormField
                        id="proposal-title"
                        label="Judul Proposal"
                        hint="Tuliskan judul yang menggambarkan permasalahan atau peluang perbaikan yang ingin dicapai. Buat secara spesifik, jelas, dan menunjukkan arah hasil yang diharapkan."
                        example="Meningkatkan Revenue Value Added Service pada Klien Handle LOB MSM Labour dari 18 Juta pada Bulan Oktober 2025 menjadi 1,5 Miliar pada Closing Bulan April 2026"
                        required
                        error={fieldError('title')}
                    >
                        <Input
                            value={form.data.title}
                            onChange={(event) =>
                                setField('title', event.target.value)
                            }
                            placeholder="Meningkatkan ... dari ... pada ... menjadi ... pada ..."
                            maxLength={255}
                            className={controlClassName}
                        />
                    </FormField>
                    <div className="grid items-start gap-6 sm:grid-cols-2">
                        <FormField
                            id="proposal-batch"
                            label="Batch QCC"
                            hint="Hanya batch yang sedang aktif yang bisa dipilih."
                            required
                            error={fieldError('batch_id')}
                        >
                            <Select
                                value={form.data.batch_id}
                                onValueChange={(value) =>
                                    form.setData('batch_id', value ?? '')
                                }
                                items={batches.map((batch) => ({
                                    value: String(batch.id),
                                    label: batch.name,
                                }))}
                            >
                                <SelectTrigger
                                    id="proposal-batch"
                                    className={selectTriggerClassName}
                                    aria-invalid={Boolean(
                                        fieldError('batch_id'),
                                    )}
                                >
                                    <SelectValue placeholder="Pilih batch QCC" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        {batches.map((batch) => (
                                            <SelectItem
                                                key={batch.id}
                                                value={String(batch.id)}
                                            >
                                                {batch.name}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </FormField>
                        <FormField
                            id="proposal-theme-category"
                            label="Kategori Tema"
                            hint="Jenis improvement yang dituju proposal ini."
                            required
                            error={fieldError('theme_category')}
                            footer={
                                selectedThemeCategory ? (
                                    <p className="rounded-lg bg-muted/60 px-3 py-2 text-xs leading-relaxed text-muted-foreground">
                                        <span className="font-medium text-foreground">
                                            Definisi:{' '}
                                        </span>
                                        {selectedThemeCategory.description}
                                    </p>
                                ) : null
                            }
                        >
                            <Select
                                value={form.data.theme_category}
                                onValueChange={(value) =>
                                    form.setData('theme_category', value ?? '')
                                }
                                items={themeCategories}
                            >
                                <SelectTrigger
                                    id="proposal-theme-category"
                                    className={selectTriggerClassName}
                                    aria-invalid={Boolean(
                                        fieldError('theme_category'),
                                    )}
                                >
                                    <SelectValue placeholder="Pilih kategori tema" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        {themeCategories.map((category) => (
                                            <SelectItem
                                                key={category.value}
                                                value={category.value}
                                                className="py-1.5"
                                            >
                                                <span className="flex flex-col gap-0.5">
                                                    <span>
                                                        {category.label}
                                                    </span>
                                                    <span className="text-xs leading-snug text-muted-foreground">
                                                        {category.description}
                                                    </span>
                                                </span>
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </FormField>
                    </div>
                    <FormField
                        id="proposal-theme"
                        label="Tema Improvement"
                        hint="Arah peluang perbaikan yang paling sesuai dengan dampak atau kontribusi utama yang ingin dihasilkan proyek terhadap perusahaan."
                        example="Optimalisasi Value Added Service untuk meningkatkan revenue"
                        required
                        error={fieldError('theme')}
                    >
                        <Textarea
                            value={form.data.theme}
                            onChange={(event) =>
                                setField('theme', event.target.value)
                            }
                            placeholder="Tuliskan permasalahan atau peluang perbaikan yang menjadi fokus"
                            maxLength={5000}
                            className="min-h-32"
                        />
                    </FormField>
                </div>
            );
        }

        if (stepNumber === 2) {
            return (
                <div className="grid gap-6">
                    <FormField
                        id="proposal-background"
                        label="Latar Belakang"
                        hint="Jelaskan alasan mengapa proyek ini penting untuk dilakukan. Gambarkan permasalahan, kondisi, atau peluang yang melatarbelakangi munculnya ide proyek serta dampaknya terhadap perusahaan."
                        required
                        error={fieldError('background')}
                    >
                        <RichTextarea
                            value={form.data.background}
                            onChange={(value) => setField('background', value)}
                            placeholder="Contoh: Proyek ini penting karena ... Masalah utama tim saat ini adalah ... Jika tidak segera diperbaiki, dampaknya ..."
                        />
                    </FormField>
                    <FormField
                        id="proposal-situation"
                        label="Situasi Saat Ini & Baseline"
                        hint="Tampilkan kondisi aktual sebelum perbaikan dilakukan dengan data kuantitatif maupun kualitatif. Pastikan baseline memiliki periode pengukuran dan sumber data yang jelas."
                        required
                        error={fieldError('current_situation')}
                    >
                        <RichTextarea
                            value={form.data.current_situation}
                            onChange={(value) =>
                                setField('current_situation', value)
                            }
                            placeholder="Contoh: Saat ini revenue VAS 18 juta per bulan (data Oktober 2025)..."
                        />
                    </FormField>
                </div>
            );
        }

        if (stepNumber === 3) {
            return (
                <div className="grid gap-6">
                    <FormField
                        id="proposal-improvement"
                        label="Rencana Perbaikan / Arah Solusi / Improvement"
                        hint="Jelaskan area yang akan dieksplorasi untuk menghasilkan perbaikan. Bukan merupakan solusi final sebelum akar penyebab dan peluang perbaikan dianalisis."
                        example="Mengeksplorasi potensi peningkatan pendapatan melalui optimalisasi layanan tambahan kepada klien, termasuk mengidentifikasi peluang layanan yang dapat dikembangkan dan faktor-faktor yang memengaruhi pemanfaatannya."
                        required
                        error={fieldError('improvement_plan')}
                    >
                        <RichTextarea
                            value={form.data.improvement_plan}
                            onChange={(value) =>
                                setField('improvement_plan', value)
                            }
                            placeholder="Jelaskan rencana perbaikan atau arah solusi"
                        />
                    </FormField>
                    <div className="grid gap-6 lg:grid-cols-2">
                        <FormField
                            id="proposal-target"
                            label="Target / Tujuan"
                            hint="Tentukan hasil yang ingin dicapai secara spesifik dan terukur dengan konsep SMART (Specific, Measurable, Achievable, Relevant, Time-bound)."
                            required
                            error={fieldError('target')}
                        >
                            <RichTextarea
                                value={form.data.target}
                                onChange={(value) => setField('target', value)}
                                placeholder="Tuliskan target dan cara mengukurnya"
                            />
                        </FormField>
                        <FormField
                            id="proposal-scope"
                            label="Ruang Lingkup Proyek"
                            hint="Tentukan batasan proyek, termasuk proses, aktivitas, area kerja, objek, dan pihak yang terlibat agar pelaksanaan proyek tetap fokus."
                            required
                            error={fieldError('scope')}
                        >
                            <RichTextarea
                                value={form.data.scope}
                                onChange={(value) => setField('scope', value)}
                                placeholder="Jelaskan proses, area, atau periode yang termasuk"
                            />
                        </FormField>
                    </div>
                </div>
            );
        }

        if (stepNumber === 4) {
            return (
                <div className="grid gap-6">
                    <FormField
                        id="proposal-supplementary-information"
                        label="Informasi dan Data Pelengkap"
                        hint="Cantumkan informasi atau data pendukung yang membantu memahami proyek, seperti grafik, foto kondisi aktual, laporan, hasil observasi, data historis, atau dokumen terkait. Jangan masukkan data pribadi yang tidak diperlukan."
                        error={fieldError('supplementary_information')}
                    >
                        <RichTextarea
                            value={form.data.supplementary_information}
                            onChange={(value) =>
                                setField('supplementary_information', value)
                            }
                            placeholder="Tambahkan data atau konteks pendukung"
                        />
                    </FormField>
                    <FormField
                        id="proposal-cost-projection"
                        label="Proyeksi Kebutuhan Biaya Proyek"
                        hint="Opsional. Perkiraan biaya yang dibutuhkan beserta rinciannya. Kosongkan bila proyek tidak memerlukan biaya."
                        error={fieldError('cost_projection')}
                    >
                        <RichTextarea
                            value={form.data.cost_projection}
                            onChange={(value) =>
                                setField('cost_projection', value)
                            }
                            placeholder="Contoh: - Lisensi tools: Rp 5.000.000"
                        />
                    </FormField>
                    {project ? (
                        <>
                            <FormField
                                id="proposal-attachments"
                                label="Lampiran Proposal"
                                hint="Lampiran langsung diunggah setelah dipilih. PDF dan gambar dapat dipratinjau setelah tersimpan."
                            >
                                <FileUploader
                                    id="proposal-attachments"
                                    files={uploadItems}
                                    onFilesAdded={addFiles}
                                    onRemove={(file) =>
                                        setPendingFiles((current) =>
                                            current.filter(
                                                (item) => item.id !== file.id,
                                            ),
                                        )
                                    }
                                    onRetry={() => uploadFiles()}
                                    accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx"
                                    maxFiles={Math.min(
                                        attachmentLimit.maxFiles,
                                        10 - existingAttachments.length,
                                    )}
                                    maxFileSizeBytes={
                                        attachmentLimit.maxSizeMb * 1024 * 1024
                                    }
                                    disabled={
                                        uploadForm.processing ||
                                        existingAttachments.length >= 10
                                    }
                                    error={
                                        typeof uploadError === 'string'
                                            ? uploadError
                                            : undefined
                                    }
                                />
                            </FormField>
                            {existingAttachments.length > 0 && (
                                <AttachmentList
                                    attachments={existingAttachments}
                                    onRemove={removeAttachment}
                                />
                            )}
                        </>
                    ) : (
                        <Empty className="min-h-32 rounded-xl border">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <FileText aria-hidden="true" />
                                </EmptyMedia>
                                <EmptyTitle>
                                    Simpan draft sebelum menambahkan lampiran
                                </EmptyTitle>
                            </EmptyHeader>
                        </Empty>
                    )}
                </div>
            );
        }

        if (stepNumber === 5) {
            return (
                <div className="grid gap-6">
                    <SectionTitle
                        title="Tiga hal yang akan dipelajari"
                        description="Wajib diisi tiga. Ini dipakai untuk menilai kecocokan tim."
                    />
                    {form.data.learning_goals.map((goal, index) => (
                        <FormField
                            key={index}
                            id={`learning-goal-${index + 1}`}
                            label={`Hal yang akan Dipelajari ${index + 1}`}
                            required
                            error={fieldError(`learning_goals.${index}`)}
                        >
                            <Input
                                value={goal}
                                onChange={(event) =>
                                    form.setData(
                                        'learning_goals',
                                        form.data.learning_goals.map(
                                            (currentGoal, currentIndex) =>
                                                currentIndex === index
                                                    ? event.target.value
                                                    : currentGoal,
                                        ),
                                    )
                                }
                                placeholder={`Contoh: ${['Analisis data proses', 'Penerapan metode improvement', 'Standardisasi hasil perbaikan'][index]}`}
                                maxLength={255}
                                className={controlClassName}
                            />
                        </FormField>
                    ))}
                    <SectionTitle
                        title="Timeline"
                        description="Tanggal mulai, target selesai, dan hasil (deliverable) setiap step QCC. Step boleh berjalan bersamaan, jadi tanggalnya boleh tumpang tindih. Target selesai menjadi target step saat proyek berjalan."
                    />
                    <div className="grid gap-4">
                        {qccSteps.map((qccStep, index) => (
                            <fieldset
                                key={qccStep.number}
                                className="grid gap-3 rounded-lg border border-border/70 p-4 md:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.4fr)]"
                            >
                                <legend className="sr-only">
                                    {`${qccStep.number}. ${qccStep.name}`}
                                </legend>
                                <div className="grid gap-1 md:col-span-2 xl:col-span-3">
                                    <p className="text-sm font-semibold text-foreground">
                                        {qccStep.number}. {qccStep.name}
                                    </p>
                                    {stepDeliverableExamples[
                                        qccStep.number
                                    ] && (
                                        <p className="text-xs text-muted-foreground">
                                            <span className="font-medium text-foreground">
                                                Contoh deliverable:
                                            </span>{' '}
                                            {
                                                stepDeliverableExamples[
                                                    qccStep.number
                                                ]
                                            }
                                        </p>
                                    )}
                                </div>
                                <FormField
                                    id={`step-start-${qccStep.number}`}
                                    label="Tanggal mulai"
                                    required
                                    error={fieldError(
                                        `step_start_dates.${index}`,
                                    )}
                                >
                                    <DatePicker
                                        value={
                                            form.data.step_start_dates[index]
                                        }
                                        onValueChange={(value) =>
                                            setTimelineValue(
                                                'step_start_dates',
                                                index,
                                                value,
                                            )
                                        }
                                        placeholder="Pilih tanggal mulai"
                                        className={controlClassName}
                                    />
                                </FormField>
                                <FormField
                                    id={`step-timeline-${qccStep.number}`}
                                    label="Target selesai"
                                    required
                                    error={fieldError(`step_timeline.${index}`)}
                                >
                                    <DatePicker
                                        value={form.data.step_timeline[index]}
                                        onValueChange={(value) =>
                                            setTimelineValue(
                                                'step_timeline',
                                                index,
                                                value,
                                            )
                                        }
                                        placeholder="Pilih target selesai"
                                        disabledDates={startDateMatcher(
                                            form.data.step_start_dates[index],
                                        )}
                                        className={controlClassName}
                                    />
                                </FormField>
                                <FormField
                                    id={`step-deliverable-${qccStep.number}`}
                                    label="Deliverable results"
                                    error={fieldError(
                                        `step_deliverables.${index}`,
                                    )}
                                >
                                    <Input
                                        value={
                                            form.data.step_deliverables[index]
                                        }
                                        onChange={(event) =>
                                            setTimelineValue(
                                                'step_deliverables',
                                                index,
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Tulis hasil step ini"
                                        maxLength={500}
                                        className={controlClassName}
                                    />
                                </FormField>
                            </fieldset>
                        ))}
                    </div>
                    <TimelineTable
                        rows={timelineRows}
                        startDate={selectedBatch?.starts_on}
                    />
                </div>
            );
        }

        if (stepNumber === 6) {
            return project ? (
                <ProjectRequirementsField
                    projectId={project.id}
                    competencyOptions={competencyOptions}
                    requirements={requirements}
                    levelOptions={levelOptions}
                    onDirtyChange={setRequirementsDirty}
                />
            ) : (
                <Card className="border-dashed bg-muted/30">
                    <CardContent className="pt-4 text-sm text-muted-foreground">
                        Kompetensi dapat ditambahkan setelah draft tersimpan.
                    </CardContent>
                </Card>
            );
        }

        return (
            <div className="grid gap-5">
                <Alert>
                    <UsersRound aria-hidden="true" />
                    <AlertTitle>Setelah diajukan</AlertTitle>
                    <AlertDescription>
                        Owner QCC akan menyeleksi proposal dan menyusun tim
                        berdasarkan kebutuhan kompetensi yang kamu pilih.
                    </AlertDescription>
                </Alert>
                <dl className="grid gap-3">
                    <PreviewValue label="Judul" value={form.data.title} plain />
                    <PreviewValue label="Batch QCC" value={batchName} plain />
                    <PreviewValue
                        label="Kategori Tema"
                        value={
                            themeCategories.find(
                                (category) =>
                                    category.value === form.data.theme_category,
                            )?.label ?? ''
                        }
                        plain
                    />
                    <PreviewValue
                        label="Tema Improvement"
                        value={form.data.theme}
                        plain
                    />
                    <PreviewValue
                        label="Latar Belakang"
                        value={form.data.background}
                    />
                    <PreviewValue
                        label="Situasi Saat Ini & Baseline"
                        value={form.data.current_situation}
                    />
                    <PreviewValue
                        label="Rencana Perbaikan / Arah Solusi / Improvement"
                        value={form.data.improvement_plan}
                    />
                    <PreviewValue
                        label="Target / Tujuan"
                        value={form.data.target}
                    />
                    <PreviewValue
                        label="Ruang Lingkup Proyek"
                        value={form.data.scope}
                    />
                    <PreviewValue
                        label="Informasi dan Data Pelengkap"
                        value={form.data.supplementary_information}
                    />
                    <PreviewValue
                        label="Proyeksi Kebutuhan Biaya Proyek"
                        value={form.data.cost_projection}
                    />
                    <PreviewBlock label="Tiga Hal yang akan Dipelajari">
                        {form.data.learning_goals.some(Boolean) ? (
                            <ol className="list-decimal space-y-0.5 pl-5">
                                {form.data.learning_goals.map((goal, index) => (
                                    <li key={index}>
                                        {goal.trim() || 'Belum diisi'}
                                    </li>
                                ))}
                            </ol>
                        ) : (
                            'Belum diisi'
                        )}
                    </PreviewBlock>
                    <PreviewBlock label="Timeline">
                        <TimelineTable
                            rows={timelineRows}
                            startDate={selectedBatch?.starts_on}
                        />
                    </PreviewBlock>
                    <PreviewBlock label="Kebutuhan Kompetensi">
                        {requirements.length > 0 ? (
                            <ul className="list-disc space-y-0.5 pl-5">
                                {requirements.map((requirement) => (
                                    <li key={requirement.competency_id}>
                                        {competencyNames.get(
                                            requirement.competency_id,
                                        ) ?? `#${requirement.competency_id}`}
                                        {requirement.minimum_level !== null &&
                                            ` · minimal ${levelNames.get(requirement.minimum_level) ?? requirement.minimum_level}`}
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            'Belum diisi'
                        )}
                    </PreviewBlock>
                </dl>
            </div>
        );
    }

    return (
        <div className="mx-auto w-full max-w-[1440px] px-4 py-6 md:px-6 md:py-8">
            <PageHeader
                breadcrumbs={[
                    { title: 'Proposal QCC', href: proposalsIndex.url() },
                    {
                        title: project ? 'Lanjutkan Draft' : 'Buat Proposal',
                        href: project
                            ? proposalsIndex.url()
                            : proposalCreate.url(),
                    },
                ]}
                title={
                    project ? 'Lanjutkan Draft Proposal' : 'Buat Proposal QCC'
                }
                description="Isi proposal, tentukan kebutuhan kompetensi, lalu ajukan. Owner QCC akan menyeleksi proposal dan menyusun tim."
                actions={[
                    <Button
                        key="drafts"
                        variant="outline"
                        render={<Link href={proposalsIndex.url()} />}
                        nativeButton={false}
                    >
                        <ArrowLeft data-icon="inline-start" /> Draft Saya
                    </Button>,
                ]}
            />

            {batches.length === 0 && !project ? (
                <Card>
                    <CardContent className="pt-0">
                        <Empty className="min-h-48 border">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <FileText aria-hidden="true" />
                                </EmptyMedia>
                                <EmptyTitle>Belum ada batch aktif</EmptyTitle>
                                <EmptyDescription>
                                    Proposal baru bisa dibuat setelah batch QCC
                                    dibuka.
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    </CardContent>
                </Card>
            ) : (
                <div className="grid gap-5">
                    <Card className="overflow-hidden border-border/70">
                        <CardHeader className="gap-2 border-b bg-muted/25 px-5 py-5 sm:px-7">
                            <div className="flex items-start justify-between gap-4">
                                <div className="grid gap-1.5">
                                    <CardTitle className="text-xl sm:text-2xl">
                                        Informasi proposal
                                    </CardTitle>
                                    <CardDescription className="max-w-2xl">
                                        Lengkapi bagian yang tersedia. Draft
                                        dapat disimpan sebelum semua bagian
                                        selesai.
                                    </CardDescription>
                                </div>
                                <Badge
                                    variant={
                                        isEditing ? 'warning' : 'secondary'
                                    }
                                    className="shrink-0"
                                >
                                    {isEditing ? 'Draft' : 'Belum disimpan'}
                                </Badge>
                            </div>
                        </CardHeader>
                        <CardContent className="grid gap-7 px-5 py-6 sm:px-7 sm:py-8">
                            <WizardStepper
                                steps={steps.map(({ number, label }) => ({
                                    number,
                                    label,
                                }))}
                                current={activeStep}
                                reached={Math.max(
                                    project?.wizard_step ?? 1,
                                    activeStep,
                                )}
                                onSelect={setActiveStep}
                                label="Tahapan proposal"
                            />
                            {errorItems.length > 0 && (
                                <div
                                    ref={errorSummaryRef}
                                    tabIndex={-1}
                                    role="alert"
                                    className="grid gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive outline-none"
                                >
                                    <p className="flex items-center gap-2 font-semibold">
                                        <TriangleAlert
                                            aria-hidden="true"
                                            className="size-4"
                                        />
                                        Belum bisa lanjut. Periksa{' '}
                                        {errorItems.length} isian berikut:
                                    </p>
                                    <ul className="grid gap-1.5">
                                        {errorItems.map(
                                            ({ field, message, location }) => (
                                                <li
                                                    key={field}
                                                    className="flex flex-wrap items-center gap-x-2 gap-y-1"
                                                >
                                                    <span>
                                                        {location &&
                                                            !message
                                                                .toLowerCase()
                                                                .includes(
                                                                    location.label.toLowerCase(),
                                                                ) && (
                                                                <span className="font-medium">
                                                                    {
                                                                        location.label
                                                                    }
                                                                    :{' '}
                                                                </span>
                                                            )}
                                                        {message}
                                                    </span>
                                                    {location &&
                                                        location.step !==
                                                            activeStep && (
                                                            <Button
                                                                type="button"
                                                                variant="link"
                                                                size="sm"
                                                                className="h-auto p-0 text-destructive underline"
                                                                onClick={() =>
                                                                    setActiveStep(
                                                                        location.step,
                                                                    )
                                                                }
                                                            >
                                                                Buka langkah{' '}
                                                                {location.step}
                                                            </Button>
                                                        )}
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                </div>
                            )}
                            <div className="grid gap-5 border-b pb-7">
                                <div className="grid gap-1">
                                    <p className="text-xs font-medium text-primary">
                                        Langkah {activeStep} dari {steps.length}
                                    </p>
                                    <h2 className="text-base font-semibold text-foreground">
                                        {steps[activeStep - 1].label}
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        {steps[activeStep - 1].description}
                                    </p>
                                </div>
                                {renderStepFields(activeStep)}
                            </div>
                        </CardContent>
                        <CardFooter className="sticky bottom-0 z-20 flex flex-col gap-3 border-t border-border/50 bg-background/95 px-5 py-4 shadow-[0_-3px_10px_-8px_var(--foreground)] backdrop-blur supports-[backdrop-filter]:bg-background/85 sm:px-7">
                            <p
                                className="text-xs text-muted-foreground"
                                aria-live="polite"
                            >
                                {footerHint}
                            </p>
                            <div className="flex w-full flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div className="flex gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="h-10 px-4"
                                        disabled={
                                            activeStep === 1 || form.processing
                                        }
                                        onClick={() =>
                                            setActiveStep((current) =>
                                                Math.max(current - 1, 1),
                                            )
                                        }
                                    >
                                        <ChevronLeft data-icon="inline-start" />
                                        Sebelumnya
                                    </Button>
                                    {activeStep < steps.length && (
                                        <Button
                                            type="button"
                                            className="h-10 px-4"
                                            disabled={
                                                !canSaveDraft ||
                                                form.processing ||
                                                requirementsDirty
                                            }
                                            onClick={() => saveDraft(true)}
                                        >
                                            Lanjut
                                            <ChevronRight data-icon="inline-end" />
                                        </Button>
                                    )}
                                </div>
                                <div className="flex flex-col-reverse gap-2 sm:ml-auto sm:flex-row">
                                    <Button
                                        type="button"
                                        variant={
                                            activeStep === steps.length
                                                ? 'outline'
                                                : 'default'
                                        }
                                        className="h-10 px-4"
                                        disabled={
                                            !canSaveDraft ||
                                            form.processing ||
                                            requirementsDirty
                                        }
                                        onClick={() => saveDraft(false)}
                                    >
                                        {form.processing ? (
                                            <Spinner />
                                        ) : (
                                            <Save data-icon="inline-start" />
                                        )}
                                        Simpan Draft
                                    </Button>
                                    {project?.can_submit &&
                                        activeStep === steps.length && (
                                            <Button
                                                type="button"
                                                className="h-10 px-5"
                                                disabled={
                                                    submitting ||
                                                    form.processing ||
                                                    form.isDirty ||
                                                    requirementsDirty
                                                }
                                                onClick={submitProposal}
                                            >
                                                {submitting ? (
                                                    <Spinner />
                                                ) : (
                                                    <CheckCircle2 data-icon="inline-start" />
                                                )}
                                                Ajukan Proposal
                                            </Button>
                                        )}
                                </div>
                            </div>
                            {project?.can_submit &&
                                activeStep === steps.length &&
                                form.isDirty && (
                                    <p className="text-xs text-muted-foreground">
                                        Simpan draft dulu sebelum mengajukan.
                                    </p>
                                )}
                        </CardFooter>
                    </Card>
                </div>
            )}
        </div>
    );
}

function AttachmentList({
    attachments,
    onRemove,
}: {
    attachments: ExistingAttachment[];
    onRemove: (attachment: ExistingAttachment) => void;
}): React.ReactNode {
    return (
        <div className="grid gap-3">
            <h3 className="text-sm font-medium">Lampiran tersimpan</h3>
            {attachments.map((attachment) => (
                <div
                    key={attachment.id}
                    className="flex min-w-0 items-center gap-3 rounded-xl border bg-card p-3"
                >
                    <FileText
                        aria-hidden="true"
                        className="size-5 shrink-0 text-muted-foreground"
                    />
                    <div className="min-w-0 flex-1">
                        {attachment.preview_url ? (
                            <a
                                href={attachment.preview_url}
                                target="_blank"
                                rel="noreferrer"
                                className="truncate text-sm font-medium hover:text-primary hover:underline"
                            >
                                {attachment.original_name}
                            </a>
                        ) : (
                            <span className="truncate text-sm font-medium">
                                {attachment.original_name}
                            </span>
                        )}
                        <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                            <span>{fileSize(attachment.size_bytes)}</span>
                            <Badge
                                variant={
                                    attachment.scan_status === 'PASSED'
                                        ? 'success'
                                        : attachment.scan_status ===
                                            'PENDING_SCAN'
                                          ? 'outline'
                                          : 'destructive'
                                }
                            >
                                {attachmentStatusLabels[
                                    attachment.scan_status
                                ] ?? attachment.scan_status}
                            </Badge>
                        </div>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        aria-label={`Hapus ${attachment.original_name}`}
                        onClick={() => onRemove(attachment)}
                    >
                        <Trash2 aria-hidden="true" />
                    </Button>
                </div>
            ))}
        </div>
    );
}

function PreviewBlock({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}): React.ReactNode {
    return (
        <div className="grid min-w-0 gap-1.5 rounded-lg border border-border/70 bg-muted/20 p-4">
            <dt className="text-xs font-semibold text-muted-foreground">
                {label}
            </dt>
            <dd className="text-sm leading-6 break-words">{children}</dd>
        </div>
    );
}

function PreviewValue({
    label,
    value,
    plain = false,
}: {
    label: string;
    value: string;
    plain?: boolean;
}): React.ReactNode {
    return (
        <PreviewBlock label={label}>
            {plain ? (
                <span className="whitespace-pre-wrap">
                    {value.trim() || 'Belum diisi'}
                </span>
            ) : (
                <FormattedText value={value} empty="Belum diisi" />
            )}
        </PreviewBlock>
    );
}
