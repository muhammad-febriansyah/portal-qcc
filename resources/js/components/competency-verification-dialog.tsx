import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { FormField } from '@/components/form-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { reject, verify } from '@/routes/admin/competency-directory';

export type CompetencyVerificationTarget = {
    id: number;
    name: string;
    competency_name: string;
    competency_code: string;
    self_level: number;
    self_level_label: string | null;
    verified_level: number | null;
    verified_level_label: string | null;
};

type Props = {
    target: CompetencyVerificationTarget | null;
    mode: 'verify' | 'reject';
    levelOptions: { value: number; label: string }[];
    onClose: () => void;
};

function VerificationForm({
    target,
    mode,
    levelOptions,
    onClose,
}: Props & { target: CompetencyVerificationTarget }) {
    const [level, setLevel] = useState(
        String(target.verified_level ?? target.self_level),
    );
    const isRejecting = mode === 'reject';

    return (
        <Form
            {...(isRejecting ? reject.form(target.id) : verify.form(target.id))}
            options={{ preserveScroll: true }}
            onSuccess={onClose}
            className="grid gap-6"
        >
            {({ errors, processing }) => (
                <>
                    <dl className="grid gap-3 rounded-lg bg-muted/60 px-4 py-3 text-sm sm:grid-cols-2">
                        <div className="grid gap-1">
                            <dt className="text-xs text-muted-foreground">
                                Anggota
                            </dt>
                            <dd className="font-medium">{target.name}</dd>
                        </div>
                        <div className="grid gap-1">
                            <dt className="text-xs text-muted-foreground">
                                Kompetensi
                            </dt>
                            <dd className="font-medium">
                                {target.competency_code} ·{' '}
                                {target.competency_name}
                            </dd>
                        </div>
                        <div className="grid gap-1">
                            <dt className="text-xs text-muted-foreground">
                                Level penilaian anggota
                            </dt>
                            <dd className="font-medium">
                                {target.self_level} · {target.self_level_label}
                            </dd>
                        </div>
                        <div className="grid gap-1">
                            <dt className="text-xs text-muted-foreground">
                                Level verifikasi sebelumnya
                            </dt>
                            <dd className="font-medium">
                                {target.verified_level === null
                                    ? 'Belum ada'
                                    : `${target.verified_level} · ${target.verified_level_label}`}
                            </dd>
                        </div>
                    </dl>

                    {isRejecting ? (
                        <FormField
                            id="verification-reason"
                            label="Alasan / perbaikan yang diminta"
                            required
                            error={errors.reason}
                        >
                            <Textarea
                                id="verification-reason"
                                name="reason"
                                required
                                maxLength={2000}
                                rows={4}
                                placeholder="Jelaskan bagian yang perlu diperbaiki..."
                            />
                        </FormField>
                    ) : (
                        <>
                            <FormField
                                id="verified-level"
                                label="Level hasil verifikasi"
                                required
                                error={errors.verified_level}
                            >
                                <Select
                                    value={level}
                                    onValueChange={(next) =>
                                        setLevel(next ?? '')
                                    }
                                    items={levelOptions.map((option) => ({
                                        value: String(option.value),
                                        label: `${option.value} · ${option.label}`,
                                    }))}
                                >
                                    <SelectTrigger
                                        id="verified-level"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Pilih level" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {levelOptions.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={String(option.value)}
                                            >
                                                {option.value} · {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </FormField>
                            <input
                                type="hidden"
                                name="verified_level"
                                value={level}
                            />
                            <FormField
                                id="verification-note"
                                label="Catatan (opsional)"
                                error={errors.note}
                            >
                                <Textarea
                                    id="verification-note"
                                    name="note"
                                    maxLength={2000}
                                    rows={3}
                                    placeholder="Tambahkan catatan untuk anggota..."
                                />
                            </FormField>
                        </>
                    )}

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
                            variant={isRejecting ? 'destructive' : 'default'}
                            className="h-10 px-4 text-sm"
                            disabled={processing}
                        >
                            {processing
                                ? 'Menyimpan...'
                                : isRejecting
                                  ? 'Kirim permintaan perbaikan'
                                  : 'Simpan verifikasi'}
                        </Button>
                    </DialogFooter>
                </>
            )}
        </Form>
    );
}

export function CompetencyVerificationDialog({
    target,
    mode,
    levelOptions,
    onClose,
}: Props) {
    return (
        <Dialog
            open={target !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="gap-6 sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {mode === 'verify'
                            ? 'Verifikasi Kompetensi'
                            : 'Minta Perbaikan Kompetensi'}
                    </DialogTitle>
                </DialogHeader>
                {target && (
                    <VerificationForm
                        key={`${target.id}-${mode}`}
                        target={target}
                        mode={mode}
                        levelOptions={levelOptions}
                        onClose={onClose}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}
