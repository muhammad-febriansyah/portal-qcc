import { router } from '@inertiajs/react';
import { Check, MessageSquareWarning, Play } from 'lucide-react';
import { useState } from 'react';
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
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { approve, revision } from '@/routes/projects/steps';
import { start } from '@/routes/projects/steps/review';

type Props = {
    projectId: number;
    stepNumber: number;
    canStartReview: boolean;
    canApprove: boolean;
    canRequestRevision: boolean;
    errors: Record<string, string>;
};

/**
 * What a reviewer can do with a submitted step. Approving and asking for a
 * revision are separate permissions, and a revision always needs a reason.
 */
export function ReviewControls({
    projectId,
    stepNumber,
    canStartReview,
    canApprove,
    canRequestRevision,
    errors,
}: Props) {
    const target = { project: projectId, step: stepNumber };
    const [busy, setBusy] = useState(false);
    const [reason, setReason] = useState('');
    const [open, setOpen] = useState(false);

    const post = (
        url: string,
        data: Record<string, string> = {},
        onSuccess?: () => void,
    ) =>
        router.post(url, data, {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
            onSuccess,
        });

    if (!canStartReview && !canApprove && !canRequestRevision) {
        return null;
    }

    return (
        <div className="grid gap-3 rounded-lg border bg-muted/30 p-4">
            <p className="text-sm font-medium">Review step</p>
            {errors.step && (
                <p className="text-[13px] text-destructive" role="alert">
                    {errors.step}
                </p>
            )}
            <div className="flex flex-wrap gap-2">
                {canStartReview && (
                    <Button
                        type="button"
                        className="h-10 px-4"
                        disabled={busy}
                        onClick={() => post(start.url(target))}
                    >
                        {busy ? (
                            <Spinner data-icon="inline-start" />
                        ) : (
                            <Play data-icon="inline-start" aria-hidden="true" />
                        )}
                        Mulai Review
                    </Button>
                )}
                {canApprove && (
                    <AlertDialog>
                        <AlertDialogTrigger
                            render={
                                <Button
                                    type="button"
                                    className="h-10 px-4"
                                    disabled={busy}
                                />
                            }
                        >
                            <Check
                                data-icon="inline-start"
                                aria-hidden="true"
                            />
                            Setujui Step
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>
                                    Setujui step ini?
                                </AlertDialogTitle>
                                <AlertDialogDescription>
                                    Progress step menjadi 100% dan isinya tidak
                                    bisa diubah lagi.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Batal</AlertDialogCancel>
                                <AlertDialogAction
                                    onClick={() => post(approve.url(target))}
                                >
                                    Ya, setujui
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                )}
                {canRequestRevision && (
                    <Dialog open={open} onOpenChange={setOpen}>
                        <DialogTrigger
                            render={
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="h-10 px-4"
                                    disabled={busy}
                                />
                            }
                        >
                            <MessageSquareWarning
                                data-icon="inline-start"
                                aria-hidden="true"
                            />
                            Minta Revisi
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Minta revisi step</DialogTitle>
                                <DialogDescription>
                                    Tim akan melihat alasan ini dan bisa
                                    memperbaiki step.
                                </DialogDescription>
                            </DialogHeader>
                            <FormField
                                id="revision-reason"
                                label="Alasan revisi"
                                required
                                error={errors.reason}
                            >
                                <Textarea
                                    value={reason}
                                    onChange={(event) =>
                                        setReason(event.target.value)
                                    }
                                    placeholder="Jelaskan apa yang perlu diperbaiki"
                                    className="min-h-28"
                                />
                            </FormField>
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setOpen(false)}
                                >
                                    Batal
                                </Button>
                                <Button
                                    type="button"
                                    disabled={busy}
                                    onClick={() =>
                                        post(
                                            revision.url(target),
                                            { reason },
                                            () => {
                                                setOpen(false);
                                                setReason('');
                                            },
                                        )
                                    }
                                >
                                    {busy && (
                                        <Spinner data-icon="inline-start" />
                                    )}
                                    Kirim Revisi
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                )}
            </div>
        </div>
    );
}
