import { Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    FileText,
    Save,
    Trash2,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { FileUploader, type UploadItem } from '@/components/file-uploader';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import {
    create as proposalCreate,
    index as proposalsIndex,
    store as proposalStore,
    update as proposalUpdate,
} from '@/routes/proposals';
import {
    destroy as destroyAttachment,
    preview as previewAttachment,
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
    preview_url: string;
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
    wizard_step: number;
    updated_at: string | null;
    attachments: ExistingAttachment[];
};
type Props = {
    project: ProjectDraft | null;
    step: number;
    batches: BatchOption[];
    drafts: DraftOption[];
    competencyOptions?: RequirementCompetencyOption[];
    requirements?: ProjectRequirement[];
    levelOptions?: { value: number; label: string }[];
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
    wizard_step: number;
    advance: boolean;
};
type DraftField = Exclude<
    keyof DraftForm,
    'batch_id' | 'learning_goals' | 'wizard_step' | 'advance'
>;
type StepDefinition = { value: string; number: number; label: string };

const steps: StepDefinition[] = [
    { value: '1', number: 1, label: 'Informasi Dasar' },
    { value: '2', number: 2, label: 'Masalah & Baseline' },
    { value: '3', number: 3, label: 'Target & Manfaat' },
    { value: '4', number: 4, label: 'Kebutuhan Kompetensi' },
    { value: '5', number: 5, label: 'Lampiran & Review' },
];

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
                ? project.learning_goals
                : ['', '', ''],
        rca: project?.rca ?? '',
        improvement_plan: project?.improvement_plan ?? '',
        business_benefit: project?.business_benefit ?? '',
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
}: Props) {
    const [activeStep, setActiveStep] = useState(step);
    const [requirementsDirty, setRequirementsDirty] = useState(false);
    const [pendingFiles, setPendingFiles] = useState<UploadItem[]>([]);
    const form = useForm<DraftForm>(initialData(project, step));
    const uploadForm = useForm<{ files: File[] }>({ files: [] });
    const isEditing = project !== null;
    const canContinue = isEditing
        ? project.wizard_step >= activeStep
        : activeStep === 1;

    useEffect(() => {
        if (project) {
            form.setData(initialData(project, project.wizard_step));
            setActiveStep(project.wizard_step);
        }
        // Update form after a new draft is created or a persisted step advances.
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
        status: uploadForm.processing ? ('uploading' as const) : item.status,
        progress: uploadForm.progress?.percentage ?? item.progress,
    }));

    function saveDraft(advance: boolean): void {
        form.transform((data) => ({
            ...data,
            wizard_step: activeStep,
            advance,
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                if (advance)
                    setActiveStep((current) => Math.min(current + 1, 5));
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
    }

    function uploadFiles(): void {
        if (!project || selectedFiles.length === 0) return;

        uploadForm.transform(() => ({ files: selectedFiles }));
        uploadForm.post(storeAttachments.url(project.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => setPendingFiles([]),
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

    function setField(field: DraftField, value: string): void {
        form.setData(field, value);
    }

    function fieldError(field: keyof DraftForm): string | undefined {
        return form.errors[field] as string | undefined;
    }

    function renderStepFields(): React.ReactNode {
        if (activeStep === 1) {
            return (
                <div className="grid gap-6 sm:grid-cols-2">
                    <FormField
                        id="proposal-title"
                        label="Judul Proposal"
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
                            className="h-10"
                        />
                    </FormField>
                    <FormField
                        id="proposal-batch"
                        label="Batch QCC"
                        required
                        error={fieldError('batch_id')}
                    >
                        <Select
                            value={form.data.batch_id || undefined}
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
                                className="h-10 w-full"
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
                                className="min-h-24"
                            />
                        </FormField>
                    </div>
                </div>
            );
        }

        if (activeStep === 2) {
            return (
                <div className="grid gap-6">
                    <FormField
                        id="proposal-background"
                        label="Latar Belakang"
                        required
                        error={fieldError('background')}
                    >
                        <Textarea
                            value={form.data.background}
                            onChange={(event) =>
                                setField('background', event.target.value)
                            }
                            placeholder="Jelaskan kondisi yang mendorong improvement"
                            className="min-h-28"
                        />
                    </FormField>
                    <FormField
                        id="proposal-problem"
                        label="Pernyataan Masalah"
                        required
                        error={fieldError('problem_statement')}
                    >
                        <Textarea
                            value={form.data.problem_statement}
                            onChange={(event) =>
                                setField(
                                    'problem_statement',
                                    event.target.value,
                                )
                            }
                            placeholder="Tuliskan masalah yang ingin diselesaikan secara spesifik"
                            className="min-h-28"
                        />
                    </FormField>
                    <div className="grid gap-6 lg:grid-cols-2">
                        <FormField
                            id="proposal-situation"
                            label="Situasi Saat Ini"
                            required
                            error={fieldError('current_situation')}
                        >
                            <Textarea
                                value={form.data.current_situation}
                                onChange={(event) =>
                                    setField(
                                        'current_situation',
                                        event.target.value,
                                    )
                                }
                                placeholder="Jelaskan proses dan kondisi saat ini"
                                className="min-h-28"
                            />
                        </FormField>
                        <FormField
                            id="proposal-baseline"
                            label="Baseline"
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

        if (activeStep === 3) {
            return (
                <div className="grid gap-6">
                    <div className="grid gap-6 lg:grid-cols-2">
                        <FormField
                            id="proposal-target"
                            label="Target Improvement"
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
                    <FormField
                        id="proposal-rca"
                        label="Root Cause Analysis (RCA)"
                        required
                        error={fieldError('rca')}
                    >
                        <Textarea
                            value={form.data.rca}
                            onChange={(event) =>
                                setField('rca', event.target.value)
                            }
                            placeholder="Tuliskan analisis akar penyebab masalah"
                            className="min-h-28"
                        />
                    </FormField>
                    <FormField
                        id="proposal-improvement"
                        label="Rencana Perbaikan"
                        required
                        error={fieldError('improvement_plan')}
                    >
                        <Textarea
                            value={form.data.improvement_plan}
                            onChange={(event) =>
                                setField('improvement_plan', event.target.value)
                            }
                            placeholder="Jelaskan tindakan perbaikan yang direncanakan"
                            className="min-h-28"
                        />
                    </FormField>
                    <FormField
                        id="proposal-benefit"
                        label="Manfaat Bisnis"
                        required
                        error={fieldError('business_benefit')}
                    >
                        <Textarea
                            value={form.data.business_benefit}
                            onChange={(event) =>
                                setField('business_benefit', event.target.value)
                            }
                            placeholder="Jelaskan manfaat yang diharapkan bagi organisasi"
                            className="min-h-28"
                        />
                    </FormField>
                </div>
            );
        }

        if (activeStep === 4) {
            return (
                <div className="grid gap-6">
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
                                className="h-10"
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
                        <FormField
                            id="proposal-attachments"
                            label="Lampiran Proposal"
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
                                accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx"
                                maxFiles={10 - existingAttachments.length}
                                maxFileSizeBytes={50 * 1024 * 1024}
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
                        {selectedFiles.length > 0 && (
                            <div className="flex justify-end">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={uploadFiles}
                                    disabled={uploadForm.processing}
                                >
                                    {uploadForm.processing ? (
                                        <Spinner />
                                    ) : (
                                        <Save data-icon="inline-start" />
                                    )}
                                    {uploadForm.processing
                                        ? 'Mengunggah...'
                                        : 'Simpan Lampiran'}
                                </Button>
                            </div>
                        )}
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
                                            <a
                                                href={previewAttachment.url({
                                                    project: project.id,
                                                    attachment: attachment.id,
                                                })}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="truncate text-sm font-medium hover:text-primary hover:underline"
                                            >
                                                {attachment.original_name}
                                            </a>
                                            <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                                <span>
                                                    {fileSize(
                                                        attachment.size_bytes,
                                                    )}
                                                </span>
                                                <Badge variant="outline">
                                                    Menunggu pemeriksaan
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
                description="Isi proposal bertahap. Draft tersimpan untuk dilanjutkan kapan saja."
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
                <Tabs
                    value={String(activeStep)}
                    onValueChange={(value) =>
                        value && setActiveStep(Number(value))
                    }
                    className="gap-5"
                >
                    <TabsList
                        aria-label="Tahapan proposal"
                        className="grid h-auto w-full grid-cols-2 gap-1 p-1 sm:grid-cols-3 lg:grid-cols-5"
                    >
                        {steps.map((item) => (
                            <TabsTrigger
                                key={item.value}
                                value={item.value}
                                disabled={
                                    item.number > (project?.wizard_step ?? 1)
                                }
                                className="min-h-10 justify-start gap-2 px-3 text-left data-active:bg-background"
                            >
                                <span className="flex size-6 shrink-0 items-center justify-center rounded-full border text-xs">
                                    {item.number <
                                    (project?.wizard_step ?? 1) ? (
                                        <Check aria-hidden="true" />
                                    ) : (
                                        item.number
                                    )}
                                </span>
                                <span className="truncate">{item.label}</span>
                            </TabsTrigger>
                        ))}
                    </TabsList>

                    <TabsContent value={String(activeStep)}>
                        <Card>
                            <CardHeader className="border-b">
                                <div className="flex items-center justify-between gap-4">
                                    <div className="grid gap-1">
                                        <CardTitle>
                                            {steps[activeStep - 1]?.label}
                                        </CardTitle>
                                        <CardDescription>
                                            Tahap {activeStep} dari{' '}
                                            {steps.length}
                                        </CardDescription>
                                    </div>
                                    <Badge variant="outline">Draft</Badge>
                                </div>
                            </CardHeader>
                            <CardContent className="grid gap-6 pt-6">
                                {stepError && (
                                    <p
                                        role="alert"
                                        className="text-sm text-destructive"
                                    >
                                        {stepError}
                                    </p>
                                )}
                                {renderStepFields()}
                            </CardContent>
                            <CardFooter className="flex flex-col-reverse justify-between gap-3 border-t sm:flex-row">
                                <div className="flex w-full gap-2 sm:w-auto">
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
                                        <ArrowLeft data-icon="inline-start" />{' '}
                                        Kembali
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        className="h-10 px-4"
                                        disabled={
                                            form.processing ||
                                            (!isEditing &&
                                                !form.data.title.trim())
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
                                </div>
                                {activeStep < steps.length && (
                                    <Button
                                        type="button"
                                        className="h-10 w-full px-4 sm:w-auto"
                                        disabled={
                                            !canContinue ||
                                            form.processing ||
                                            !form.data.title.trim() ||
                                            (activeStep === 4 &&
                                                requirementsDirty)
                                        }
                                        onClick={() => saveDraft(true)}
                                    >
                                        {form.processing ? <Spinner /> : null}
                                        Simpan & Lanjut
                                        {!form.processing && (
                                            <ArrowRight data-icon="inline-end" />
                                        )}
                                    </Button>
                                )}
                            </CardFooter>
                        </Card>
                    </TabsContent>
                </Tabs>
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
