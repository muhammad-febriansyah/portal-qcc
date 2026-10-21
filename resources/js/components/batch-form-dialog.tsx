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
};

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
            <DialogContent className="gap-6 sm:max-w-lg">
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
