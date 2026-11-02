import { useForm } from '@inertiajs/react';
import { DatePicker } from '@/components/date-picker';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
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
import { store, update } from '@/routes/projects/risks';
import {
    store as storeAction,
    update as updateAction,
} from '@/routes/projects/risks/actions';

export type Option = { value: string; label: string };

export type RiskHistoryEntry = {
    id: number;
    event: string;
    actor: string;
    before: Record<string, unknown>;
    after: Record<string, unknown>;
    reason: string | null;
    occurred_at: string | null;
};

export type RiskRow = {
    id: number;
    type: string;
    title: string;
    description: string;
    impact: string;
    likelihood: string;
    priority: string;
    mitigation: string | null;
    pic_id: number | null;
    pic: string | null;
    due_date: string | null;
    status: string;
    is_overdue: boolean;
    history: RiskHistoryEntry[];
};

export type ActionRow = {
    id: number;
    title: string;
    description: string | null;
    pic_id: number;
    pic: string | null;
    due_date: string | null;
    status: string;
    source_type: string;
    source_id: number | null;
    is_overdue: boolean;
};

function OptionSelect({
    id,
    value,
    onChange,
    options,
    placeholder,
}: {
    id: string;
    value: string;
    onChange: (value: string) => void;
    options: Option[];
    placeholder: string;
}) {
    return (
        <Select
            value={value}
            onValueChange={(next) => next && onChange(next)}
            items={options}
        >
            <SelectTrigger id={id} className="w-full">
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

type RiskDialogProps = {
    projectId: number;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    editing: RiskRow | null;
    options: {
        types: Option[];
        likelihoods: Option[];
        priorities: Option[];
        riskStatuses: Option[];
    };
    members: Option[];
};

export function RiskDialog(props: RiskDialogProps) {
    return (
        <Dialog open={props.open} onOpenChange={props.onOpenChange}>
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-xl">
                {props.open && (
                    <RiskForm key={props.editing?.id ?? 'new'} {...props} />
                )}
            </DialogContent>
        </Dialog>
    );
}

function RiskForm({
    projectId,
    onOpenChange,
    editing,
    options,
    members,
}: RiskDialogProps) {
    const form = useForm({
        type: editing?.type ?? 'RISK',
        title: editing?.title ?? '',
        description: editing?.description ?? '',
        impact: editing?.impact ?? '',
        likelihood: editing?.likelihood ?? 'MEDIUM',
        priority: editing?.priority ?? 'MEDIUM',
        mitigation: editing?.mitigation ?? '',
        pic_id: editing?.pic_id ? String(editing.pic_id) : '__none',
        due_date: editing?.due_date ?? (null as string | null),
        status: editing?.status ?? 'OPEN',
    });

    return (
        <form
            className="grid gap-6"
            onSubmit={(event) => {
                event.preventDefault();
                form.transform((data) => ({
                    ...data,
                    pic_id:
                        data.pic_id === '__none' ? null : Number(data.pic_id),
                }));
                const options = {
                    preserveScroll: true,
                    onSuccess: () => onOpenChange(false),
                };

                if (editing) {
                    form.patch(
                        update.url({ project: projectId, risk: editing.id }),
                        options,
                    );
                } else {
                    form.post(store.url(projectId), options);
                }
            }}
        >
            <DialogHeader>
                <DialogTitle>
                    {editing ? 'Ubah Risiko / Isu' : 'Catat Risiko / Isu'}
                </DialogTitle>
            </DialogHeader>
            <div className="grid gap-6 sm:grid-cols-2">
                <FormField
                    id="risk-type"
                    label="Jenis"
                    required
                    error={form.errors.type}
                >
                    <OptionSelect
                        id="risk-type"
                        value={form.data.type}
                        onChange={(v) => form.setData('type', v)}
                        options={options.types}
                        placeholder="Pilih jenis"
                    />
                </FormField>
                <FormField
                    id="risk-status"
                    label="Status"
                    required
                    error={form.errors.status}
                >
                    <OptionSelect
                        id="risk-status"
                        value={form.data.status}
                        onChange={(v) => form.setData('status', v)}
                        options={options.riskStatuses}
                        placeholder="Pilih status"
                    />
                </FormField>
            </div>
            <FormField
                id="risk-title"
                label="Judul"
                required
                error={form.errors.title}
            >
                <Input
                    value={form.data.title}
                    onChange={(e) => form.setData('title', e.target.value)}
                    placeholder="Ringkas masalahnya"
                    maxLength={255}
                    className="h-10"
                />
            </FormField>
            <FormField
                id="risk-description"
                label="Deskripsi"
                required
                error={form.errors.description}
            >
                <Textarea
                    value={form.data.description}
                    onChange={(e) =>
                        form.setData('description', e.target.value)
                    }
                    placeholder="Jelaskan risiko atau isunya"
                    className="min-h-24"
                />
            </FormField>
            <FormField
                id="risk-impact"
                label="Dampak"
                required
                error={form.errors.impact}
            >
                <Textarea
                    value={form.data.impact}
                    onChange={(e) => form.setData('impact', e.target.value)}
                    placeholder="Apa akibatnya bagi proyek?"
                    className="min-h-20"
                />
            </FormField>
            <div className="grid gap-6 sm:grid-cols-2">
                <FormField
                    id="risk-likelihood"
                    label="Kemungkinan"
                    required
                    error={form.errors.likelihood}
                >
                    <OptionSelect
                        id="risk-likelihood"
                        value={form.data.likelihood}
                        onChange={(v) => form.setData('likelihood', v)}
                        options={options.likelihoods}
                        placeholder="Pilih kemungkinan"
                    />
                </FormField>
                <FormField
                    id="risk-priority"
                    label="Prioritas"
                    required
                    error={form.errors.priority}
                >
                    <OptionSelect
                        id="risk-priority"
                        value={form.data.priority}
                        onChange={(v) => form.setData('priority', v)}
                        options={options.priorities}
                        placeholder="Pilih prioritas"
                    />
                </FormField>
            </div>
            <FormField
                id="risk-mitigation"
                label="Mitigasi"
                error={form.errors.mitigation}
            >
                <Textarea
                    value={form.data.mitigation}
                    onChange={(e) => form.setData('mitigation', e.target.value)}
                    placeholder="Langkah untuk menurunkan dampaknya"
                    className="min-h-20"
                />
            </FormField>
            <div className="grid gap-6 sm:grid-cols-2">
                <FormField id="risk-pic" label="PIC" error={form.errors.pic_id}>
                    <OptionSelect
                        id="risk-pic"
                        value={form.data.pic_id}
                        onChange={(v) => form.setData('pic_id', v)}
                        options={[
                            { value: '__none', label: 'Tidak ditugaskan' },
                            ...members,
                        ]}
                        placeholder="Pilih penanggung jawab"
                    />
                </FormField>
                <FormField
                    id="risk-due"
                    label="Tenggat"
                    error={form.errors.due_date}
                >
                    <DatePicker
                        value={form.data.due_date}
                        onValueChange={(v) => form.setData('due_date', v)}
                        placeholder="Pilih tanggal"
                    />
                </FormField>
            </div>
            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => onOpenChange(false)}
                >
                    Batal
                </Button>
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner data-icon="inline-start" />}
                    Simpan
                </Button>
            </DialogFooter>
        </form>
    );
}

type ActionDialogProps = {
    projectId: number;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    editing: ActionRow | null;
    statuses: Option[];
    members: Option[];
    risks: { value: string; label: string }[];
};

export function ActionDialog(props: ActionDialogProps) {
    return (
        <Dialog open={props.open} onOpenChange={props.onOpenChange}>
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-lg">
                {props.open && (
                    <ActionForm key={props.editing?.id ?? 'new'} {...props} />
                )}
            </DialogContent>
        </Dialog>
    );
}

const NO_RISK = 'none';

function ActionForm({
    projectId,
    onOpenChange,
    editing,
    statuses,
    members,
    risks,
}: ActionDialogProps) {
    const form = useForm({
        title: editing?.title ?? '',
        description: editing?.description ?? '',
        pic_id: editing ? String(editing.pic_id) : '',
        due_date: editing?.due_date ?? (null as string | null),
        status: editing?.status ?? 'OPEN',
        source_type: editing?.source_type ?? 'PROJECT',
        source_id: editing?.source_id ? String(editing.source_id) : NO_RISK,
    });

    return (
        <form
            className="grid gap-6"
            onSubmit={(event) => {
                event.preventDefault();
                form.transform((data) => ({
                    ...data,
                    source_type:
                        data.source_id === NO_RISK ? 'PROJECT' : 'RISK',
                    source_id:
                        data.source_id === NO_RISK ? null : data.source_id,
                }));
                const options = {
                    preserveScroll: true,
                    onSuccess: () => onOpenChange(false),
                };

                if (editing) {
                    form.patch(
                        updateAction.url({
                            project: projectId,
                            action: editing.id,
                        }),
                        options,
                    );
                } else {
                    form.post(storeAction.url(projectId), options);
                }
            }}
        >
            <DialogHeader>
                <DialogTitle>
                    {editing ? 'Ubah Tindakan' : 'Catat Tindakan'}
                </DialogTitle>
            </DialogHeader>
            <FormField
                id="action-title"
                label="Judul tindakan"
                required
                error={form.errors.title}
            >
                <Input
                    value={form.data.title}
                    onChange={(e) => form.setData('title', e.target.value)}
                    placeholder="Apa yang harus dilakukan?"
                    maxLength={255}
                    className="h-10"
                />
            </FormField>
            <FormField
                id="action-description"
                label="Deskripsi"
                error={form.errors.description}
            >
                <Textarea
                    value={form.data.description}
                    onChange={(e) =>
                        form.setData('description', e.target.value)
                    }
                    placeholder="Tambahkan detail jika perlu"
                    className="min-h-20"
                />
            </FormField>
            <div className="grid gap-6 sm:grid-cols-2">
                <FormField
                    id="action-pic"
                    label="PIC"
                    required
                    error={form.errors.pic_id}
                >
                    <OptionSelect
                        id="action-pic"
                        value={form.data.pic_id}
                        onChange={(v) => form.setData('pic_id', v)}
                        options={members}
                        placeholder="Pilih penanggung jawab"
                    />
                </FormField>
                <FormField
                    id="action-due"
                    label="Tenggat"
                    required
                    error={form.errors.due_date}
                >
                    <DatePicker
                        value={form.data.due_date}
                        onValueChange={(v) => form.setData('due_date', v)}
                        placeholder="Pilih tanggal"
                    />
                </FormField>
            </div>
            <div className="grid gap-6 sm:grid-cols-2">
                <FormField
                    id="action-status"
                    label="Status"
                    required
                    error={form.errors.status}
                >
                    <OptionSelect
                        id="action-status"
                        value={form.data.status}
                        onChange={(v) => form.setData('status', v)}
                        options={statuses}
                        placeholder="Pilih status"
                    />
                </FormField>
                {editing &&
                ['BIWEEKLY_REPORT', 'MENTORING_REPORT'].includes(
                    editing.source_type,
                ) ? (
                    <div className="grid content-start gap-2">
                        <p className="text-sm font-medium">Sumber tindakan</p>
                        <p className="rounded-lg border bg-muted/30 px-3 py-2 text-sm text-muted-foreground">
                            {editing.source_type === 'BIWEEKLY_REPORT'
                                ? 'Laporan bi-weekly'
                                : 'Laporan mentoring'}{' '}
                            #{editing.source_id}
                        </p>
                    </div>
                ) : (
                    <FormField
                        id="action-source"
                        label="Terkait risiko / isu"
                        error={form.errors.source_id}
                    >
                        <OptionSelect
                            id="action-source"
                            value={form.data.source_id}
                            onChange={(v) => form.setData('source_id', v)}
                            options={[
                                { value: NO_RISK, label: 'Tidak terkait' },
                                ...risks,
                            ]}
                            placeholder="Pilih risiko atau isu"
                        />
                    </FormField>
                )}
            </div>
            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => onOpenChange(false)}
                >
                    Batal
                </Button>
                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner data-icon="inline-start" />}
                    Simpan
                </Button>
            </DialogFooter>
        </form>
    );
}
