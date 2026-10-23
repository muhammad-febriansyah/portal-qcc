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
                error={form.errors.activity_description}
            >
                <Textarea
                    value={form.data.activity_description}
                    onChange={(event) =>
                        form.setData('activity_description', event.target.value)
                    }
                    placeholder="Jelaskan aktivitas pada step ini"
                    className="min-h-24"
                />
            </FormField>
            <FormField
                id="plan_text"
                label="Rencana"
                required
                error={form.errors.plan_text}
            >
                <Textarea
                    value={form.data.plan_text}
                    onChange={(event) =>
                        form.setData('plan_text', event.target.value)
                    }
                    placeholder="Apa yang direncanakan?"
                    className="min-h-24"
                />
            </FormField>
            <FormField
                id="actual_text"
                label="Aktual / hasil"
                error={form.errors.actual_text}
            >
                <Textarea
                    value={form.data.actual_text}
                    onChange={(event) =>
                        form.setData('actual_text', event.target.value)
                    }
                    placeholder="Tuliskan realisasi dan hasil"
                    className="min-h-24"
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
                error={form.errors.submitter_note}
            >
                <Textarea
                    value={form.data.submitter_note}
                    onChange={(event) =>
                        form.setData('submitter_note', event.target.value)
                    }
                    placeholder="Tambahkan catatan untuk reviewer"
                    className="min-h-20"
                />
            </FormField>

            <div className="flex flex-wrap justify-end gap-2">
                <Button
                    type="submit"
                    variant="outline"
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
