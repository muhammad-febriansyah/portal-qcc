import { Head, router, useForm } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import {
    Download,
    FileText,
    PencilLine,
    Plus,
    Send,
    Trash2,
    UserMinus,
    UserPlus,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { DataTable } from '@/components/data-table';
import type { DataTableColumnDef } from '@/components/data-table';
import { DataTableRowActions } from '@/components/data-table-row-actions';
import { FormField } from '@/components/form-field';
import { DatePicker } from '@/components/date-picker';
import { PageHeader } from '@/components/page-header';
import { CsvExportButton } from '@/components/csv-export-button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
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
    author: string | null;
    status: string;
    assigned_at: string;
    ended_at: string | null;
};
type Report = {
    id: number;
    session_at: string;
    mentor: string | null;
    author: string | null;
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
    author_type: 'MENTOR' | 'TEAM_LEADER';
    project_mentor_id: number;
};

type Props = {
    project: { id: number; code: string; title: string };
    assignments: Assignment[];
    reports: Report[];
    members: { value: string; label: string }[];
    mentor_options: { value: string; label: string }[];
    can_assign: boolean;
    can_write: boolean;
    is_team_leader: boolean;
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
    assignments,
    isTeamLeader,
    onClose,
}: {
    projectId: number;
    editing: Report | null;
    members: Props['members'];
    assignments: Assignment[];
    isTeamLeader: boolean;
    onClose: () => void;
}) {
    const initialSessionAt = editing
        ? format(parseISO(editing.session_at), "yyyy-MM-dd'T'HH:mm")
        : '';
    const [sessionDate, setSessionDate] = useState(
        initialSessionAt.slice(0, 10),
    );
    const [sessionTime, setSessionTime] = useState(
        initialSessionAt.slice(11, 16),
    );
    const form = useForm({
        session_at: initialSessionAt,
        project_mentor_id: editing?.project_mentor_id
            ? String(editing.project_mentor_id)
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
                {isTeamLeader && (
                    <FormField
                        id="project_mentor_id"
                        label="Mentor ditugaskan"
                        required
                        error={form.errors.project_mentor_id}
                    >
                        <Select
                            disabled={editing !== null}
                            value={form.data.project_mentor_id}
                            onValueChange={(value) =>
                                form.setData('project_mentor_id', value ?? '')
                            }
                            items={assignments
                                .filter((item) => item.status === 'ACTIVE')
                                .map((item) => ({
                                    value: String(item.id),
                                    label: item.mentor ?? 'Mentor',
                                }))}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Pilih mentor" />
                            </SelectTrigger>
                            <SelectContent>
                                {assignments
                                    .filter((item) => item.status === 'ACTIVE')
                                    .map((item) => (
                                        <SelectItem
                                            key={item.id}
                                            value={String(item.id)}
                                        >
                                            {item.mentor ?? 'Mentor'}
                                        </SelectItem>
                                    ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                )}
                <FormField
                    id="session-date"
                    label="Tanggal dan waktu sesi"
                    required
                    error={form.errors.session_at}
                >
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="grid gap-1.5">
                            <span className="text-xs text-muted-foreground">
                                Tanggal
                            </span>
                            <DatePicker
                                id="session-date"
                                value={sessionDate || null}
                                onValueChange={(value) => {
                                    const nextDate = value ?? '';
                                    setSessionDate(nextDate);
                                    form.setData(
                                        'session_at',
                                        nextDate && sessionTime
                                            ? `${nextDate}T${sessionTime}`
                                            : '',
                                    );
                                }}
                                required
                                aria-invalid={Boolean(form.errors.session_at)}
                                className="h-10"
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <label
                                htmlFor="session-time"
                                className="text-xs text-muted-foreground"
                            >
                                Jam
                            </label>
                            <Input
                                id="session-time"
                                type="time"
                                value={sessionTime}
                                onChange={(event) => {
                                    const nextTime = event.target.value;
                                    setSessionTime(nextTime);
                                    form.setData(
                                        'session_at',
                                        sessionDate && nextTime
                                            ? `${sessionDate}T${nextTime}`
                                            : '',
                                    );
                                }}
                                required
                                aria-invalid={Boolean(form.errors.session_at)}
                                className="h-10"
                            />
                        </div>
                    </div>
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
                    variant="default"
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
                        Kirim {isTeamLeader ? 'ke Mentor' : 'ke Tim'}
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
                <DialogTitle>
                    {report.author_type === 'TEAM_LEADER'
                        ? 'Feedback Mentor'
                        : 'Tanggapan tim'}
                    : {report.topic}
                </DialogTitle>
            </DialogHeader>
            <FormField
                id="team_response"
                label={
                    report.author_type === 'TEAM_LEADER'
                        ? 'Feedback Mentor'
                        : 'Tanggapan tim'
                }
                required
                error={form.errors.team_response}
            >
                <Textarea
                    value={form.data.team_response}
                    onChange={(e) =>
                        form.setData('team_response', e.target.value)
                    }
                    placeholder={
                        report.author_type === 'TEAM_LEADER'
                            ? 'Tulis tanggapan mentor untuk laporan tim'
                            : 'Apa yang akan dilakukan tim?'
                    }
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

function MentoringReportDetail({ report }: { report: Report }) {
    const sections = [
        ['Temuan', report.findings],
        ['Saran', report.suggestions],
        ['Tindak lanjut', report.next_steps],
        ['Tanggapan tim', report.team_response],
    ];

    return (
        <div className="grid gap-5">
            <DialogHeader>
                <DialogTitle className="pr-8 text-xl font-semibold">
                    {report.topic}
                </DialogTitle>
                <DialogDescription>
                    {when(report.session_at)} ·{' '}
                    {report.mentor ?? 'Mentor belum ditugaskan'}
                </DialogDescription>
                <div className="flex flex-wrap items-center gap-2 pt-1">
                    <Badge variant={variants[report.status] ?? 'secondary'}>
                        {report.status_label}
                    </Badge>
                    <span className="text-sm text-muted-foreground">
                        {report.author_type === 'TEAM_LEADER'
                            ? `Dibuat ${report.author ?? 'Team Leader'}`
                            : `Mentor ${report.mentor ?? 'tidak diketahui'}`}
                    </span>
                </div>
            </DialogHeader>

            <div className="grid max-h-[68dvh] gap-5 overflow-y-auto px-1 pb-1">
                <div className="grid gap-3 md:grid-cols-2">
                    {sections.map(([label, value]) => (
                        <Card key={label} className="gap-0 py-0">
                            <CardHeader className="border-b p-4">
                                <CardTitle className="text-sm font-semibold">
                                    {label}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="px-4 py-3 text-sm leading-6 break-words whitespace-pre-wrap">
                                {value || (
                                    <span className="text-muted-foreground">
                                        Belum diisi
                                    </span>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card className="gap-0 py-0">
                    <CardHeader className="border-b p-4">
                        <CardTitle className="text-sm font-semibold">
                            Action item
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-4">
                        {report.actions.length ? (
                            <ul className="grid gap-3 md:grid-cols-2">
                                {report.actions.map((action) => (
                                    <li
                                        key={action.id}
                                        className="grid gap-2 rounded-lg border p-3"
                                    >
                                        <div className="grid gap-1">
                                            <p className="text-sm font-medium">
                                                {action.title}
                                            </p>
                                            {action.description && (
                                                <p className="text-sm whitespace-pre-line text-muted-foreground">
                                                    {action.description}
                                                </p>
                                            )}
                                        </div>
                                        <p className="border-t pt-2 text-xs text-muted-foreground">
                                            PIC{' '}
                                            {action.pic ?? 'Belum ditentukan'} ·
                                            tenggat{' '}
                                            {format(
                                                parseISO(action.due_date),
                                                'd MMM yyyy',
                                                {
                                                    locale: indonesianLocale,
                                                },
                                            )}{' '}
                                            · {action.status}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Belum ada action item.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Card className="gap-0 py-0">
                    <CardHeader className="border-b p-4">
                        <CardTitle className="text-sm font-semibold">
                            Evidence
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-4">
                        {report.evidence.length ? (
                            <ul className="grid gap-3 md:grid-cols-2">
                                {report.evidence.map((item) => (
                                    <li
                                        key={item.id}
                                        className="flex min-w-0 items-center justify-between gap-3 rounded-lg border p-3"
                                    >
                                        <div className="flex min-w-0 items-start gap-2.5">
                                            <FileText
                                                className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                                aria-hidden="true"
                                            />
                                            <div className="grid min-w-0 gap-1">
                                                <span className="text-sm font-medium break-words">
                                                    {item.title}
                                                </span>
                                                <span className="text-xs break-all text-muted-foreground">
                                                    {item.original_name}
                                                </span>
                                            </div>
                                        </div>
                                        {item.download_url ? (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                render={
                                                    <a
                                                        href={item.download_url}
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
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Belum ada evidence tertaut.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
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
    is_team_leader: isTeamLeader,
}: Props) {
    const [dialog, setDialog] = useState<{
        mode: 'session' | 'ack' | 'detail';
        report: Report | null;
    } | null>(null);
    const [mentorId, setMentorId] = useState('');
    const [assignError, setAssignError] = useState<string | null>(null);
    const [search, setSearch] = useState('');
    const filteredReports = reports.filter((report) =>
        [
            report.topic,
            when(report.session_at),
            report.mentor,
            report.author,
            report.status_label,
            report.findings,
            report.suggestions,
            report.next_steps,
            report.team_response,
            report.actions.map((action) => action.title).join(' '),
            report.evidence
                .map((item) => `${item.title} ${item.original_name}`)
                .join(' '),
        ]
            .filter(Boolean)
            .join(' ')
            .toLocaleLowerCase('id')
            .includes(search.trim().toLocaleLowerCase('id')),
    );
    const columns = useMemo<DataTableColumnDef<Report>[]>(
        () => [
            {
                id: 'session',
                header: 'Sesi',
                cell: ({ row }) => (
                    <div className="grid max-w-64 gap-1 whitespace-normal">
                        <span className="font-medium">
                            {row.original.topic}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {when(row.original.session_at)}
                        </span>
                    </div>
                ),
            },
            {
                id: 'mentor',
                header: 'Mentor & pelapor',
                cell: ({ row }) => (
                    <div className="grid max-w-56 gap-1 whitespace-normal">
                        <span>
                            {row.original.mentor ?? 'Mentor belum ditugaskan'}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {row.original.author_type === 'TEAM_LEADER'
                                ? `Dibuat ${row.original.author ?? 'Team Leader'}`
                                : 'Feedback mentor'}
                        </span>
                    </div>
                ),
            },
            {
                id: 'summary',
                header: 'Ringkasan',
                cell: ({ row }) => (
                    <div className="grid max-w-md gap-1 text-sm whitespace-normal">
                        <p>
                            <span className="font-medium">Temuan:</span>{' '}
                            {row.original.findings || 'Belum diisi'}
                        </p>
                        <p className="text-muted-foreground">
                            <span className="font-medium">Saran:</span>{' '}
                            {row.original.suggestions || 'Belum diisi'}
                        </p>
                    </div>
                ),
            },
            {
                id: 'follow_up',
                header: 'Tindak lanjut',
                cell: ({ row }) => (
                    <div className="grid max-w-xs gap-1 whitespace-normal">
                        <span className="text-sm">
                            {row.original.next_steps ||
                                'Belum ada tindak lanjut'}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            {row.original.actions.length} action item ·{' '}
                            {row.original.evidence.length} evidence
                        </span>
                    </div>
                ),
            },
            {
                id: 'status',
                header: 'Status',
                cell: ({ row }) => (
                    <Badge
                        variant={variants[row.original.status] ?? 'secondary'}
                    >
                        {row.original.status_label}
                    </Badge>
                ),
            },
            {
                id: 'actions',
                header: 'Aksi',
                cell: ({ row }) => (
                    <div className="flex justify-end">
                        <DataTableRowActions
                            label={`Aksi sesi ${row.original.topic}`}
                            actions={[
                                {
                                    label: 'Detail',
                                    icon: FileText,
                                    onSelect: () =>
                                        setDialog({
                                            mode: 'detail',
                                            report: row.original,
                                        }),
                                },
                                {
                                    label: 'Ubah',
                                    icon: PencilLine,
                                    onSelect: () =>
                                        setDialog({
                                            mode: 'session',
                                            report: row.original,
                                        }),
                                    hidden: !row.original.can_edit,
                                },
                                {
                                    label: 'Tanggapi',
                                    icon: Send,
                                    onSelect: () =>
                                        setDialog({
                                            mode: 'ack',
                                            report: row.original,
                                        }),
                                    hidden: !row.original.can_acknowledge,
                                },
                            ]}
                        />
                    </div>
                ),
            },
        ],
        [],
    );

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
                            <p className="text-sm text-muted-foreground">
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
                                            <p className="text-sm font-medium">
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
                                        <p className="text-sm text-destructive">
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
                            menyetujui 8 Step pada proyek bimbingan aktif. Team
                            Leader mengirim laporan mentoring; Mentor memberi
                            feedback sesuai penugasan. Reviewer QCC tetap
                            memutuskan proposal.
                        </p>
                    </CardContent>
                </Card>

                <Card className="gap-0 py-0">
                    <DataTable
                        embedded
                        columns={columns}
                        data={filteredReports}
                        rowId={(report) => String(report.id)}
                        page={1}
                        pageSize={Math.max(filteredReports.length, 1)}
                        total={filteredReports.length}
                        onPageChange={() => {}}
                        search={search}
                        onSearchChange={setSearch}
                        searchPlaceholder="Cari sesi, mentor, pelapor, catatan, status..."
                        emptyTitle={
                            reports.length === 0
                                ? 'Belum ada sesi mentoring'
                                : 'Sesi mentoring tidak ditemukan'
                        }
                        emptyDescription={
                            reports.length === 0
                                ? 'Catatan sesi dan feedback mentor akan muncul di sini.'
                                : 'Coba kata kunci lain.'
                        }
                    />
                </Card>
            </div>

            <Dialog
                open={dialog !== null}
                onOpenChange={(open) => !open && setDialog(null)}
            >
                <DialogContent
                    className={
                        dialog?.mode === 'detail'
                            ? 'max-h-[90dvh] overflow-y-auto sm:max-w-5xl'
                            : 'max-h-[90dvh] overflow-y-auto sm:max-w-4xl'
                    }
                >
                    {dialog?.mode === 'session' && (
                        <SessionForm
                            key={dialog.report?.id ?? 'new'}
                            projectId={project.id}
                            editing={dialog.report}
                            members={members}
                            assignments={assignments}
                            isTeamLeader={isTeamLeader}
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
                    {dialog?.mode === 'detail' && dialog.report && (
                        <MentoringReportDetail report={dialog.report} />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
