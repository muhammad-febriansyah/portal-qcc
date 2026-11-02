import { useForm } from '@inertiajs/react';
import { Save, Send } from 'lucide-react';
import { DatePicker } from '@/components/date-picker';
import { FormField } from '@/components/form-field';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { submit, update } from '@/routes/projects/steps';

export type EditableStep = {
    number: number;
    activity_description: string | null;
    plan_text: string | null;
    actual_text: string | null;
    target_date: string | null;
    progress_pct: number;
    submitter_note: string | null;
    lock_version: number;
    can_submit: boolean;
};

function CharacterCount({ value, max }: { value: string; max: number }) {
    return (
        <p className="text-right text-xs text-muted-foreground">
            {value.length.toLocaleString('id-ID')} /{' '}
            {max.toLocaleString('id-ID')} karakter
        </p>
    );
}

/**
 * The step form of the team: save a draft any time, submit when the activity and
 * the plan are filled in. The version travels with the form, so a stale page
 * cannot overwrite what someone else just saved.
 */
export function StepForm({
    projectId,
    step,
}: {
    projectId: number;
    step: EditableStep;
}) {
    const form = useForm({
        activity_description: step.activity_description ?? '',
        plan_text: step.plan_text ?? '',
        actual_text: step.actual_text ?? '',
        target_date: step.target_date,
        progress_pct: String(step.progress_pct),
        submitter_note: step.submitter_note ?? '',
        lock_version: step.lock_version,
    });
    const stepError = (form.errors as Record<string, string | undefined>).step;
    const target = { project: projectId, step: step.number };
    const progress = Math.min(
        100,
        Math.max(0, Number.parseInt(form.data.progress_pct, 10) || 0),
    );

    return (
        <form
            className="grid gap-5"
            onSubmit={(event) => {
                event.preventDefault();
                form.patch(update.url(target), { preserveScroll: true });
            }}
        >
            {stepError && (
                <Alert variant="destructive">
                    <AlertDescription>{stepError}</AlertDescription>
                </Alert>
            )}
            {form.errors.lock_version && (
                <Alert variant="destructive">
                    <AlertDescription>
                        {form.errors.lock_version}
                    </AlertDescription>
                </Alert>
            )}

            <FormField
                id="activity_description"
                label="Deskripsi aktivitas"
                required
                hint="Catat kegiatan yang dilakukan, pihak yang terlibat, dan hasil pengamatan."
                error={form.errors.activity_description}
                footer={
                    <CharacterCount
                        value={form.data.activity_description}
                        max={20000}
                    />
                }
            >
                <Textarea
                    value={form.data.activity_description}
                    maxLength={20000}
                    onChange={(event) =>
                        form.setData('activity_description', event.target.value)
                    }
                    placeholder="Contoh: Tim mengumpulkan data proses selama minggu pertama dan menemukan..."
                    className="min-h-28"
                />
            </FormField>
            <FormField
                id="plan_text"
                label="Rencana"
                required
                hint="Tuliskan langkah berikutnya, penanggung jawab, dan target waktunya."
                error={form.errors.plan_text}
                footer={
                    <CharacterCount value={form.data.plan_text} max={20000} />
                }
            >
                <Textarea
                    value={form.data.plan_text}
                    maxLength={20000}
                    onChange={(event) =>
                        form.setData('plan_text', event.target.value)
                    }
                    placeholder="Contoh: Validasi data bersama tim, lalu dokumentasikan hasil sebelum..."
                    className="min-h-28"
                />
            </FormField>
            <FormField
                id="actual_text"
                label="Aktual / hasil"
                hint="Bandingkan hasil aktual dengan rencana; sertakan data atau kendala bila ada."
                error={form.errors.actual_text}
                footer={
                    <CharacterCount value={form.data.actual_text} max={20000} />
                }
            >
                <Textarea
                    value={form.data.actual_text}
                    maxLength={20000}
                    onChange={(event) =>
                        form.setData('actual_text', event.target.value)
                    }
                    placeholder="Contoh: Data menunjukkan waktu proses turun dari... menjadi..."
                    className="min-h-28"
                />
            </FormField>
            <div className="grid gap-5 sm:grid-cols-2">
                <FormField
                    id="target_date"
                    label="Target / tenggat"
                    error={form.errors.target_date}
                >
                    <DatePicker
                        value={form.data.target_date}
                        onValueChange={(value) =>
                            form.setData('target_date', value)
                        }
                        placeholder="Pilih tanggal target"
                    />
                </FormField>
                <FormField
                    id="progress_pct"
                    label="Progress (%)"
                    error={form.errors.progress_pct}
                >
                    <Input
                        type="number"
                        inputMode="numeric"
                        min={0}
                        max={100}
                        value={form.data.progress_pct}
                        onChange={(event) =>
                            form.setData('progress_pct', event.target.value)
                        }
                        placeholder="0–100"
                        className="h-10"
                    />
                </FormField>
            </div>
            <Progress
                value={progress}
                aria-label="Progress step"
                className="gap-0"
            >
                <span className="sr-only">{progress}%</span>
            </Progress>
            <FormField
                id="submitter_note"
                label="Catatan pengaju"
                hint="Opsional: tambahkan konteks yang perlu diketahui reviewer."
                error={form.errors.submitter_note}
                footer={
                    <CharacterCount value={form.data.submitter_note} max={5000} />
                }
            >
                <Textarea
                    value={form.data.submitter_note}
                    maxLength={5000}
                    onChange={(event) =>
                        form.setData('submitter_note', event.target.value)
                    }
                    placeholder="Tambahkan catatan singkat untuk reviewer"
                    className="min-h-20"
                />
            </FormField>

            <div className="flex flex-wrap justify-end gap-2">
                <Button
                    type="submit"
                    variant="default"
                    className="h-10 px-4"
                    disabled={form.processing}
                >
                    {form.processing ? (
                        <Spinner data-icon="inline-start" />
                    ) : (
                        <Save data-icon="inline-start" aria-hidden="true" />
                    )}
                    Simpan Draft
                </Button>
                {step.can_submit && (
                    <AlertDialog>
                        <AlertDialogTrigger
                            render={
                                <Button
                                    type="button"
                                    className="h-10 px-4"
                                    disabled={form.processing}
                                />
                            }
                        >
                            <Send data-icon="inline-start" aria-hidden="true" />
                            Ajukan Step
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>
                                    Ajukan step untuk review?
                                </AlertDialogTitle>
                                <AlertDialogDescription>
                                    Setelah diajukan, isi step dikunci sampai
                                    reviewer menyetujui atau meminta revisi.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Batal</AlertDialogCancel>
                                <AlertDialogAction
                                    onClick={() =>
                                        form.post(submit.url(target), {
                                            preserveScroll: true,
                                        })
                                    }
                                >
                                    Ya, ajukan
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                )}
            </div>
        </form>
    );
}
