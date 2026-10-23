import { Head, Link, useForm } from '@inertiajs/react';
import { formatWeight } from '@/lib/format-weight';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    Clock3,
    FileSearch2,
    RotateCcw,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { FormField } from '@/components/form-field';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { scoringStatusBadgeVariant } from '@/lib/badge-variants';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { dashboard } from '@/routes';
import { approve, index, requestRevision, show } from '@/routes/admin/scoring';

type CriterionScore = {
    criterionId: number;
    code: string;
    name: string;
    weight: string | null;
    aiScore: string | null;
    reviewerScore: string | null;
    finalScore: string | null;
    rationale: string | null;
    gaps: string[];
    uncertainty: string | null;
    evidenceReferences: { evidence_id: number; locator: string }[];
};

type Run = {
    id: number;
    projectId: number;
    projectTitle: string;
    status: string;
    statusLabel: string;
    requestedBy: string;
    rubricName: string;
    scoringType: string;
    rubricVersion: number | null;
    model: string | null;
    promptVersion: string | null;
    attemptCount: number;
    createdAt: string | null;
    startedAt: string | null;
    completedAt: string | null;
    errorSummary: string | null;
    canRequestRevision: boolean;
    canApprove: boolean;
    manifestCount: number;
    minimumEvidence: number | null;
    evidence: { id: number | null; title: string }[];
    result: null | {
        summary: string | null;
        aiScore: string | null;
        reviewerScore: string | null;
        finalScore: string | null;
        reviewComment: string | null;
        reviewerName: string | null;
        reviewedAt: string | null;
        cautions: string[];
        criteria: CriterionScore[];
    };
};

type ApprovalForm = {
    scores: Record<string, string>;
    reason: string;
    comment: string;
};

type RevisionForm = { reason: string };

function scoreLabel(score: string | null): string {
    return score === null ? '—' : `${Number(score).toFixed(2)} / 100`;
}

export default function ScoringShow({ run }: { run: Run }) {
    const [isApprovalOpen, setIsApprovalOpen] = useState(false);
    const [isRevisionOpen, setIsRevisionOpen] = useState(false);
    const approvalForm = useForm<ApprovalForm>({
        scores: Object.fromEntries(
            (run.result?.criteria ?? []).map((criterion) => [
                String(criterion.criterionId),
                criterion.aiScore ?? '',
            ]),
        ),
        reason: '',
        comment: '',
    });
    const revisionForm = useForm<RevisionForm>({ reason: '' });
    const criteria = run.result?.criteria ?? [];
    const approvalStatusError = (
        approvalForm.errors as Record<string, string | undefined>
    ).status;
    const revisionStatusError = (
        revisionForm.errors as Record<string, string | undefined>
    ).status;
    const changedScores = criteria.filter((criterion) => {
        const score =
            approvalForm.data.scores[String(criterion.criterionId)] ?? '';

        return score === '' || Number(score) !== Number(criterion.aiScore);
    });

    function submitApproval(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        approvalForm.post(approve.url(run.id), {
            preserveScroll: true,
            onSuccess: () => {
                setIsApprovalOpen(false);
                toast.success('Penilaian berhasil disetujui.');
            },
        });
    }

    function submitRevision(event: React.FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        revisionForm.post(requestRevision.url(run.id), {
            preserveScroll: true,
            onSuccess: () => {
                setIsRevisionOpen(false);
                toast.success('Permintaan revisi berhasil dikirim.');
            },
        });
    }

    return (
        <>
            <Head title={`Scoring proyek · ${run.projectTitle}`} />
            <PageHeader
                breadcrumbs={[
                    { title: 'Dashboard', href: dashboard() },
                    { title: 'AI Scoring', href: index() },
                    { title: `Run #${run.id}`, href: show(run.id) },
                ]}
                title={run.projectTitle}
                description={`Run #${run.id} · ${run.rubricName} · versi ${run.rubricVersion ?? '—'}`}
                actions={
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            render={<Link href={index()} />}
                            nativeButton={false}
                            variant="outline"
                        >
                            <ArrowLeft aria-hidden="true" /> Kembali
                        </Button>
                        {run.status === 'pending_review' &&
                            run.canRequestRevision && (
                                <Button
                                    variant="outline"
                                    onClick={() => setIsRevisionOpen(true)}
                                >
                                    <RotateCcw aria-hidden="true" /> Minta
                                    Revisi
                                </Button>
                            )}
                        {run.status === 'pending_review' && run.canApprove && (
                            <Button onClick={() => setIsApprovalOpen(true)}>
                                <CheckCircle2 aria-hidden="true" /> Setujui
                                Penilaian
                            </Button>
                        )}
                    </div>
                }
            />

            <div className="space-y-5">
                <Card>
                    <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3">
                        <CardTitle className="flex items-center gap-2">
                            Status run{' '}
                            <Badge
                                variant={scoringStatusBadgeVariant(run.status)}
                            >
                                {run.statusLabel}
                            </Badge>
                        </CardTitle>
                        <span className="text-sm text-muted-foreground">
                            Diminta {run.createdAt ?? '—'} oleh{' '}
                            {run.requestedBy}
                        </span>
                    </CardHeader>
                    <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Metadata
                            label="Mulai"
                            value={run.startedAt}
                            icon={<Clock3 aria-hidden="true" />}
                        />
                        <Metadata
                            label="Selesai"
                            value={run.completedAt}
                            icon={<CheckCircle2 aria-hidden="true" />}
                        />
                        <Metadata
                            label="Model"
                            value={run.model ?? 'Belum dijalankan'}
                        />
                        <Metadata
                            label="Percobaan"
                            value={String(run.attemptCount)}
                        />
                    </CardContent>
                </Card>

                {run.errorSummary && (
                    <Card className="border-destructive/40">
                        <CardContent className="flex gap-3 pt-4 text-sm">
                            <XCircle
                                className="mt-0.5 size-4 shrink-0 text-destructive"
                                aria-hidden="true"
                            />
                            <div>
                                <p className="font-medium">
                                    Scoring tidak selesai
                                </p>
                                <p className="mt-1 text-muted-foreground">
                                    {run.errorSummary}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-5 xl:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
                    <div className="space-y-5">
                        <Card>
                            <CardHeader>
                                <CardTitle>Hasil penilaian</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-5">
                                {run.result ? (
                                    <>
                                        <div className="grid gap-3 sm:grid-cols-3">
                                            <Score
                                                label="Skor AI"
                                                value={run.result.aiScore}
                                            />
                                            <Score
                                                label="Skor reviewer"
                                                value={run.result.reviewerScore}
                                            />
                                            <Score
                                                label="Skor final"
                                                value={run.result.finalScore}
                                            />
                                        </div>
                                        <p className="text-sm leading-6 whitespace-pre-wrap text-muted-foreground">
                                            {run.result.summary ||
                                                'Ringkasan belum tersedia.'}
                                        </p>
                                        {run.result.cautions.length > 0 && (
                                            <div className="rounded-lg border border-amber-300/60 bg-amber-50/70 p-4 text-sm dark:bg-amber-950/20">
                                                <p className="mb-2 flex items-center gap-2 font-medium">
                                                    <AlertTriangle
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                    Catatan kehati-hatian
                                                </p>
                                                <ul className="list-disc space-y-1 pl-5 text-muted-foreground">
                                                    {run.result.cautions.map(
                                                        (item, position) => (
                                                            <li
                                                                key={`${position}-${item}`}
                                                            >
                                                                {item}
                                                            </li>
                                                        ),
                                                    )}
                                                </ul>
                                            </div>
                                        )}
                                        {run.result.reviewComment && (
                                            <p className="text-sm">
                                                <span className="font-medium">
                                                    Catatan reviewer:
                                                </span>{' '}
                                                {run.result.reviewComment}
                                            </p>
                                        )}
                                        {run.result.reviewedAt && (
                                            <p className="text-xs text-muted-foreground">
                                                Direview {run.result.reviewedAt}
                                                {run.result.reviewerName
                                                    ? ` oleh ${run.result.reviewerName}`
                                                    : ''}
                                            </p>
                                        )}
                                    </>
                                ) : (
                                    <div className="flex min-h-28 items-center gap-3 rounded-lg border border-dashed p-5 text-sm text-muted-foreground">
                                        <FileSearch2
                                            className="size-5 shrink-0"
                                            aria-hidden="true"
                                        />
                                        Hasil per kriteria muncul setelah AI
                                        menyelesaikan scoring.
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {run.result && (
                            <section
                                className="space-y-3"
                                aria-labelledby="criteria-title"
                            >
                                <h2
                                    id="criteria-title"
                                    className="text-base font-semibold"
                                >
                                    Skor per kriteria
                                </h2>
                                {run.result.criteria.length === 0 ? (
                                    <Card>
                                        <CardContent className="py-8 text-center text-sm text-muted-foreground">
                                            Belum ada rincian kriteria.
                                        </CardContent>
                                    </Card>
                                ) : (
                                    run.result.criteria.map((criterion) => (
                                        <Card key={criterion.code}>
                                            <CardHeader className="flex flex-row flex-wrap items-start justify-between gap-3">
                                                <div>
                                                    <CardTitle>
                                                        {criterion.code} ·{' '}
                                                        {criterion.name}
                                                    </CardTitle>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        Bobot{' '}
                                                        {criterion.weight ===
                                                        null
                                                            ? '—'
                                                            : `${formatWeight(criterion.weight)}%`}
                                                    </p>
                                                </div>
                                                <div className="flex flex-wrap gap-2">
                                                    <Badge variant="info">
                                                        AI:{' '}
                                                        {scoreLabel(
                                                            criterion.aiScore,
                                                        )}
                                                    </Badge>
                                                    {criterion.reviewerScore !==
                                                        null && (
                                                        <Badge variant="violet">
                                                            Reviewer:{' '}
                                                            {scoreLabel(
                                                                criterion.reviewerScore,
                                                            )}
                                                        </Badge>
                                                    )}
                                                    {criterion.finalScore !==
                                                        null && (
                                                        <Badge variant="success">
                                                            Final:{' '}
                                                            {scoreLabel(
                                                                criterion.finalScore,
                                                            )}
                                                        </Badge>
                                                    )}
                                                </div>
                                            </CardHeader>
                                            <CardContent className="space-y-4 text-sm">
                                                <p className="leading-6 whitespace-pre-wrap text-muted-foreground">
                                                    {criterion.rationale ||
                                                        'Alasan belum tersedia.'}
                                                </p>
                                                {criterion.gaps.length > 0 && (
                                                    <InfoList
                                                        title="Kesenjangan evidence"
                                                        items={criterion.gaps}
                                                    />
                                                )}
                                                {criterion.uncertainty && (
                                                    <p className="rounded-md bg-muted/50 p-3 text-muted-foreground">
                                                        <span className="font-medium text-foreground">
                                                            Ketidakpastian:
                                                        </span>{' '}
                                                        {criterion.uncertainty}
                                                    </p>
                                                )}
                                                {criterion.evidenceReferences
                                                    .length > 0 && (
                                                    <div>
                                                        <p className="mb-2 font-medium">
                                                            Rujukan evidence
                                                        </p>
                                                        <ul className="space-y-1 text-muted-foreground">
                                                            {criterion.evidenceReferences.map(
                                                                (
                                                                    reference,
                                                                    position,
                                                                ) => (
                                                                    <li
                                                                        key={`${reference.evidence_id}-${position}`}
                                                                    >
                                                                        Evidence
                                                                        #
                                                                        {
                                                                            reference.evidence_id
                                                                        }{' '}
                                                                        ·{' '}
                                                                        {
                                                                            reference.locator
                                                                        }
                                                                    </li>
                                                                ),
                                                            )}
                                                        </ul>
                                                    </div>
                                                )}
                                            </CardContent>
                                        </Card>
                                    ))
                                )}
                            </section>
                        )}
                    </div>

                    <aside className="space-y-5">
                        <Card>
                            <CardHeader>
                                <CardTitle>Evidence yang diperiksa</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                <p className="text-muted-foreground">
                                    {run.manifestCount} evidence · minimum{' '}
                                    {run.minimumEvidence ?? '—'}
                                </p>
                                {run.evidence.length > 0 ? (
                                    <ul className="space-y-2">
                                        {run.evidence.map((item, position) => (
                                            <li
                                                key={`${item.id ?? 'evidence'}-${position}`}
                                                className="rounded-md border p-3"
                                            >
                                                {item.title}
                                                <span className="mt-1 block text-xs text-muted-foreground">
                                                    ID #{item.id ?? '—'}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                ) : (
                                    <p className="rounded-lg border border-dashed p-4 text-muted-foreground">
                                        Manifest evidence belum tersedia.
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>Informasi run</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                <Metadata
                                    label="Jenis rubrik"
                                    value={run.scoringType || '—'}
                                />
                                <Metadata
                                    label="Versi prompt"
                                    value={run.promptVersion ?? '—'}
                                />
                                <Metadata
                                    label="ID proyek"
                                    value={`#${run.projectId}`}
                                />
                            </CardContent>
                        </Card>
                    </aside>
                </div>
            </div>

            <Dialog open={isApprovalOpen} onOpenChange={setIsApprovalOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl sm:gap-6 sm:p-6">
                    <form onSubmit={submitApproval} className="space-y-6">
                        <DialogHeader>
                            <DialogTitle>Setujui penilaian</DialogTitle>
                        </DialogHeader>
                        {approvalStatusError && (
                            <p
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {approvalStatusError}
                            </p>
                        )}
                        <div className="space-y-6">
                            {criteria.map((criterion) => {
                                const fieldKey = String(criterion.criterionId);

                                return (
                                    <FormField
                                        key={fieldKey}
                                        id={`review-score-${fieldKey}`}
                                        label={`Nilai reviewer · ${criterion.code}`}
                                        required
                                        error={
                                            approvalForm.errors[
                                                `scores.${fieldKey}`
                                            ]
                                        }
                                    >
                                        <Input
                                            type="number"
                                            min="0"
                                            max="100"
                                            step="0.01"
                                            placeholder={`Nilai AI: ${criterion.aiScore ?? '—'}`}
                                            value={
                                                approvalForm.data.scores[
                                                    fieldKey
                                                ] ?? ''
                                            }
                                            aria-label={`Nilai reviewer untuk ${criterion.name}`}
                                            onChange={(event) => {
                                                const scores = {
                                                    ...approvalForm.data.scores,
                                                    [fieldKey]:
                                                        event.target.value,
                                                };
                                                approvalForm.setData(
                                                    'scores',
                                                    scores,
                                                );
                                            }}
                                        />
                                    </FormField>
                                );
                            })}
                            {approvalForm.errors.scores && (
                                <p
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {approvalForm.errors.scores}
                                </p>
                            )}
                            <FormField
                                id="review-reason"
                                label="Alasan perubahan skor"
                                required={changedScores.length > 0}
                                error={approvalForm.errors.reason}
                            >
                                <Textarea
                                    placeholder={
                                        changedScores.length > 0
                                            ? 'Jelaskan alasan perubahan skor...'
                                            : 'Opsional bila skor tidak diubah'
                                    }
                                    value={approvalForm.data.reason}
                                    onChange={(event) =>
                                        approvalForm.setData(
                                            'reason',
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                            <FormField
                                id="review-comment"
                                label="Catatan review"
                                error={approvalForm.errors.comment}
                            >
                                <Textarea
                                    placeholder="Tambahkan catatan review (opsional)..."
                                    value={approvalForm.data.comment}
                                    onChange={(event) =>
                                        approvalForm.setData(
                                            'comment',
                                            event.target.value,
                                        )
                                    }
                                />
                            </FormField>
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsApprovalOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={approvalForm.processing}
                            >
                                Setujui Penilaian
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={isRevisionOpen} onOpenChange={setIsRevisionOpen}>
                <DialogContent className="sm:max-w-lg sm:gap-6 sm:p-6">
                    <form onSubmit={submitRevision} className="space-y-6">
                        <DialogHeader>
                            <DialogTitle>Minta revisi penilaian</DialogTitle>
                        </DialogHeader>
                        {revisionStatusError && (
                            <p
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {revisionStatusError}
                            </p>
                        )}
                        <FormField
                            id="revision-reason"
                            label="Alasan revisi"
                            required
                            error={revisionForm.errors.reason}
                        >
                            <Textarea
                                placeholder="Jelaskan bagian yang perlu diperbaiki..."
                                value={revisionForm.data.reason}
                                onChange={(event) =>
                                    revisionForm.setData(
                                        'reason',
                                        event.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setIsRevisionOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={revisionForm.processing}
                            >
                                Kirim Permintaan Revisi
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

function Metadata({
    label,
    value,
    icon,
}: {
    label: string;
    value: string | null;
    icon?: React.ReactNode;
}) {
    return (
        <div className="min-w-0">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 flex items-center gap-2 font-medium break-words">
                {icon}
                {value ?? '—'}
            </p>
        </div>
    );
}

function Score({ label, value }: { label: string; value: string | null }) {
    return (
        <div className="rounded-lg border p-4">
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="mt-1 text-lg font-semibold tabular-nums">
                {scoreLabel(value)}
            </p>
        </div>
    );
}

function InfoList({ title, items }: { title: string; items: string[] }) {
    return (
        <div className="rounded-md border border-amber-300/50 bg-amber-50/60 p-3 dark:bg-amber-950/20">
            <p className="mb-2 flex items-center gap-2 font-medium">
                <AlertTriangle className="size-4" aria-hidden="true" />
                {title}
            </p>
            <ul className="list-disc space-y-1 pl-5 text-muted-foreground">
                {items.map((item, position) => (
                    <li key={`${position}-${item}`}>{item}</li>
                ))}
            </ul>
        </div>
    );
}
