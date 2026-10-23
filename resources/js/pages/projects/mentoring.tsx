import { Head, router, useForm } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import {
    Download,
    FileText,
    Plus,
    Send,
    Trash2,
    UserMinus,
    UserPlus,
} from 'lucide-react';
import { useState } from 'react';
import { FormField } from '@/components/form-field';
import { DatePicker } from '@/components/date-picker';
import { PageHeader } from '@/components/page-header';
import { CsvExportButton } from '@/components/csv-export-button';
import { EmptyState } from '@/components/ui-states';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { BadgeVariant } from '@/lib/badge-variants';
import { dashboard } from '@/routes';
import {
    acknowledge,
    assign,
    index,
    store,
    submit,
    update,
    exportMethod as exportMentoringCsv,
} from '@/routes/projects/mentoring';
import { end } from '@/routes/projects/mentoring/assignments';

type Assignment = {
    id: number;
    mentor: string | null;
    status: string;
    assigned_at: string;
    ended_at: string | null;
};
type Report = {
    id: number;
    session_at: string;
    mentor: string | null;
    topic: string;
    findings: string | null;
    suggestions: string | null;
    next_steps: string | null;
    status: string;
    status_label: string;
    team_response: string | null;
    actions: {
        id: number;
        title: string;
        description: string | null;
        pic_id: number;
        pic: string | null;
        due_date: string;
        status: string;
    }[];
    evidence: {
        id: number;
        title: string;
        original_name: string;
        scan_status: string;
        download_url: string | null;
    }[];
    can_edit: boolean;
    can_acknowledge: boolean;
};

type Props = {
    project: { id: number; code: string; title: string };
    assignments: Assignment[];
    reports: Report[];
    members: { value: string; label: string }[];
    mentor_options: { value: string; label: string }[];
    can_assign: boolean;
    can_write: boolean;
};

const variants: Record<string, BadgeVariant> = {
    DRAFT: 'secondary',
    SUBMITTED: 'violet',
    ACKNOWLEDGED: 'success',
};

function when(value: string): string {
    return format(parseISO(value), 'd MMM yyyy, HH:mm', {
        locale: indonesianLocale,
    });
}

function SessionForm({
    projectId,
    editing,
    members,
    onClose,
}: {
    projectId: number;
    editing: Report | null;
    members: Props['members'];
    onClose: () => void;
}) {
    const form = useForm({
        session_at: editing
            ? format(parseISO(editing.session_at), "yyyy-MM-dd'T'HH:mm")
            : '',
        topic: editing?.topic ?? '',
        findings: editing?.findings ?? '',
        suggestions: editing?.suggestions ?? '',
        next_steps: editing?.next_steps ?? '',
        actions: (editing?.actions ?? []).map(
            ({ id, title, description, pic_id, due_date }) => ({
                ...(id ? { id } : {}),
                title,
                description: description ?? '',
                pic_id,
                due_date,
            }),
        ) as {
            id?: number;
            title: string;
            description: string;
            pic_id: number;
            due_date: string;
        }[],
    });
    const errors = form.errors as Record<string, string | undefined>;

    return (
        <form
            className="grid gap-6"
            onSubmit={(event) => {
                event.preventDefault();
                const options = { preserveScroll: true, onSuccess: onClose };

                if (editing) {
                    form.patch(
                        update.url({ project: projectId, report: editing.id }),
                        options,
                    );
                } else {
                    form.post(store.url(projectId), options);
                }
            }}
        >
            <DialogHeader>
                <DialogTitle>
                    {editing ? 'Ubah sesi mentoring' : 'Catat sesi mentoring'}
                </DialogTitle>
            </DialogHeader>
            {errors.report && (
                <Alert variant="destructive">
                    <AlertDescription>{errors.report}</AlertDescription>
                </Alert>
            )}
            <div className="grid gap-6 sm:grid-cols-2">
                <FormField
                    id="session_at"
                    label="Waktu sesi"
                    required
                    error={form.errors.session_at}
                >
                    <Input
                        type="datetime-local"
                        value={form.data.session_at}
                        onChange={(e) =>
                            form.setData('session_at', e.target.value)
                        }
                        className="h-10"
                    />
                </FormField>
                <FormField
                    id="topic"
                    label="Topik"
                    required
                    error={form.errors.topic}
                >
                    <Input
                        value={form.data.topic}
                        onChange={(e) => form.setData('topic', e.target.value)}
                        placeholder="Apa yang dibahas?"
                        maxLength={255}
                        className="h-10"
                    />
                </FormField>
            </div>
            <FormField
                id="findings"
                label="Temuan"
                required
                error={form.errors.findings}
            >
                <Textarea
                    value={form.data.findings}
                    onChange={(e) => form.setData('findings', e.target.value)}
                    placeholder="Apa yang ditemukan?"
                    className="min-h-24"
                />
            </FormField>
            <FormField
                id="suggestions"
                label="Saran"
                required
                error={form.errors.suggestions}
            >
                <Textarea
                    value={form.data.suggestions}
                    onChange={(e) =>
                        form.setData('suggestions', e.target.value)
                    }
                    placeholder="Apa yang disarankan?"
                    className="min-h-24"
                />
            </FormField>
            <FormField
                id="next_steps"
                label="Tindak lanjut"
                error={form.errors.next_steps}
            >
                <Textarea
                    value={form.data.next_steps}
                    onChange={(e) => form.setData('next_steps', e.target.value)}
                    placeholder="Langkah berikutnya untuk tim"
                    className="min-h-20"
                />
            </FormField>
            <fieldset className="grid gap-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <legend className="text-sm font-medium">Action item</legend>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            form.setData('actions', [
                                ...form.data.actions,
                                {
                                    title: '',
                                    description: '',
                                    pic_id: 0,
                                    due_date: '',
                                },
                            ])
                        }
                    >
                        <Plus data-icon="inline-start" aria-hidden="true" />
                        Tambah tindakan
                    </Button>
                </div>
                {form.data.actions.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Belum ada action item.
                    </p>
                ) : (
                    form.data.actions.map((action, index) => (
                        <div
                            key={action.id ?? index}
                            className="grid gap-3 rounded-lg border p-3 sm:grid-cols-2"
                        >
                            <FormField
                                id={`actions-${index}-title`}
                                label="Tindakan"
                                required
                                error={errors[`actions.${index}.title`]}
                            >
                                <Textarea
                                    value={action.title}
                                    onChange={(event) =>
                                        form.setData(
                                            'actions',
                                            form.data.actions.map(
                                                (item, itemIndex) =>
                                                    itemIndex === index
                                                        ? {
                                                              ...item,
                                                              title: event
                                                                  .target.value,
                                                          }
                                                        : item,
                                            ),
                                        )
                                    }
                                    placeholder="Apa tindak lanjutnya?"
                                    className="min-h-16"
                                />
                            </FormField>
                            <FormField
                                id={`actions-${index}-pic`}
                                label="PIC"
                                required
                                error={errors[`actions.${index}.pic_id`]}
                            >
                                <select
                                    value={action.pic_id || ''}
                                    onChange={(event) =>
                                        form.setData(
                                            'actions',
                                            form.data.actions.map(
                                                (item, itemIndex) =>
                                                    itemIndex === index
                                                        ? {
                                                              ...item,
                                                              pic_id: Number(
                                                                  event.target
                                                                      .value,
                                                              ),
                                                          }
                                                        : item,
                                            ),
                                        )
                                    }
                                    className="h-9 w-full rounded-lg border border-input bg-background px-3 text-sm"
                                >
                                    <option value="" disabled>
                                        Pilih PIC
                                    </option>
                                    {members.map((member) => (
                                        <option
                                            key={member.value}
                                            value={member.value}
                                        >
                                            {member.label}
                                        </option>
                                    ))}
                                </select>
                            </FormField>
                            <FormField
                                id={`actions-${index}-description`}
                                label="Catatan"
                            >
                                <Textarea
                                    value={action.description}
                                    onChange={(event) =>
                                        form.setData(
                                            'actions',
                                            form.data.actions.map(
                                                (item, itemIndex) =>
                                                    itemIndex === index
                                                        ? {
                                                              ...item,
                                                              description:
                                                                  event.target
                                                                      .value,
                                                          }
                                                        : item,
                                            ),
                                        )
                                    }
                                    placeholder="Detail tindakan (opsional)"
                                    className="min-h-16"
                                />
                            </FormField>
                            <div className="flex items-end gap-2">
                                <div className="min-w-0 flex-1">
                                    <FormField
                                        id={`actions-${index}-due-date`}
                                        label="Tenggat"
                                        required
                                        error={
                                            errors[`actions.${index}.due_date`]
                                        }
                                    >
                                        <DatePicker
                                            value={action.due_date || null}
                                            onValueChange={(value) =>
                                                form.setData(
                                                    'actions',
                                                    form.data.actions.map(
                                                        (item, itemIndex) =>
                                                            itemIndex === index
                                                                ? {
                                                                      ...item,
                                                                      due_date:
                                                                          value ??
                                                                          '',
                                                                  }
                                                                : item,
                                                    ),
                                                )
                                            }
                                            placeholder="Pilih tenggat"
                                        />
                                    </FormField>
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Hapus tindakan"
                                    onClick={() =>
                                        form.setData(
                                            'actions',
                                            form.data.actions.filter(
                                                (_, itemIndex) =>
                                                    itemIndex !== index,
                                            ),
                                        )
                                    }
                                >
                                    <Trash2 aria-hidden="true" />
                                </Button>
                            </div>
                        </div>
                    ))
                )}
            </fieldset>
            <DialogFooter>
                <Button type="button" variant="outline" onClick={onClose}>
                    Batal
                </Button>
                <Button
                    type="submit"
                    variant="outline"
                    disabled={form.processing}
                >
                    {form.processing && <Spinner data-icon="inline-start" />}
                    Simpan Draft
                </Button>
                {editing && (
                    <Button
                        type="button"
                        disabled={form.processing}
                        onClick={() =>
                            form.post(
                                submit.url({
                                    project: projectId,
                                    report: editing.id,
                                }),
                                { preserveScroll: true, onSuccess: onClose },
                            )
                        }
                    >
                        <Send data-icon="inline-start" aria-hidden="true" />
                        Kirim ke Tim
                    </Button>
                )}
            </DialogFooter>
        </form>
    );
}

function AckForm({
    projectId,
    report,
    onClose,
}: {
    projectId: number;
    report: Report;
    onClose: () => void;
}) {
    const form = useForm({ team_response: '' });

    return (
        <form
            className="grid gap-6"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(
                    acknowledge.url({ project: projectId, report: report.id }),
                    { preserveScroll: true, onSuccess: onClose },
                );
            }}
        >
            <DialogHeader>
                <DialogTitle>Tanggapi feedback: {report.topic}</DialogTitle>
            </DialogHeader>
            <FormField
                id="team_response"
                label="Tanggapan tim"
                required
                error={form.errors.team_response}
            >
                <Textarea
                    value={form.data.team_response}
                    onChange={(e) =>
                        form.setData('team_response', e.target.value)
                    }
                    placeholder="Apa yang akan dilakukan tim?"
                    className="min-h-28"
                />
            </FormField>
            <DialogFooter>
                <Button type="button" variant="outline" onClick={onClose}>
                    Batal
                </Button>
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner data-icon="inline-start" />}
                    Kirim Tanggapan
                </Button>
            </DialogFooter>
        </form>
    );
}

export default function ProjectMentoring({
    project,
    assignments,
    reports,
    members,
    mentor_options: mentorOptions,
    can_assign: canAssign,
    can_write: canWrite,
}: Props) {
    const [dialog, setDialog] = useState<{
        mode: 'session' | 'ack';
        report: Report | null;
    } | null>(null);
    const [mentorId, setMentorId] = useState('');
    const [assignError, setAssignError] = useState<string | null>(null);

    return (
        <>
            <Head title="Mentoring" />
            <div className="mx-auto w-full max-w-[1440px] px-4 py-6 md:px-6 md:py-8">
                <PageHeader
                    breadcrumbs={[
                        { title: 'Dashboard', href: dashboard() },
                        { title: 'Mentoring', href: index.url(project.id) },
                    ]}
                    title="Mentoring"
                    description={`${project.code} · ${project.title}`}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <CsvExportButton
                                href={exportMentoringCsv.url({
                                    project: project.id,
                                })}
                            />
                            {canWrite ? (
                                <Button
                                    className="h-10 px-4 text-sm"
                                    onClick={() =>
                                        setDialog({
                                            mode: 'session',
                                            report: null,
                                        })
                                    }
                                >
                                    <Plus
                                        data-icon="inline-start"
                                        aria-hidden="true"
                                    />
                                    Catat Sesi
                                </Button>
                            ) : null}
                        </div>
                    }
                />

                <Card className="mb-6 gap-0 py-0">
                    <CardContent className="grid gap-4 p-5">
                        <p className="text-sm font-medium">Mentor proyek</p>
                        {assignments.length === 0 ? (
                            <p className="text-[13px] text-muted-foreground">
                                Belum ada mentor yang ditugaskan.
                            </p>
                        ) : (
                            <ul className="grid gap-2">
                                {assignments.map((item) => (
                                    <li
                                        key={item.id}
                                        className="flex items-center justify-between gap-3 rounded-lg border p-3"
                                    >
                                        <div className="grid">
                                            <p className="text-[13px] font-medium">
                                                {item.mentor}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Ditugaskan{' '}
                                                {when(item.assigned_at)}
                                                {item.ended_at &&
                                                    ` · berakhir ${when(item.ended_at)}`}
                                            </p>
                                        </div>
                                        <Badge
                                            variant={
                                                item.status === 'ACTIVE'
                                                    ? 'success'
                                                    : 'secondary'
                                            }
                                        >
                                            {item.status === 'ACTIVE'
                                                ? 'Aktif'
                                                : 'Berakhir'}
                                        </Badge>
                                        {canAssign &&
                                            item.status === 'ACTIVE' && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        router.delete(
                                                            end.url({
                                                                project:
                                                                    project.id,
                                                                assignment:
                                                                    item.id,
                                                            }),
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <UserMinus
                                                        data-icon="inline-start"
                                                        aria-hidden="true"
                                                    />
                                                    Akhiri
                                                </Button>
                                            )}
                                    </li>
                                ))}
                            </ul>
                        )}
                        {canAssign && (
                            <div className="flex flex-wrap items-end gap-3">
                                <div className="grid gap-1.5">
                                    <Select
                                        value={mentorId}
                                        onValueChange={(next) =>
                                            setMentorId(next ?? '')
                                        }
                                        items={mentorOptions}
                                    >
                                        <SelectTrigger
                                            aria-label="Pilih mentor"
                                            className="w-64"
                                        >
                                            <SelectValue placeholder="Pilih mentor" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {mentorOptions.map((option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {assignError && (
                                        <p className="text-[13px] text-destructive">
                                            {assignError}
                                        </p>
                                    )}
                                </div>
                                <Button
                                    disabled={mentorId === ''}
                                    onClick={() =>
                                        router.post(
                                            assign.url(project.id),
                                            { mentor_id: mentorId },
                                            {
                                                preserveScroll: true,
                                                onSuccess: () =>
                                                    setMentorId(''),
                                                onError: (errors) =>
                                                    setAssignError(
                                                        errors.mentor_id ??
                                                            null,
                                                    ),
                                            },
                                        )
                                    }
                                >
                                    <UserPlus
                                        data-icon="inline-start"
                                        aria-hidden="true"
                                    />
                                    Tugaskan
                                </Button>
                            </div>
                        )}
                        <p className="text-xs text-muted-foreground">
                            Mentor dapat meninjau, meminta revisi, dan
                            menyetujui 8 Step pada proyek bimbingan aktif.
                            Persetujuan proposal dan scoring tetap oleh reviewer
                            QCC.
                        </p>
                    </CardContent>
                </Card>

                {reports.length === 0 ? (
                    <div className="rounded-xl border border-dashed">
                        <EmptyState
                            icon={FileText}
                            title="Belum ada sesi mentoring"
                            description="Catatan sesi dan feedback mentor akan muncul di sini."
                        />
                    </div>
                ) : (
                    <ul className="grid gap-3">
                        {reports.map((report) => (
                            <li key={report.id}>
                                <Card className="gap-0 py-0">
                                    <CardContent className="grid gap-3 p-5">
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div className="grid gap-0.5">
                                                <p className="text-sm font-semibold">
                                                    {report.topic}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {when(report.session_at)} ·{' '}
                                                    {report.mentor}
                                                </p>
                                            </div>
                                            <Badge
                                                variant={
                                                    variants[report.status] ??
                                                    'secondary'
                                                }
                                            >
                                                {report.status_label}
                                            </Badge>
                                        </div>
                                        {[
                                            ['Temuan', report.findings],
                                            ['Saran', report.suggestions],
                                            [
                                                'Tindak lanjut',
                                                report.next_steps,
                                            ],
                                            [
                                                'Tanggapan tim',
                                                report.team_response,
                                            ],
                                        ].map(
                                            ([label, value]) =>
                                                value && (
                                                    <p
                                                        key={label}
                                                        className="text-[13px] whitespace-pre-line"
                                                    >
                                                        <span className="font-medium">
                                                            {label}:
                                                        </span>{' '}
                                                        {value}
                                                    </p>
                                                ),
                                        )}
                                        {report.actions.length > 0 && (
                                            <div className="grid gap-2">
                                                <p className="text-[13px] font-medium">
                                                    Action item
                                                </p>
                                                {report.actions.map(
                                                    (action) => (
                                                        <div
                                                            key={action.id}
                                                            className="rounded-lg border px-3 py-2.5"
                                                        >
                                                            <p className="text-sm font-medium">
                                                                {action.title}
                                                            </p>
                                                            {action.description && (
                                                                <p className="text-xs text-muted-foreground">
                                                                    {
                                                                        action.description
                                                                    }
                                                                </p>
                                                            )}
                                                            <p className="mt-1 text-xs text-muted-foreground">
                                                                PIC {action.pic}{' '}
                                                                · tenggat{' '}
                                                                {format(
                                                                    parseISO(
                                                                        action.due_date,
                                                                    ),
                                                                    'd MMM yyyy',
                                                                    {
                                                                        locale: indonesianLocale,
                                                                    },
                                                                )}{' '}
                                                                ·{' '}
                                                                {action.status}
                                                            </p>
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        )}
                                        {report.evidence.length > 0 && (
                                            <div className="grid gap-2">
                                                <p className="text-[13px] font-medium">
                                                    Evidence
                                                </p>
                                                {report.evidence.map((item) => (
                                                    <div
                                                        key={item.id}
                                                        className="flex flex-wrap items-center justify-between gap-3 rounded-lg border px-3 py-2.5"
                                                    >
                                                        <div className="flex min-w-0 items-center gap-2.5">
                                                            <FileText
                                                                className="size-4 shrink-0 text-muted-foreground"
                                                                aria-hidden="true"
                                                            />
                                                            <span className="truncate text-sm">
                                                                {item.title} ·{' '}
                                                                {
                                                                    item.original_name
                                                                }
                                                            </span>
                                                        </div>
                                                        {item.download_url ? (
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
                                                            >
                                                                <Download
                                                                    data-icon="inline-start"
                                                                    aria-hidden="true"
                                                                />
                                                                Unduh
                                                            </Button>
                                                        ) : (
                                                            <Badge variant="secondary">
                                                                Pemeriksaan{' '}
                                                                {item.scan_status.toLowerCase()}
                                                            </Badge>
                                                        )}
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                        {report.evidence.length > 0 && (
                                            <div className="grid gap-2">
                                                <p className="text-[13px] font-medium">
                                                    Evidence
                                                </p>
                                                {report.evidence.map((item) => (
                                                    <div
                                                        key={item.id}
                                                        className="flex flex-wrap items-center justify-between gap-3 rounded-lg border px-3 py-2.5"
                                                    >
                                                        <div className="flex min-w-0 items-center gap-2.5">
                                                            <FileText
                                                                className="size-4 shrink-0 text-muted-foreground"
                                                                aria-hidden="true"
                                                            />
                                                            <span className="truncate text-sm">
                                                                {item.title} ·{' '}
                                                                {
                                                                    item.original_name
                                                                }
                                                            </span>
                                                        </div>
                                                        {item.download_url ? (
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
                                                            >
                                                                <Download
                                                                    data-icon="inline-start"
                                                                    aria-hidden="true"
                                                                />
                                                                Unduh
                                                            </Button>
                                                        ) : (
                                                            <Badge variant="secondary">
                                                                Pemeriksaan{' '}
                                                                {item.scan_status.toLowerCase()}
                                                            </Badge>
                                                        )}
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                        <div className="flex gap-2">
                                            {report.can_edit && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        setDialog({
                                                            mode: 'session',
                                                            report,
                                                        })
                                                    }
                                                >
                                                    Ubah
                                                </Button>
                                            )}
                                            {report.can_acknowledge && (
                                                <Button
                                                    size="sm"
                                                    onClick={() =>
                                                        setDialog({
                                                            mode: 'ack',
                                                            report,
                                                        })
                                                    }
                                                >
                                                    Tanggapi
                                                </Button>
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            <Dialog
                open={dialog !== null}
                onOpenChange={(open) => !open && setDialog(null)}
            >
                <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-xl">
                    {dialog?.mode === 'session' && (
                        <SessionForm
                            key={dialog.report?.id ?? 'new'}
                            projectId={project.id}
                            editing={dialog.report}
                            members={members}
                            onClose={() => setDialog(null)}
                        />
                    )}
                    {dialog?.mode === 'ack' && dialog.report && (
                        <AckForm
                            key={dialog.report.id}
                            projectId={project.id}
                            report={dialog.report}
                            onClose={() => setDialog(null)}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
