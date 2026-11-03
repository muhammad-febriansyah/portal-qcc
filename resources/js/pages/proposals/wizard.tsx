import { Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    FileText,
    Save,
    Trash2,
    UsersRound,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { FileUploader, type UploadItem } from '@/components/file-uploader';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { RichTextarea } from '@/components/rich-textarea';
import { WizardStepper } from '@/components/wizard-stepper';
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
import {
    ProjectRequirementsField,
    type ProjectRequirement,
    type RequirementCompetencyOption,
} from '@/components/project-requirements-field';
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
    candidates as proposalCandidates,
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

type BatchOption = { id: number; name: string };
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
    background: string | null;
    problem_statement: string | null;
    current_situation: string | null;
    baseline: string | null;
    target: string | null;
    scope: string | null;
    learning_goals: string[];
    rca: string | null;
    improvement_plan: string | null;
    business_benefit: string | null;
    supplementary_information: string | null;
    wizard_step: number;
    updated_at: string | null;
    attachments: ExistingAttachment[];
    can_submit: boolean;
    can_find_candidates: boolean;
    team_confirmed: boolean;
    team_status_message: string | null;
};
type Props = {
    project: ProjectDraft | null;
    step: number;
    batches: BatchOption[];
    drafts: DraftOption[];
    competencyOptions?: RequirementCompetencyOption[];
    requirements?: ProjectRequirement[];
    levelOptions?: { value: number; label: string }[];
    attachmentLimit?: { maxFiles: number; maxSizeMb: number };
};
type DraftForm = {
    batch_id: string;
    title: string;
    theme: string;
    background: string;
    problem_statement: string;
    current_situation: string;
    baseline: string;
    target: string;
    scope: string;
    learning_goals: string[];
    rca: string;
    improvement_plan: string;
    business_benefit: string;
    supplementary_information: string;
    wizard_step: number;
    advance: boolean;
};
type DraftField = Exclude<
    keyof DraftForm,
    'batch_id' | 'learning_goals' | 'wizard_step' | 'advance'
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
            'Tentukan judul, batch, dan tema perbaikan yang kamu ajukan.',
    },
    {
        number: 2,
        label: 'Masalah & Baseline',
        description:
            'Jelaskan latar belakang, masalahnya, kondisi saat ini, dan data awal sebagai pembanding.',
    },
    {
        number: 3,
        label: 'Target & Manfaat',
        description:
            'Tetapkan target dan ruang lingkup, analisis akar masalah, rencana perbaikan, dan manfaatnya.',
    },
    {
        number: 4,
        label: 'Kebutuhan Kompetensi',
        description:
            'Tulis tiga hal yang ingin dipelajari tim dan kompetensi yang dibutuhkan proyek ini.',
    },
    {
        number: 5,
        label: 'Lampiran & Review',
        description:
            'Unggah lampiran pendukung, lalu periksa ringkasan sebelum proposal diajukan.',
    },
];

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

function initialData(project: ProjectDraft | null, step: number): DraftForm {
    return {
        batch_id: project ? String(project.batch_id) : '',
        title: project?.title ?? '',
        theme: project?.theme ?? '',
        background: project?.background ?? '',
        problem_statement: project?.problem_statement ?? '',
        current_situation: project?.current_situation ?? '',
        baseline: project?.baseline ?? '',
        target: project?.target ?? '',
        scope: project?.scope ?? '',
        learning_goals:
            project?.learning_goals?.length === 3
                ? project.learning_goals.map((goal) => goal ?? '')
                : ['', '', ''],
        rca: project?.rca ?? '',
        improvement_plan: project?.improvement_plan ?? '',
        business_benefit: project?.business_benefit ?? '',
        supplementary_information: project?.supplementary_information ?? '',
        wizard_step: project?.wizard_step ?? step,
        advance: false,
    };
}

function fileSize(bytes: number): string {
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export default function ProposalWizard({
    project,
    step,
    batches,
    drafts: _drafts,
    competencyOptions = [],
    requirements = [],
    levelOptions = [],
    attachmentLimit = { maxFiles: 5, maxSizeMb: 50 },
}: Props) {
    const [requirementsDirty, setRequirementsDirty] = useState(false);
    const [pendingFiles, setPendingFiles] = useState<UploadItem[]>([]);
    const [activeStep, setActiveStep] = useState(project?.wizard_step ?? step);
    const draftSaveInFlight = useRef(false);
    const form = useForm<DraftForm>(initialData(project, step));
    const uploadForm = useForm<{ files: File[] }>({ files: [] });
    const isEditing = project !== null;

    useEffect(() => {
        if (project) {
            form.setData(initialData(project, project.wizard_step));
        }
        // Refresh form after a new draft is created or a persisted draft changes.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [project?.id, project?.wizard_step]);

    const existingAttachments = useMemo(
        () => project?.attachments ?? [],
        [project?.attachments],
    );
    const selectedFiles = pendingFiles.map((item) => item.file);
    const stepError = form.errors.wizard_step;
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
                    setActiveStep((current) => Math.min(current + 1, 5));
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

    function addFiles(files: File[]): void {
        setPendingFiles((current) => [
            ...current,
            ...files.map((file) => ({
                id: `${file.name}-${file.size}-${file.lastModified}-${crypto.randomUUID()}`,
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

    function fieldError(field: keyof DraftForm): string | undefined {
        return form.errors[field] as string | undefined;
    }

    function renderStepFields(stepNumber: number): React.ReactNode {
        if (stepNumber === 1) {
            return (
                <div className="grid gap-6 sm:grid-cols-2">
                    <FormField
                        id="proposal-title"
                        label="Judul Proposal"
                        hint="Singkat dan menggambarkan hasil yang dituju."
                        required
                        error={fieldError('title')}
                    >
                        <Input
                            value={form.data.title}
                            onChange={(event) =>
                                setField('title', event.target.value)
                            }
                            placeholder="Contoh: Mengurangi waktu tunggu proses produksi"
                            maxLength={255}
                            className={controlClassName}
                        />
                    </FormField>
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
                                aria-invalid={Boolean(fieldError('batch_id'))}
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
                    <div className="sm:col-span-2">
                        <FormField
                            id="proposal-theme"
                            label="Tema Improvement"
                            hint="Rangkum perbaikan dalam satu atau dua kalimat."
                            required
                            error={fieldError('theme')}
                        >
                            <Textarea
                                value={form.data.theme}
                                onChange={(event) =>
                                    setField('theme', event.target.value)
                                }
                                placeholder="Tuliskan tema perbaikan yang diajukan"
                                maxLength={5000}
                                className="min-h-32"
                            />
                        </FormField>
                    </div>
                </div>
            );
        }

        if (stepNumber === 2) {
            return (
                <div className="grid gap-6">
                    <FormField
                        id="proposal-background"
                        label="Latar Belakang"
                        hint="Apa yang terjadi, dan mengapa perlu diperbaiki sekarang?"
                        required
                        error={fieldError('background')}
                    >
                        <RichTextarea
                            value={form.data.background}
                            onChange={(value) => setField('background', value)}
                            placeholder="Jelaskan kondisi yang mendorong improvement"
                            className="min-h-28"
                        />
                    </FormField>
                    <FormField
                        id="proposal-problem"
                        label="Pernyataan Masalah"
                        hint="Satu masalah utama yang spesifik dan bisa diukur."
                        required
                        error={fieldError('problem_statement')}
                    >
                        <RichTextarea
                            value={form.data.problem_statement}
                            onChange={(value) =>
                                setField('problem_statement', value)
                            }
                            placeholder="Tuliskan masalah yang ingin diselesaikan secara spesifik"
                            className="min-h-28"
                        />
                    </FormField>
                    <div className="grid gap-6 lg:grid-cols-2">
                        <FormField
                            id="proposal-situation"
                            label="Situasi Saat Ini"
                            hint="Proses dan kondisi yang berjalan sekarang."
                            required
                            error={fieldError('current_situation')}
                        >
                            <RichTextarea
                                value={form.data.current_situation}
                                onChange={(value) =>
                                    setField('current_situation', value)
                                }
                                placeholder="Jelaskan proses dan kondisi saat ini"
                                className="min-h-28"
                            />
                        </FormField>
                        <FormField
                            id="proposal-baseline"
                            label="Baseline"
                            hint="Sertakan angka dan periode pengukurannya."
                            required
                            error={fieldError('baseline')}
                        >
                            <Textarea
                                value={form.data.baseline}
                                onChange={(event) =>
                                    setField('baseline', event.target.value)
                                }
                                placeholder="Masukkan data awal dan periode pengukuran"
                                className="min-h-28"
                            />
                        </FormField>
                    </div>
                </div>
            );
        }

        if (stepNumber === 3) {
            return (
                <div className="grid gap-6">
                    <SectionTitle
                        title="Sasaran proyek"
                        description="Apa yang ingin dicapai dan sejauh mana cakupannya."
                    />
                    <div className="grid gap-6 lg:grid-cols-2">
                        <FormField
                            id="proposal-target"
                            label="Target Improvement"
                            hint="Angka yang ingin dicapai dan cara mengukurnya."
                            required
                            error={fieldError('target')}
                        >
                            <Textarea
                                value={form.data.target}
                                onChange={(event) =>
                                    setField('target', event.target.value)
                                }
                                placeholder="Tuliskan target dan cara mengukurnya"
                                className="min-h-28"
                            />
                        </FormField>
                        <FormField
                            id="proposal-scope"
                            label="Ruang Lingkup"
                            hint="Proses, area, dan periode yang termasuk."
                            required
                            error={fieldError('scope')}
                        >
                            <Textarea
                                value={form.data.scope}
                                onChange={(event) =>
                                    setField('scope', event.target.value)
                                }
                                placeholder="Jelaskan proses, area, atau periode yang termasuk"
                                className="min-h-28"
                            />
                        </FormField>
                    </div>
                    <SectionTitle
                        title="Analisis dan rencana"
                        description="Dari penyebab masalah sampai tindakan dan manfaatnya."
                    />
                    <FormField
                        id="proposal-rca"
                        label="Root Cause Analysis (RCA)"
                        hint="Penyebab utama yang dibuktikan dengan data, bukan dugaan."
                        required
                        error={fieldError('rca')}
                    >
                        <RichTextarea
                            value={form.data.rca}
                            onChange={(value) => setField('rca', value)}
                            placeholder="Tuliskan analisis akar penyebab masalah"
                            className="min-h-28"
                        />
                    </FormField>
                    <FormField
                        id="proposal-improvement"
                        label="Rencana Perbaikan"
                        hint="Tindakan konkret, penanggung jawab, dan jadwalnya."
                        required
                        error={fieldError('improvement_plan')}
                    >
                        <RichTextarea
                            value={form.data.improvement_plan}
                            onChange={(value) =>
                                setField('improvement_plan', value)
                            }
                            placeholder="Jelaskan tindakan perbaikan yang direncanakan"
                            className="min-h-28"
                        />
                    </FormField>
                    <FormField
                        id="proposal-benefit"
                        label="Manfaat Bisnis"
                        hint="Dampak bagi proses, pelanggan, atau perusahaan."
                        required
                        error={fieldError('business_benefit')}
                    >
                        <RichTextarea
                            value={form.data.business_benefit}
                            onChange={(value) =>
                                setField('business_benefit', value)
                            }
                            placeholder="Jelaskan manfaat yang diharapkan bagi organisasi"
                            className="min-h-28"
                        />
                    </FormField>
                </div>
            );
        }

        if (stepNumber === 4) {
            return (
                <div className="grid gap-6">
                    <SectionTitle
                        title="Hal yang ingin dipelajari"
                        description="Wajib diisi tiga. Ini dipakai untuk menilai kecocokan tim."
                    />
                    {form.data.learning_goals.map((goal, index) => (
                        <FormField
                            key={index}
                            id={`learning-goal-${index + 1}`}
                            label={`Hal yang Ingin Dipelajari ${index + 1}`}
                            required
                            error={fieldError(
                                `learning_goals.${index}` as keyof DraftForm,
                            )}
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
                    {project ? (
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
                                Kompetensi dari master dan level minimum dapat
                                ditambahkan setelah draft tersimpan.
                            </CardContent>
                        </Card>
                    )}
                </div>
            );
        }

        return (
            <div className="grid gap-6">
                {!project ? (
                    <Empty className="min-h-40 rounded-xl border">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <FileText aria-hidden="true" />
                            </EmptyMedia>
                            <EmptyTitle>
                                Simpan draft sebelum menambahkan lampiran
                            </EmptyTitle>
                            <EmptyDescription>
                                Selesaikan informasi dasar lalu simpan draft.
                                Setelah itu, kembali ke tahap ini untuk
                                mengunggah file.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <>
                        <SectionTitle
                            title="Informasi dan data pelengkap"
                            description="Ringkasan ini dapat digunakan AI Team Matching. Lampiran dan tautan tidak dibaca otomatis."
                        />
                        <FormField
                            id="proposal-supplementary-information"
                            label="Ringkasan data pendukung"
                            hint="Masukkan informasi yang boleh dipakai untuk rekomendasi tim. Jangan masukkan data pribadi yang tidak diperlukan."
                            error={fieldError('supplementary_information')}
                        >
                            <RichTextarea
                                value={form.data.supplementary_information}
                                onChange={(value) =>
                                    setField('supplementary_information', value)
                                }
                                placeholder="Tambahkan fakta, data, atau konteks proyek yang belum tercakup pada bagian sebelumnya"
                                className="min-h-28"
                            />
                        </FormField>
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
                            <div className="grid gap-3">
                                <h3 className="text-sm font-medium">
                                    Lampiran tersimpan
                                </h3>
                                {existingAttachments.map((attachment) => (
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
                                                    href={
                                                        attachment.preview_url
                                                    }
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
                                                <span>
                                                    {fileSize(
                                                        attachment.size_bytes,
                                                    )}
                                                </span>
                                                <Badge
                                                    variant={
                                                        attachment.scan_status ===
                                                        'PASSED'
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
                                            onClick={() =>
                                                removeAttachment(attachment)
                                            }
                                        >
                                            <Trash2 aria-hidden="true" />
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </>
                )}
                <Card className="bg-muted/20">
                    <CardHeader>
                        <CardTitle>Pratinjau proposal</CardTitle>
                        <CardDescription>
                            Periksa ringkasan sebelum draft disimpan.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2">
                        <PreviewValue label="Judul" value={form.data.title} />
                        <PreviewValue label="Tema" value={form.data.theme} />
                        <PreviewValue
                            label="Masalah"
                            value={form.data.problem_statement}
                        />
                        <PreviewValue
                            label="Baseline"
                            value={form.data.baseline}
                        />
                        <PreviewValue label="Target" value={form.data.target} />
                        <PreviewValue
                            label="Manfaat"
                            value={form.data.business_benefit}
                        />
                        <PreviewValue
                            label="Hal yang dipelajari"
                            value={form.data.learning_goals
                                .filter(Boolean)
                                .join(' · ')}
                        />
                    </CardContent>
                </Card>
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
                description="Isi bagian proposal, simpan sebagai draft, lalu ajukan setelah tim dikonfirmasi."
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
                    <Card className="overflow-hidden border-border/60 shadow-xs">
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
                            {stepError && (
                                <p
                                    role="alert"
                                    className="rounded-lg border border-destructive/30 bg-destructive/5 px-4 py-3 text-sm text-destructive"
                                >
                                    {stepError}
                                </p>
                            )}
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
                            {project?.team_status_message && (
                                <p
                                    className="w-full text-xs text-muted-foreground sm:max-w-md"
                                    role="status"
                                >
                                    {project.team_status_message}
                                </p>
                            )}
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
                                {project?.can_find_candidates &&
                                    !project.team_confirmed && (
                                        <Button
                                            type="button"
                                            className="h-10 px-4 text-white hover:bg-warning/90"
                                            style={{
                                                backgroundColor:
                                                    'var(--warning)',
                                            }}
                                            render={
                                                <Link
                                                    href={proposalCandidates.url(
                                                        project.id,
                                                    )}
                                                />
                                            }
                                            nativeButton={false}
                                        >
                                            <UsersRound data-icon="inline-start" />
                                            Atur Tim
                                        </Button>
                                    )}
                                <div className="flex flex-col-reverse gap-2 sm:ml-auto sm:flex-row">
                                    <Button
                                        type="button"
                                        variant="default"
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
                                    {project?.can_submit && (
                                        <Button
                                            type="button"
                                            className="h-10 px-5"
                                            disabled={
                                                activeStep !== steps.length ||
                                                !project.team_confirmed ||
                                                form.processing ||
                                                form.isDirty ||
                                                requirementsDirty
                                            }
                                            onClick={() =>
                                                router.post(
                                                    proposalSubmit.url(
                                                        project.id,
                                                    ),
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <CheckCircle2 data-icon="inline-start" />
                                            Ajukan
                                        </Button>
                                    )}
                                </div>
                            </div>
                        </CardFooter>
                    </Card>
                </div>
            )}
        </div>
    );
}

function PreviewValue({
    label,
    value,
}: {
    label: string;
    value: string;
}): React.ReactNode {
    return (
        <div className="grid min-w-0 gap-1">
            <span className="text-xs font-medium text-muted-foreground">
                {label}
            </span>
            <span className="text-sm break-words whitespace-pre-wrap">
                {value.trim() || 'Belum diisi'}
            </span>
        </div>
    );
}
