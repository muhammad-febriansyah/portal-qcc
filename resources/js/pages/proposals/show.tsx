import { FormattedText } from '@/components/formatted-text';
import { TimelineTable } from '@/components/step-timeline-table';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import {
    ArrowLeft,
    Download,
    Eye,
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
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
    preview_url: string | null;
};

type Proposal = {
    id: number;
    code: string;
    title: string;
    batch: string | null;
    proposer: string | null;
    pic: string | null;
    organization: string | null;
    status: string;
    status_label: string;
    theme: string | null;
    theme_category: string | null;
    background: string | null;
    current_situation: string | null;
    improvement_plan: string | null;
    target: string | null;
    scope: string | null;
    supplementary_information: string | null;
    cost_projection: string | null;
    learning_goals: string[];
    step_timeline: {
        step_number: number;
        name: string;
        start_date: string | null;
        target_date: string | null;
        deliverable: string | null;
    }[];
    batch_starts_on: string | null;
    legacy_sections: { label: string; value: string }[];
    updated_at: string | null;
    attachments: ProposalAttachment[];
    can_edit_draft: boolean;
    can_find_candidates: boolean;
    has_team: boolean;
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
        <div className="min-w-0 rounded-lg border border-border/70 bg-muted/20 p-4">
            <dt className="text-xs font-semibold tracking-wide text-muted-foreground">
                {label}
            </dt>
            <dd className="mt-2 text-sm leading-6 text-foreground">
                <FormattedText value={value} />
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
    const [previewAttachment, setPreviewAttachment] =
        useState<ProposalAttachment | null>(null);
    const decisionForm = useForm<{ decision: string; reason: string }>({
        decision: 'APPROVE',
        reason: '',
    });
    const { errors } = usePage().props as { errors: Record<string, string> };
    const latestRevision = proposal.review_history
        .filter((item) => item.decision === 'REQUEST_REVISION')
        .at(-1);
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
                                    {proposal.has_team
                                        ? 'Kelola tim'
                                        : 'Cari kandidat tim'}
                                </Button>
                            )}
                            {proposal.can_submit && (
                                <Button
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
                    {proposal.team_status_message &&
                        proposal.status === 'APPROVED' && (
                            <Alert>
                                <UsersRound aria-hidden="true" />
                                <AlertTitle>
                                    Tim proposal sudah dikonfirmasi
                                </AlertTitle>
                                <AlertDescription>
                                    {proposal.team_status_message}
                                </AlertDescription>
                            </Alert>
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
                    {proposal.status === 'REVISION_REQUIRED' && (
                        <Alert className="border-warning/40 bg-warning/10">
                            <AlertTitle>Proposal perlu revisi</AlertTitle>
                            <AlertDescription>
                                {latestRevision?.reason
                                    ? `"${latestRevision.reason}"`
                                    : 'Reviewer belum mencatat alasan revisi.'}
                                {latestRevision?.decided_at && (
                                    <span className="mt-1 block text-xs">
                                        {latestRevision.actor ?? 'Reviewer'} ·{' '}
                                        {format(
                                            parseISO(latestRevision.decided_at),
                                            'd MMMM yyyy, HH:mm',
                                            { locale: indonesianLocale },
                                        )}
                                    </span>
                                )}
                            </AlertDescription>
                        </Alert>
                    )}
                    <Tabs defaultValue="summary" className="gap-5">
                        <TabsList
                            aria-label="Bagian detail proposal"
                            className="w-full max-w-full flex-wrap justify-start sm:w-fit"
                        >
                            <TabsTrigger value="summary">
                                <FileText
                                    aria-hidden="true"
                                    data-icon="inline-start"
                                />
                                Ringkasan & situasi
                            </TabsTrigger>
                            <TabsTrigger value="plan">
                                <ClipboardCheck
                                    aria-hidden="true"
                                    data-icon="inline-start"
                                />
                                Rencana & timeline
                            </TabsTrigger>
                            <TabsTrigger value="documents">
                                <Paperclip
                                    aria-hidden="true"
                                    data-icon="inline-start"
                                />
                                Lampiran & riwayat
                                <Badge variant="secondary" className="ml-1">
                                    {proposal.attachments.length +
                                        proposal.review_history.length}
                                </Badge>
                            </TabsTrigger>
                        </TabsList>

                        <TabsContent value="summary" className="space-y-5">
                            <Card>
                                <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3">
                                    <div className="space-y-1">
                                        <CardTitle className="text-base">
                                            Ringkasan proposal
                                        </CardTitle>
                                        <CardDescription>
                                            Informasi pengajuan · Terakhir
                                            diperbarui{' '}
                                            {proposal.updated_at
                                                ? format(
                                                      parseISO(
                                                          proposal.updated_at,
                                                      ),
                                                      'd MMMM yyyy, HH:mm',
                                                      {
                                                          locale: indonesianLocale,
                                                      },
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
                                    <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                        <DetailField
                                            label="Pengaju"
                                            value={proposal.proposer}
                                        />
                                        <DetailField
                                            label="PIC proposal"
                                            value={proposal.pic}
                                        />
                                        <DetailField
                                            label="LOB / Divisi"
                                            value={proposal.organization}
                                        />
                                        <DetailField
                                            label="Kategori Tema"
                                            value={proposal.theme_category}
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
                                        Latar belakang & situasi
                                    </CardTitle>
                                    <CardDescription>
                                        Konteks dan data awal yang menjadi dasar
                                        perbaikan.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <dl className="grid gap-3 sm:grid-cols-2">
                                        <DetailField
                                            label="Latar belakang"
                                            value={proposal.background}
                                        />
                                        <DetailField
                                            label="Situasi saat ini & baseline"
                                            value={proposal.current_situation}
                                        />
                                    </dl>
                                </CardContent>
                            </Card>
                        </TabsContent>
                        <TabsContent value="plan" className="space-y-5">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        Rencana & target
                                    </CardTitle>
                                    <CardDescription>
                                        Arah solusi, sasaran, ruang lingkup, dan
                                        kebutuhan biaya proyek.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <dl className="grid gap-3 sm:grid-cols-2">
                                        <DetailField
                                            label="Rencana perbaikan / arah solusi"
                                            value={proposal.improvement_plan}
                                        />
                                        <DetailField
                                            label="Target / tujuan"
                                            value={proposal.target}
                                        />
                                        <DetailField
                                            label="Ruang lingkup proyek"
                                            value={proposal.scope}
                                        />
                                        <DetailField
                                            label="Informasi dan data pelengkap"
                                            value={
                                                proposal.supplementary_information
                                            }
                                        />
                                        <DetailField
                                            label="Proyeksi kebutuhan biaya proyek"
                                            value={proposal.cost_projection}
                                        />
                                        <div className="min-w-0 rounded-lg border border-border/70 bg-muted/20 p-4">
                                            <dt className="text-xs font-semibold tracking-wide text-muted-foreground">
                                                Tiga hal yang akan dipelajari
                                            </dt>
                                            <dd className="mt-2">
                                                {proposal.learning_goals
                                                    .length > 0 ? (
                                                    <ol className="list-decimal space-y-1 pl-5 text-sm leading-6">
                                                        {proposal.learning_goals.map(
                                                            (
                                                                goal,
                                                                position,
                                                            ) => (
                                                                <li
                                                                    key={
                                                                        position
                                                                    }
                                                                >
                                                                    {goal}
                                                                </li>
                                                            ),
                                                        )}
                                                    </ol>
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
                                    <CardTitle className="text-base">
                                        Timeline
                                    </CardTitle>
                                    <CardDescription>
                                        Rencana waktu, target selesai, dan
                                        deliverable setiap step QCC.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <TimelineTable
                                        rows={proposal.step_timeline.map(
                                            (row) => ({
                                                number: row.step_number,
                                                name: row.name,
                                                startDate: row.start_date,
                                                date: row.target_date,
                                                deliverable: row.deliverable,
                                            }),
                                        )}
                                        startDate={proposal.batch_starts_on}
                                    />
                                </CardContent>
                            </Card>

                            {proposal.legacy_sections.length > 0 && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">
                                            Bagian proposal versi sebelumnya
                                        </CardTitle>
                                        <CardDescription>
                                            Bagian ini sudah tidak ada di
                                            formulir, tetapi masih diisi pada
                                            proposal ini.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <dl className="grid gap-3 sm:grid-cols-2">
                                            {proposal.legacy_sections.map(
                                                (section) => (
                                                    <DetailField
                                                        key={section.label}
                                                        label={section.label}
                                                        value={section.value}
                                                    />
                                                ),
                                            )}
                                        </dl>
                                    </CardContent>
                                </Card>
                            )}
                        </TabsContent>
                        <TabsContent value="documents" className="space-y-5">
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
                                        <ul className="grid gap-3">
                                            {proposal.attachments.map(
                                                (attachment) => (
                                                    <li
                                                        key={attachment.id}
                                                        className="flex flex-col gap-3 rounded-lg border border-border/70 bg-muted/20 p-4 sm:flex-row sm:items-center"
                                                    >
                                                        <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                            <FileText
                                                                className="size-4"
                                                                aria-hidden="true"
                                                            />
                                                        </span>
                                                        <div className="min-w-0 flex-1">
                                                            <p className="text-sm font-medium break-words">
                                                                {
                                                                    attachment.name
                                                                }
                                                            </p>
                                                            <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                                                {
                                                                    attachment.mime_type
                                                                }{' '}
                                                                ·{' '}
                                                                {fileSize(
                                                                    attachment.size_bytes,
                                                                )}
                                                            </p>
                                                        </div>
                                                        {attachment.scan_status !==
                                                            'PASSED' && (
                                                            <Badge variant="warning">
                                                                {attachment.scan_status ===
                                                                'PENDING_SCAN'
                                                                    ? 'Menunggu pemeriksaan format'
                                                                    : 'Format belum lolos pemeriksaan'}
                                                            </Badge>
                                                        )}
                                                        {attachment.preview_url &&
                                                            (attachment.mime_type ===
                                                                'application/pdf' ||
                                                                attachment.mime_type.startsWith(
                                                                    'image/',
                                                                )) && (
                                                                <Button
                                                                    type="button"
                                                                    variant="outline"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        setPreviewAttachment(
                                                                            attachment,
                                                                        )
                                                                    }
                                                                >
                                                                    <Eye data-icon="inline-start" />
                                                                    Pratinjau
                                                                </Button>
                                                            )}
                                                        {attachment.preview_url &&
                                                            attachment.mime_type !==
                                                                'application/pdf' &&
                                                            !attachment.mime_type.startsWith(
                                                                'image/',
                                                            ) && (
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
                                                                    nativeButton={
                                                                        false
                                                                    }
                                                                >
                                                                    <Download data-icon="inline-start" />
                                                                    Buka file
                                                                </Button>
                                                            )}
                                                    </li>
                                                ),
                                            )}
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
                                            Catatan keputusan tersimpan sebagai
                                            riwayat proposal.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <ol className="space-y-4">
                                            {proposal.review_history.map(
                                                (item) => (
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
                                                            {item.actor ??
                                                                'Pengguna'}{' '}
                                                            ·{' '}
                                                            {format(
                                                                parseISO(
                                                                    item.decided_at,
                                                                ),
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
                                                ),
                                            )}
                                        </ol>
                                    </CardContent>
                                </Card>
                            )}
                        </TabsContent>
                    </Tabs>

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
            <Dialog
                open={previewAttachment !== null}
                onOpenChange={(open) => {
                    if (!open) setPreviewAttachment(null);
                }}
            >
                <DialogContent className="grid h-[min(88vh,900px)] w-[calc(100vw-2rem)] max-w-6xl grid-rows-[auto_minmax(0,1fr)] gap-4 overflow-hidden p-4 sm:max-w-6xl sm:p-6">
                    <DialogHeader className="pr-8">
                        <DialogTitle className="break-all">
                            {previewAttachment?.name ?? 'Pratinjau lampiran'}
                        </DialogTitle>
                        <DialogDescription>
                            {previewAttachment
                                ? `${previewAttachment.mime_type} · ${fileSize(previewAttachment.size_bytes)}`
                                : 'Pratinjau file proposal.'}
                        </DialogDescription>
                    </DialogHeader>
                    {previewAttachment?.preview_url &&
                        (previewAttachment.mime_type === 'application/pdf' ? (
                            <iframe
                                title={`Pratinjau ${previewAttachment.name}`}
                                src={previewAttachment.preview_url}
                                className="h-full min-h-0 w-full rounded-lg border bg-muted/20"
                            />
                        ) : (
                            <div className="flex min-h-0 items-center justify-center overflow-auto rounded-lg border bg-muted/20 p-3">
                                <img
                                    src={previewAttachment.preview_url}
                                    alt={previewAttachment.name}
                                    className="max-h-full max-w-full object-contain"
                                />
                            </div>
                        ))}
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
