import { Head, router, useForm } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import {
    Check,
    Download,
    FileText,
    MessageSquareWarning,
    PencilLine,
    Plus,
    Send,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { DatePicker } from '@/components/date-picker';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { CsvExportButton } from '@/components/csv-export-button';
import { EmptyState } from '@/components/ui-states';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { BadgeVariant } from '@/lib/badge-variants';
import { dashboard } from '@/routes';
import {
    approve,
    destroy,
    index,
    revision,
    store,
    submit,
    update,
    exportMethod as exportBiweeklyCsv,
} from '@/routes/projects/biweekly';

type Report = {
    id: number;
    period_no: number;
    starts_on: string;
    ends_on: string;
    meeting_date: string | null;
    agenda: string | null;
    summary: string | null;
    planned: string | null;
    actual: string | null;
    issues: string | null;
    decisions: string | null;
    status: string;
    status_label: string;
    submitted_by: string | null;
    review_note: string | null;
    attendee_ids: number[];
    attendees: string[];
    evidence: {
        id: number;
        title: string;
        original_name: string;
        scan_status: string;
        download_url: string | null;
    }[];
    actions: ActionItem[];
    can_edit: boolean;
    can_submit: boolean;
    can_review: boolean;
};

type ActionItem = {
    id?: number;
    title: string;
    description: string | null;
    pic_id: number;
    pic: string | null;
    due_date: string;
    status: string;
};

type EditableActionItem = Pick<
    ActionItem,
    'title' | 'description' | 'pic_id' | 'due_date'
> & { id?: number };

type Props = {
    project: { id: number; code: string; title: string };
    reports: Report[];
    members: { value: string; label: string }[];
    next_period: number;
    can_create: boolean;
};

const variants: Record<string, BadgeVariant> = {
    DRAFT: 'secondary',
    SUBMITTED: 'violet',
    APPROVED: 'success',
    REVISION_REQUIRED: 'warning',
};

function date(value: string | null): string {
    return value
        ? format(parseISO(value), 'd MMM yyyy', { locale: indonesianLocale })
        : '—';
}

const textFields = [
    ['agenda', 'Agenda', 'Apa yang dibahas?', true],
    ['summary', 'Ringkasan', 'Ringkas hasil pemantauan', true],
    ['planned', 'Rencana', 'Rencana periode ini', true],
    ['actual', 'Aktual', 'Realisasi periode ini', true],
    ['issues', 'Kendala', 'Kendala yang dihadapi', false],
    ['decisions', 'Keputusan', 'Keputusan rapat', false],
] as const;

function ReportForm({
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
        starts_on: editing?.starts_on ?? (null as string | null),
        ends_on: editing?.ends_on ?? (null as string | null),
        meeting_date: editing?.meeting_date ?? (null as string | null),
        agenda: editing?.agenda ?? '',
        summary: editing?.summary ?? '',
        planned: editing?.planned ?? '',
        actual: editing?.actual ?? '',
        issues: editing?.issues ?? '',
        decisions: editing?.decisions ?? '',
        attendee_ids: (editing?.attendee_ids ?? []) as number[],
        actions: (editing?.actions ?? []).map(
            ({ id, title, description, pic_id, due_date }) => ({
                ...(id ? { id } : {}),
                title,
                description: description ?? '',
                pic_id,
                due_date,
            }),
        ) as EditableActionItem[],
    });
    const errors = form.errors as Record<string, string | undefined>;
    const attendeeError =
        errors.attendee_ids ??
        Object.entries(errors).find(([key]) =>
            key.startsWith('attendee_ids.'),
        )?.[1];
    const options = { preserveScroll: true, onSuccess: onClose };

    return (
        <form
            className="grid gap-6"
            onSubmit={(event) => {
                event.preventDefault();
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
                    {editing
                        ? `Ubah laporan periode ${editing.period_no}`
                        : 'Buat laporan bi-weekly'}
                </DialogTitle>
            </DialogHeader>
            {errors.report && (
                <Alert variant="destructive">
                    <AlertDescription>{errors.report}</AlertDescription>
                </Alert>
            )}
            <div className="grid gap-6 sm:grid-cols-3">
                <FormField
                    id="starts_on"
                    label="Awal periode"
                    required
                    error={form.errors.starts_on}
                >
                    <DatePicker
                        value={form.data.starts_on}
                        onValueChange={(v) => form.setData('starts_on', v)}
                        placeholder="Pilih tanggal"
                    />
                </FormField>
                <FormField
                    id="ends_on"
                    label="Akhir periode"
                    required
                    error={form.errors.ends_on}
                >
                    <DatePicker
                        value={form.data.ends_on}
                        onValueChange={(v) => form.setData('ends_on', v)}
                        placeholder="Pilih tanggal"
                    />
                </FormField>
                <FormField
                    id="meeting_date"
                    label="Tanggal rapat"
                    error={form.errors.meeting_date}
                >
                    <DatePicker
                        value={form.data.meeting_date}
                        onValueChange={(v) => form.setData('meeting_date', v)}
                        placeholder="Pilih tanggal"
                    />
                </FormField>
            </div>
            {textFields.map(([field, label, placeholder, required]) => (
                <FormField
                    key={field}
                    id={field}
                    label={label}
                    required={required}
                    error={form.errors[field]}
                >
                    <Textarea
                        value={form.data[field]}
                        onChange={(e) => form.setData(field, e.target.value)}
                        placeholder={placeholder}
                        className="min-h-20"
                    />
                </FormField>
            ))}
            <fieldset className="grid gap-3">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <legend className="text-sm font-medium">
                        Corrective action
                    </legend>
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
                        Belum ada corrective action.
                    </p>
                ) : (
                    <div className="grid gap-3">
                        {form.data.actions.map((action, index) => (
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
                                                                      .target
                                                                      .value,
                                                              }
                                                            : item,
                                                ),
                                            )
                                        }
                                        placeholder="Apa tindakan perbaikannya?"
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
                                                                      event
                                                                          .target
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
                                        value={action.description ?? ''}
                                        onChange={(event) =>
                                            form.setData(
                                                'actions',
                                                form.data.actions.map(
                                                    (item, itemIndex) =>
                                                        itemIndex === index
                                                            ? {
                                                                  ...item,
                                                                  description:
                                                                      event
                                                                          .target
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
                                                errors[
                                                    `actions.${index}.due_date`
                                                ]
                                            }
                                        >
                                            <DatePicker
                                                value={action.due_date || null}
                                                onValueChange={(value) =>
                                                    form.setData(
                                                        'actions',
                                                        form.data.actions.map(
                                                            (
                                                                item,
                                                                itemIndex,
                                                            ) =>
                                                                itemIndex ===
                                                                index
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
                        ))}
                    </div>
                )}
            </fieldset>
            <fieldset className="grid gap-2.5">
                <legend className="text-sm font-medium">
                    Peserta rapat <span className="text-destructive">*</span>
                </legend>
                <div className="grid gap-2 sm:grid-cols-2">
                    {members.map((member) => {
                        const id = Number(member.value);
                        const checked = form.data.attendee_ids.includes(id);

                        return (
                            <Label
                                key={member.value}
                                className="flex items-center gap-2 rounded-lg border p-2.5 font-normal"
                            >
                                <Checkbox
                                    checked={checked}
                                    onCheckedChange={(next) =>
                                        form.setData(
                                            'attendee_ids',
                                            next === true
                                                ? [
                                                      ...form.data.attendee_ids,
                                                      id,
                                                  ]
                                                : form.data.attendee_ids.filter(
                                                      (item) => item !== id,
                                                  ),
                                        )
                                    }
                                />
                                {member.label}
                            </Label>
                        );
                    })}
                </div>
                {attendeeError && (
                    <p className="text-[13px] text-destructive">
                        {attendeeError}
                    </p>
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
                        Ajukan
                    </Button>
                )}
            </DialogFooter>
        </form>
    );
}

function DetailDialog({
    projectId,
    report,
    onClose,
}: {
    projectId: number;
    report: Report;
    onClose: () => void;
}) {
    const [reason, setReason] = useState('');
    const [asking, setAsking] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const target = { project: projectId, report: report.id };
    const post = (url: string, data: Record<string, string> = {}) =>
        router.post(url, data, {
            preserveScroll: true,
            onSuccess: onClose,
            onError: (errors) =>
                setError(errors.reason ?? errors.report ?? null),
        });

    return (
        <>
            <DialogHeader>
                <DialogTitle>
                    Periode {report.period_no} · {date(report.starts_on)} –{' '}
                    {date(report.ends_on)}
                </DialogTitle>
            </DialogHeader>
            <div className="grid gap-4 text-sm">
                <div className="flex flex-wrap items-center gap-2">
                    <Badge variant={variants[report.status] ?? 'secondary'}>
                        {report.status_label}
                    </Badge>
                    <span className="text-muted-foreground">
                        Rapat {date(report.meeting_date)}
                    </span>
                </div>
                {report.review_note && (
                    <Alert variant="destructive">
                        <MessageSquareWarning aria-hidden="true" />
                        <AlertTitle>Catatan reviewer</AlertTitle>
                        <AlertDescription>
                            {report.review_note}
                        </AlertDescription>
                    </Alert>
                )}
                {textFields.map(([field, label]) => (
                    <div key={field} className="grid gap-1">
                        <p className="text-[13px] font-medium">{label}</p>
                        <p className="rounded-lg border bg-muted/30 px-3 py-2 whitespace-pre-line">
                            {report[field] || (
                                <span className="text-muted-foreground">
                                    Belum diisi
                                </span>
                            )}
                        </p>
                    </div>
                ))}
                <div className="grid gap-1">
                    <p className="text-[13px] font-medium">Peserta</p>
                    <p>
                        {report.attendees.length
                            ? report.attendees.join(', ')
                            : '—'}
                    </p>
                </div>
                <div className="grid gap-2">
                    <p className="text-[13px] font-medium">Evidence</p>
                    {report.evidence.length > 0 ? (
                        <ul className="grid gap-2">
                            {report.evidence.map((item) => (
                                <li
                                    key={item.id}
                                    className="flex flex-wrap items-center justify-between gap-3 rounded-lg border px-3 py-2.5"
                                >
                                    <div className="flex min-w-0 items-center gap-2.5">
                                        <FileText
                                            className="size-4 shrink-0 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                        <div className="grid min-w-0 gap-0.5">
                                            <span className="truncate text-sm font-medium">
                                                {item.title}
                                            </span>
                                            <span className="truncate text-xs text-muted-foreground">
                                                {item.original_name}
                                            </span>
                                        </div>
                                    </div>
                                    {item.download_url ? (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            render={
                                                <a href={item.download_url} />
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
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Belum ada evidence tertaut.
                        </p>
                    )}
                </div>
                <div className="grid gap-2">
                    <p className="text-[13px] font-medium">Corrective action</p>
                    {report.actions.length ? (
                        <ul className="grid gap-2">
                            {report.actions.map((action) => (
                                <li
                                    key={action.id}
                                    className="grid gap-1 rounded-lg border px-3 py-2.5 sm:grid-cols-[1fr_auto] sm:items-center"
                                >
                                    <div>
                                        <p className="text-sm font-medium">
                                            {action.title}
                                        </p>
                                        {action.description && (
                                            <p className="text-xs text-muted-foreground">
                                                {action.description}
                                            </p>
                                        )}
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        PIC {action.pic} · tenggat{' '}
                                        {date(action.due_date)} ·{' '}
                                        {action.status}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Belum ada tindakan.
                        </p>
                    )}
                </div>
                {report.can_review && (
                    <div className="grid gap-3 rounded-lg border bg-muted/30 p-4">
                        {asking && (
                            <FormField
                                id="reason"
                                label="Alasan revisi"
                                required
                                error={error ?? undefined}
                            >
                                <Textarea
                                    value={reason}
                                    onChange={(e) => setReason(e.target.value)}
                                    placeholder="Jelaskan apa yang perlu diperbaiki"
                                    className="min-h-20"
                                />
                            </FormField>
                        )}
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                onClick={() => post(approve.url(target))}
                            >
                                <Check
                                    data-icon="inline-start"
                                    aria-hidden="true"
                                />
                                Setujui
                            </Button>
                            {asking ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        post(revision.url(target), { reason })
                                    }
                                >
                                    Kirim Revisi
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setAsking(true)}
                                >
                                    Minta Revisi
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

export default function ProjectBiweekly({
    project,
    reports,
    members,
    next_period: nextPeriod,
    can_create: canCreate,
}: Props) {
    const [dialog, setDialog] = useState<{
        mode: 'form' | 'detail';
        report: Report | null;
    } | null>(null);

    return (
        <>
            <Head title="Bi-weekly Report" />
            <div className="mx-auto w-full max-w-[1440px] px-4 py-6 md:px-6 md:py-8">
                <PageHeader
                    breadcrumbs={[
                        { title: 'Dashboard', href: dashboard() },
                        {
                            title: 'Bi-weekly Report',
                            href: index.url(project.id),
                        },
                    ]}
                    title="Bi-weekly Report"
                    description={`${project.code} · ${project.title}`}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <CsvExportButton
                                href={exportBiweeklyCsv.url({
                                    project: project.id,
                                })}
                            />
                            {canCreate ? (
                                <Button
                                    className="h-10 px-4 text-sm"
                                    onClick={() =>
                                        setDialog({
                                            mode: 'form',
                                            report: null,
                                        })
                                    }
                                >
                                    <Plus
                                        data-icon="inline-start"
                                        aria-hidden="true"
                                    />
                                    Laporan Periode {nextPeriod}
                                </Button>
                            ) : null}
                        </div>
                    }
                />

                {reports.length === 0 ? (
                    <div className="rounded-xl border border-dashed">
                        <EmptyState
                            icon={FileText}
                            title="Belum ada laporan bi-weekly"
                            description="Laporan pemantauan berkala proyek akan muncul di sini."
                        />
                    </div>
                ) : (
                    <ul className="grid gap-3">
                        {reports.map((report) => (
                            <li key={report.id}>
                                <Card className="gap-0 py-0">
                                    <CardContent className="flex flex-col gap-3 p-5 sm:flex-row sm:items-center">
                                        <div className="grid min-w-0 flex-1 gap-1">
                                            <p className="text-sm font-semibold">
                                                Periode {report.period_no} ·{' '}
                                                {date(report.starts_on)} –{' '}
                                                {date(report.ends_on)}
                                            </p>
                                            <p className="truncate text-[13px] text-muted-foreground">
                                                {report.summary ||
                                                    'Ringkasan belum diisi'}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Rapat{' '}
                                                {date(report.meeting_date)} ·{' '}
                                                {report.attendees.length}{' '}
                                                peserta
                                                {report.submitted_by &&
                                                    ` · diajukan ${report.submitted_by}`}
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
                                        <div className="flex gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    setDialog({
                                                        mode: 'detail',
                                                        report,
                                                    })
                                                }
                                            >
                                                {report.can_review
                                                    ? 'Review'
                                                    : 'Detail'}
                                            </Button>
                                            {report.can_edit && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        setDialog({
                                                            mode: 'form',
                                                            report,
                                                        })
                                                    }
                                                >
                                                    <PencilLine
                                                        data-icon="inline-start"
                                                        aria-hidden="true"
                                                    />
                                                    Ubah
                                                </Button>
                                            )}
                                            {report.status === 'DRAFT' &&
                                                report.can_edit && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            router.delete(
                                                                destroy.url({
                                                                    project:
                                                                        project.id,
                                                                    report: report.id,
                                                                }),
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        Hapus
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
                <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-2xl">
                    {dialog?.mode === 'form' && (
                        <ReportForm
                            key={dialog.report?.id ?? 'new'}
                            projectId={project.id}
                            editing={dialog.report}
                            members={members}
                            onClose={() => setDialog(null)}
                        />
                    )}
                    {dialog?.mode === 'detail' && dialog.report && (
                        <DetailDialog
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
