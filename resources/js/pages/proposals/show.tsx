import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import {
    ArrowLeft,
    Download,
    FileText,
    PencilLine,
    Paperclip,
    ClipboardCheck,
    CheckCircle2,
    UsersRound,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { BadgeVariant } from '@/lib/badge-variants';
import { dashboard } from '@/routes';
import { candidates, edit, index } from '@/routes/proposals';
import proposalRoutes from '@/routes/proposals';
import type { BreadcrumbItem } from '@/types';

type ProposalAttachment = {
    id: number;
    name: string;
    mime_type: string;
    size_bytes: number;
    scan_status: string;
    preview_url: string;
};

type Proposal = {
    id: number;
    code: string;
    title: string;
    batch: string | null;
    proposer: string | null;
    organization: string | null;
    status: string;
    status_label: string;
    theme: string | null;
    background: string | null;
    problem_statement: string | null;
    current_situation: string | null;
    baseline: string | null;
    target: string | null;
    scope: string | null;
    learning_goals: string[];
    rca: string | null;
    improvement_plan: string | null;
    business_benefit: string | null;
    updated_at: string | null;
    attachments: ProposalAttachment[];
    can_edit_draft: boolean;
    can_find_candidates: boolean;
    can_submit: boolean;
    team_confirmed: boolean;
    team_status_message: string | null;
    can_start_review: boolean;
    can_decide: boolean;
    review_history: {
        id: number;
        decision: string;
        previous_status: string | null;
        new_status: string;
        reason: string | null;
        actor: string | null;
        decided_at: string;
    }[];
};

type Props = { proposal: Proposal };

const statusVariants: Record<string, BadgeVariant> = {
    DRAFT: 'secondary',
    SUBMITTED: 'info',
    UNDER_REVIEW: 'violet',
    REVISION_REQUIRED: 'warning',
    APPROVED: 'success',
    REJECTED: 'destructive',
};

function DetailField({
    label,
    value,
}: {
    label: string;
    value: string | null | undefined;
}) {
    return (
        <div className="min-w-0 space-y-1">
            <dt className="text-xs font-medium text-muted-foreground">
                {label}
            </dt>
            <dd className="text-sm whitespace-pre-wrap text-foreground">
                {value?.trim() ? value : '—'}
            </dd>
        </div>
    );
}

function fileSize(bytes: number): string {
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export default function ProposalShow({ proposal }: Props) {
    const [decisionOpen, setDecisionOpen] = useState(false);
    const decisionForm = useForm<{ decision: string; reason: string }>({
        decision: 'APPROVE',
        reason: '',
    });
    const { errors } = usePage().props as { errors: Record<string, string> };
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Proposal QCC', href: index() },
    ];

    function submitDecision(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        decisionForm.post(proposalRoutes.review.decide.url(proposal.id), {
            preserveScroll: true,
            onSuccess: () => {
                setDecisionOpen(false);
                decisionForm.reset();
            },
        });
    }

    return (
        <>
            <Head title={`${proposal.code} · Proposal QCC`} />
            <div className="mx-auto w-full max-w-[1440px] px-4 py-6 md:px-6 md:py-8">
                <PageHeader
                    breadcrumbs={breadcrumbs}
                    title={proposal.title}
                    description={`${proposal.code} · ${proposal.batch ?? 'Tanpa batch'}`}
                    actions={
                        <>
                            {proposal.can_edit_draft && (
                                <Button
                                    variant="outline"
                                    render={
                                        <Link href={edit.url(proposal.id)} />
                                    }
                                    nativeButton={false}
                                >
                                    <PencilLine data-icon="inline-start" />
                                    {proposal.status === 'REVISION_REQUIRED'
                                        ? 'Edit untuk revisi'
                                        : 'Lanjutkan draft'}
                                </Button>
                            )}
                            {proposal.can_find_candidates && (
                                <Button
                                    render={
                                        <Link
                                            href={candidates.url(proposal.id)}
                                        />
                                    }
                                    nativeButton={false}
                                >
                                    <UsersRound data-icon="inline-start" />
                                    Cari kandidat tim
                                </Button>
                            )}
                            {proposal.can_submit && (
                                <Button
                                    disabled={!proposal.team_confirmed}
                                    onClick={() =>
                                        router.post(
                                            proposalRoutes.submit.url(
                                                proposal.id,
                                            ),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <ClipboardCheck data-icon="inline-start" />
                                    {proposal.status === 'REVISION_REQUIRED'
                                        ? 'Ajukan revisi'
                                        : 'Ajukan proposal'}
                                </Button>
                            )}
                            {proposal.can_start_review && (
                                <Button
                                    onClick={() =>
                                        router.post(
                                            proposalRoutes.review.start.url(
                                                proposal.id,
                                            ),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <ClipboardCheck data-icon="inline-start" />{' '}
                                    Mulai review
                                </Button>
                            )}
                            {proposal.can_decide && (
                                <Button
                                    onClick={() => {
                                        decisionForm.resetAndClearErrors();
                                        setDecisionOpen(true);
                                    }}
                                >
                                    <CheckCircle2 data-icon="inline-start" />{' '}
                                    Beri keputusan
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="space-y-5">
                    {proposal.can_edit_draft &&
                        !proposal.can_find_candidates && (
                            <Alert>
                                <UsersRound aria-hidden="true" />
                                <AlertTitle>
                                    {proposal.team_confirmed
                                        ? 'Tim proposal sudah dikonfirmasi'
                                        : 'Susunan tim menunggu Owner QCC'}
                                </AlertTitle>
                                <AlertDescription>
                                    {proposal.team_status_message}
                                </AlertDescription>
                            </Alert>
                        )}
                    {proposal.can_submit && !proposal.team_confirmed && (
                        <p
                            className="text-sm text-muted-foreground"
                            role="status"
                        >
                            Proposal belum dapat diajukan karena tim belum
                            dikonfirmasi.
                        </p>
                    )}
                    {Object.keys(errors).length > 0 && (
                        <div
                            role="alert"
                            className="rounded-xl border border-destructive/25 bg-destructive/5 px-4 py-3 text-sm text-destructive"
                        >
                            <p className="font-medium">
                                Tindakan belum dapat diproses.
                            </p>
                            <ul className="mt-1 list-inside list-disc space-y-0.5">
                                {Object.entries(errors).map(
                                    ([field, message]) => (
                                        <li key={field}>{message}</li>
                                    ),
                                )}
                            </ul>
                        </div>
                    )}
                    <Card>
                        <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3">
                            <div className="space-y-1">
                                <CardTitle className="text-base">
                                    Informasi proposal
                                </CardTitle>
                                <CardDescription>
                                    Terakhir diperbarui{' '}
                                    {proposal.updated_at
                                        ? format(
                                              parseISO(proposal.updated_at),
                                              'd MMMM yyyy, HH:mm',
                                              { locale: indonesianLocale },
                                          )
                                        : '—'}
                                </CardDescription>
                            </div>
                            <Badge
                                variant={
                                    statusVariants[proposal.status] ??
                                    'secondary'
                                }
                            >
                                {proposal.status_label}
                            </Badge>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-x-8 gap-y-5 sm:grid-cols-2 lg:grid-cols-3">
                                <DetailField
                                    label="Pengaju"
                                    value={proposal.proposer}
                                />
                                <DetailField
                                    label="LOB / Divisi"
                                    value={proposal.organization}
                                />
                                <DetailField
                                    label="Tema Improvement"
                                    value={proposal.theme}
                                />
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Masalah & baseline
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-x-8 gap-y-5 sm:grid-cols-2">
                                <DetailField
                                    label="Latar belakang"
                                    value={proposal.background}
                                />
                                <DetailField
                                    label="Pernyataan masalah"
                                    value={proposal.problem_statement}
                                />
                                <DetailField
                                    label="Kondisi saat ini"
                                    value={proposal.current_situation}
                                />
                                <DetailField
                                    label="Baseline"
                                    value={proposal.baseline}
                                />
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Target & manfaat
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-x-8 gap-y-5 sm:grid-cols-2">
                                <DetailField
                                    label="Target"
                                    value={proposal.target}
                                />
                                <DetailField
                                    label="Ruang lingkup"
                                    value={proposal.scope}
                                />
                                <DetailField
                                    label="Root cause analysis"
                                    value={proposal.rca}
                                />
                                <DetailField
                                    label="Rencana improvement"
                                    value={proposal.improvement_plan}
                                />
                                <DetailField
                                    label="Manfaat bisnis"
                                    value={proposal.business_benefit}
                                />
                                <div className="space-y-1">
                                    <dt className="text-xs font-medium text-muted-foreground">
                                        Tujuan pembelajaran
                                    </dt>
                                    <dd>
                                        {proposal.learning_goals.length > 0 ? (
                                            <ul className="list-inside list-disc space-y-1 text-sm">
                                                {proposal.learning_goals.map(
                                                    (goal, position) => (
                                                        <li key={position}>
                                                            {goal}
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        ) : (
                                            <span className="text-sm text-foreground">
                                                —
                                            </span>
                                        )}
                                    </dd>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Paperclip
                                    className="size-4 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                Lampiran ({proposal.attachments.length})
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            {proposal.attachments.length > 0 ? (
                                <ul className="divide-y divide-border/60">
                                    {proposal.attachments.map((attachment) => (
                                        <li
                                            key={attachment.id}
                                            className="flex flex-col gap-3 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center"
                                        >
                                            <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                <FileText
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm font-medium break-all">
                                                    {attachment.name}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {attachment.mime_type} ·{' '}
                                                    {fileSize(
                                                        attachment.size_bytes,
                                                    )}
                                                </p>
                                            </div>
                                            {attachment.scan_status ===
                                                'PENDING_SCAN' && (
                                                <Badge variant="warning">
                                                    Menunggu pemindaian
                                                </Badge>
                                            )}
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                render={
                                                    <a
                                                        href={
                                                            attachment.preview_url
                                                        }
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    />
                                                }
                                                nativeButton={false}
                                            >
                                                <Download data-icon="inline-start" />
                                                Buka
                                            </Button>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Belum ada lampiran.
                                </p>
                            )}
                        </CardContent>
                    </Card>

                    {proposal.review_history.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Riwayat pengajuan & review
                                </CardTitle>
                                <CardDescription>
                                    Catatan keputusan tersimpan sebagai riwayat
                                    proposal.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <ol className="space-y-4">
                                    {proposal.review_history.map((item) => (
                                        <li
                                            key={item.id}
                                            className="relative border-l border-border pl-4 last:border-transparent"
                                        >
                                            <p className="text-sm font-medium">
                                                {decisionLabels[
                                                    item.decision
                                                ] ?? item.decision}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {item.actor ?? 'Pengguna'} ·{' '}
                                                {format(
                                                    parseISO(item.decided_at),
                                                    'd MMMM yyyy, HH:mm',
                                                    {
                                                        locale: indonesianLocale,
                                                    },
                                                )}
                                            </p>
                                            {item.reason && (
                                                <p className="mt-2 text-sm whitespace-pre-wrap">
                                                    {item.reason}
                                                </p>
                                            )}
                                        </li>
                                    ))}
                                </ol>
                            </CardContent>
                        </Card>
                    )}

                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            render={<Link href={index()} />}
                            nativeButton={false}
                        >
                            <ArrowLeft data-icon="inline-start" />
                            Kembali ke daftar
                        </Button>
                    </div>
                </div>
            </div>
            <Dialog open={decisionOpen} onOpenChange={setDecisionOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Keputusan review</DialogTitle>
                        <DialogDescription>
                            Pilih hasil review proposal. Alasan wajib untuk
                            revisi atau penolakan.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitDecision} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="review-decision">Keputusan</Label>
                            <select
                                id="review-decision"
                                className="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                value={decisionForm.data.decision}
                                onChange={(event) =>
                                    decisionForm.setData(
                                        'decision',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="APPROVE">Setujui</option>
                                <option value="REQUEST_REVISION">
                                    Minta revisi
                                </option>
                                <option value="REJECT">Tolak</option>
                            </select>
                        </div>
                        {decisionForm.data.decision !== 'APPROVE' && (
                            <div className="space-y-2">
                                <Label htmlFor="review-reason">
                                    Alasan{' '}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <Textarea
                                    id="review-reason"
                                    value={decisionForm.data.reason}
                                    onChange={(event) =>
                                        decisionForm.setData(
                                            'reason',
                                            event.target.value,
                                        )
                                    }
                                    maxLength={5000}
                                    rows={4}
                                    placeholder="Tuliskan masukan yang perlu diketahui pengaju."
                                />
                                {decisionForm.errors.reason && (
                                    <p className="text-sm text-destructive">
                                        {decisionForm.errors.reason}
                                    </p>
                                )}
                            </div>
                        )}
                        {(decisionForm.errors as Record<string, string>)
                            .proposal && (
                            <p className="text-sm text-destructive">
                                {
                                    (
                                        decisionForm.errors as Record<
                                            string,
                                            string
                                        >
                                    ).proposal
                                }
                            </p>
                        )}
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setDecisionOpen(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={decisionForm.processing}
                            >
                                {decisionForm.processing
                                    ? 'Menyimpan...'
                                    : 'Simpan keputusan'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

const decisionLabels: Record<string, string> = {
    SUBMITTED: 'Proposal diajukan',
    RESUBMITTED: 'Proposal diajukan ulang',
    REVIEW_STARTED: 'Review dimulai',
    APPROVE: 'Proposal disetujui',
    REQUEST_REVISION: 'Revisi diminta',
    REJECT: 'Proposal ditolak',
};
