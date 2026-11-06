import { Form } from '@inertiajs/react';
import { parseISO } from 'date-fns';
import { useState } from 'react';
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
import { store, update } from '@/routes/admin/batches';

export type BatchStatusOption = { value: string; label: string };

export type BatchFormValues = {
    id: number;
    code: string;
    name: string;
    starts_on: string;
    ends_on: string;
    status: string;
    participant_target: number | null;
    registration_starts_on: string | null;
    registration_ends_on: string | null;
    selection_starts_on: string | null;
    selection_ends_on: string | null;
    execution_starts_on: string | null;
    execution_ends_on: string | null;
};

type PhaseField = {
    number: number;
    label: string;
    start: keyof BatchFormValues & `${string}_starts_on`;
    end: keyof BatchFormValues & `${string}_ends_on`;
};

const PHASES: PhaseField[] = [
    {
        number: 1,
        label: 'Registrasi & Pengajuan Proposal',
        start: 'registration_starts_on',
        end: 'registration_ends_on',
    },
    {
        number: 2,
        label: 'Seleksi Proposal & Pembentukan Tim',
        start: 'selection_starts_on',
        end: 'selection_ends_on',
    },
    {
        number: 3,
        label: 'Eksekusi & Monitoring Proyek',
        start: 'execution_starts_on',
        end: 'execution_ends_on',
    },
];

/** Start and end of one program phase; the dashboard opens the phase on its start date. */
function PhaseDates({
    phase,
    batch,
    errors,
}: {
    phase: PhaseField;
    batch: BatchFormValues | null;
    errors: Record<string, string>;
}) {
    const [startsOn, setStartsOn] = useState<string | null>(
        (batch?.[phase.start] as string | null | undefined) ?? null,
    );
    const [endsOn, setEndsOn] = useState<string | null>(
        (batch?.[phase.end] as string | null | undefined) ?? null,
    );

    return (
        <fieldset className="grid gap-3 border-t border-border/70 pt-4 first-of-type:border-t-0 first-of-type:pt-0">
            <legend className="float-left mb-3 w-full text-sm font-medium text-foreground">
                Fase {phase.number} · {phase.label}
            </legend>
            <div className="grid gap-4 sm:grid-cols-2">
                <FormField
                    id={`batch-${phase.start}`}
                    label="Mulai"
                    error={errors[phase.start]}
                >
                    <DatePicker
                        name={phase.start}
                        value={startsOn}
                        onValueChange={setStartsOn}
                    />
                </FormField>
                <FormField
                    id={`batch-${phase.end}`}
                    label="Selesai"
                    error={errors[phase.end]}
                >
                    <DatePicker
                        name={phase.end}
                        value={endsOn}
                        onValueChange={setEndsOn}
                        disabledDates={
                            startsOn
                                ? { before: parseISO(startsOn) }
                                : undefined
                        }
                    />
                </FormField>
            </div>
        </fieldset>
    );
}

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    batch: BatchFormValues | null;
    statusOptions: BatchStatusOption[];
};

function BatchForm({
    batch,
    statusOptions,
    onClose,
}: {
    batch: BatchFormValues | null;
    statusOptions: BatchStatusOption[];
    onClose: () => void;
}) {
    const [startsOn, setStartsOn] = useState<string | null>(
        batch?.starts_on ?? null,
    );
    const [endsOn, setEndsOn] = useState<string | null>(batch?.ends_on ?? null);
    const [status, setStatus] = useState<string>(
        batch?.status ?? statusOptions[0]?.value ?? 'draft',
    );
    const isEditing = batch !== null;

    return (
        <Form
            {...(isEditing ? update.form(batch.id) : store.form())}
            options={{ preserveScroll: true }}
            onSuccess={onClose}
            className="grid gap-6"
        >
            {({ errors, processing }) => (
                <>
                    <FormField
                        id="batch-code"
                        label="Kode Batch"
                        required
                        error={errors.code}
                    >
                        <Input
                            name="code"
                            defaultValue={batch?.code}
                            placeholder="Contoh: BATCH-2026-01"
                            maxLength={32}
                            autoComplete="off"
                            className="uppercase placeholder:normal-case"
                        />
                    </FormField>

                    <FormField
                        id="batch-name"
                        label="Nama Batch"
                        required
                        error={errors.name}
                    >
                        <Input
                            name="name"
                            defaultValue={batch?.name}
                            placeholder="Contoh: QCC Batch Tahun 2026"
                            maxLength={255}
                            autoComplete="off"
                        />
                    </FormField>

                    <div className="grid gap-6 sm:grid-cols-2">
                        <FormField
                            id="batch-starts-on"
                            label="Tanggal Mulai"
                            required
                            error={errors.starts_on}
                        >
                            <DatePicker
                                name="starts_on"
                                value={startsOn}
                                onValueChange={setStartsOn}
                            />
                        </FormField>

                        <FormField
                            id="batch-ends-on"
                            label="Tanggal Selesai"
                            required
                            error={errors.ends_on}
                        >
                            <DatePicker
                                name="ends_on"
                                value={endsOn}
                                onValueChange={setEndsOn}
                                disabledDates={
                                    startsOn
                                        ? { before: parseISO(startsOn) }
                                        : undefined
                                }
                            />
                        </FormField>
                    </div>

                    <FormField
                        id="batch-participant-target"
                        label="Target Peserta"
                        hint="Jumlah SIMers yang ditargetkan mengajukan proposal. Dipakai di dashboard program."
                        error={errors.participant_target}
                    >
                        <Input
                            name="participant_target"
                            type="number"
                            inputMode="numeric"
                            min={1}
                            defaultValue={batch?.participant_target ?? ''}
                            placeholder="Contoh: 290"
                        />
                    </FormField>

                    <div className="grid gap-3">
                        <div className="grid gap-0.5">
                            <h3 className="text-sm font-semibold text-foreground">
                                Jadwal Fase Program
                            </h3>
                            <p className="text-xs text-muted-foreground">
                                Opsional. Dashboard program membuka tiap fase
                                pada tanggal mulainya.
                            </p>
                        </div>
                        {PHASES.map((phase) => (
                            <PhaseDates
                                key={phase.number}
                                phase={phase}
                                batch={batch}
                                errors={errors}
                            />
                        ))}
                    </div>

                    <FormField
                        id="batch-status"
                        label="Status"
                        required
                        error={errors.status}
                    >
                        <Select
                            name="status"
                            value={status}
                            onValueChange={(value) => value && setStatus(value)}
                            items={statusOptions}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Pilih status" />
                            </SelectTrigger>
                            <SelectContent>
                                {statusOptions.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            className="h-10 px-4 text-sm"
                            onClick={onClose}
                            disabled={processing}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            className="h-10 px-4 text-sm"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            {processing ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </DialogFooter>
                </>
            )}
        </Form>
    );
}

export function BatchFormDialog({
    open,
    onOpenChange,
    batch,
    statusOptions,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] gap-6 overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {batch ? 'Ubah Batch' : 'Tambah Batch'}
                    </DialogTitle>
                </DialogHeader>
                {open && (
                    <BatchForm
                        key={batch?.id ?? 'new'}
                        batch={batch}
                        statusOptions={statusOptions}
                        onClose={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}
