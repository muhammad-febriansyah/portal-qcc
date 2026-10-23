import { Head, Link, usePage } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import {
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    Check,
    CircleAlert,
    Files,
    ListChecks,
} from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { CsvExportButton } from '@/components/csv-export-button';
import { ReviewControls } from '@/components/project-step/review-controls';
import { StepForm } from '@/components/project-step/step-form';
import { StepHistory } from '@/components/project-step/step-history';
import type { StepHistoryEntry } from '@/components/project-step/step-history';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import type { BadgeVariant } from '@/lib/badge-variants';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as proposalsIndex } from '@/routes/proposals';
import { index as biweeklyIndex } from '@/routes/projects/biweekly';
import { index as mentoringIndex } from '@/routes/projects/mentoring';
import { index as risksIndex } from '@/routes/projects/risks';
import { index as evidenceIndex } from '@/routes/projects/evidence';
import { exportMethod as exportStepsCsv, show } from '@/routes/projects/steps';

type StepItem = {
    number: number;
    name: string;
    status: string;
    status_label: string;
    progress_pct: number;
    target_date: string | null;
    is_current: boolean;
};

type StepDetail = {
    number: number;
    name: string;
    activity_description: string | null;
    plan_text: string | null;
    actual_text: string | null;
    target_date: string | null;
    status: string;
    status_label: string;
    progress_pct: number;
    submitted_at: string | null;
    approved_at: string | null;
    updated_at: string | null;
    submitter_note: string | null;
    lock_version: number;
    can_edit: boolean;
    can_submit: boolean;
    can_start_review: boolean;
    can_approve: boolean;
    can_request_revision: boolean;
    history: StepHistoryEntry[];
};

type Props = {
    project: { id: number; code: string; title: string };
    available: boolean;
    steps: StepItem[];
    selected: StepDetail | null;
    summary: {
        approved: number;
        total: number;
        overall_progress: number;
        in_review: number;
        needs_revision: number;
    };
    catalog: { number: number; name: string }[];
};

const statusVariants: Record<string, BadgeVariant> = {
    NOT_STARTED: 'secondary',
    IN_PROGRESS: 'info',
    SUBMITTED: 'violet',
    UNDER_REVIEW: 'violet',
    APPROVED: 'success',
    REVISION_REQUIRED: 'warning',
};

function formatDate(value: string | null): string {
    return value
        ? format(parseISO(value), 'd MMMM yyyy', { locale: indonesianLocale })
        : 'Belum ditentukan';
}

function ProgressBar({ value, label }: { value: number; label: string }) {
    return (
        <Progress value={value} aria-label={label} className="gap-0">
            <span className="sr-only">{value}%</span>
        </Progress>
    );
}

function StepMarker({ step, active }: { step: StepItem; active: boolean }) {
    return (
        <span
            className={cn(
                'flex size-8 shrink-0 items-center justify-center rounded-full border text-[13px] font-semibold',
                step.status === 'APPROVED' &&
                    'border-success/30 bg-success/10 text-success',
                step.status !== 'APPROVED' &&
                    active &&
                    'border-primary bg-primary text-primary-foreground',
                step.status !== 'APPROVED' &&
                    !active &&
                    'border-border bg-background text-muted-foreground',
            )}
            aria-hidden="true"
        >
            {step.status === 'APPROVED' ? (
                <Check className="size-4" />
            ) : (
                step.number
            )}
        </span>
    );
}

function Field({
    label,
    value,
    long = false,
}: {
    label: string;
    value: string | null;
    long?: boolean;
}) {
    return (
        <div className="grid gap-1.5">
            <dt className="text-[13px] font-medium">{label}</dt>
            <dd
                className={cn(
                    'rounded-lg border bg-muted/30 px-3 py-2.5 text-sm whitespace-pre-line',
                    long && 'min-h-20',
                    !value && 'text-muted-foreground',
                )}
            >
                {value || 'Belum diisi'}
            </dd>
        </div>
    );
}

export default function ProjectSteps({
    project,
    available,
    steps,
    selected,
    summary,
    catalog,
}: Props) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const statusLabels = Object.fromEntries(
        steps.map((step) => [step.status, step.status_label]),
    );
    const previous =
        selected && selected.number > 1 ? selected.number - 1 : null;
    const next =
        selected && selected.number < summary.total
            ? selected.number + 1
            : null;
    const nameOf = (number: number) =>
        catalog.find((item) => item.number === number)?.name ?? '';

    return (
        <>
            <Head title="Progress 8 Step QCC" />
            <div className="mx-auto w-full max-w-[1440px] px-4 py-6 md:px-6 md:py-8">
                <PageHeader
                    breadcrumbs={[
                        { title: 'Dashboard', href: dashboard() },
                        { title: 'Proposal QCC', href: proposalsIndex() },
                        {
                            title: '8 Step QCC',
                            href: show.url({ project: project.id }),
                        },
                    ]}
                    title="Progress 8 Step QCC"
                    description={`${project.code} · ${project.title}`}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <CsvExportButton
                                href={exportStepsCsv.url({
                                    project: project.id,
                                })}
                            />
                            <Button
                                variant="warning"
                                className="h-10 px-4 text-sm"
                                render={
                                    <Link
                                        href={risksIndex.url({
                                            project: project.id,
                                        })}
                                    />
                                }
                                nativeButton={false}
                            >
                                <AlertTriangle
                                    data-icon="inline-start"
                                    aria-hidden="true"
                                />
                                Risiko & Tindakan
                            </Button>
                            <Button
                                variant="info"
                                className="h-10 px-4 text-sm"
                                render={
                                    <Link
                                        href={biweeklyIndex.url({
                                            project: project.id,
                                        })}
                                    />
                                }
                                nativeButton={false}
                            >
                                Bi-weekly
                            </Button>
                            <Button
                                variant="violet"
                                className="h-10 px-4 text-sm"
                                render={
                                    <Link
                                        href={mentoringIndex.url({
                                            project: project.id,
                                        })}
                                    />
                                }
                                nativeButton={false}
                            >
                                Mentoring
                            </Button>
                            {selected !== null && (
                                <Button
                                    variant="teal"
                                    className="h-10 px-4 text-sm"
                                    render={
                                        <Link
                                            href={evidenceIndex.url(
                                                { project: project.id },
                                                {
                                                    query: {
                                                        step: selected.number,
                                                    },
                                                },
                                            )}
                                        />
                                    }
                                    nativeButton={false}
                                >
                                    <Files
                                        data-icon="inline-start"
                                        aria-hidden="true"
                                    />
                                    Evidence
                                </Button>
                            )}
                        </div>
                    }
                />

                {!available || selected === null ? (
                    <div className="flex min-h-64 flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border/80 px-4 text-center">
                        <ListChecks
                            className="size-6 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p className="text-sm font-medium">
                            8 Step QCC belum tersedia
                        </p>
                        <p className="max-w-md text-xs text-muted-foreground">
                            Delapan step dibuka otomatis setelah proposal
                            disetujui reviewer.
                        </p>
                        <ol className="mt-2 grid max-w-md gap-1 text-left text-[13px] text-muted-foreground">
                            {catalog.map((item) => (
                                <li key={item.number}>
                                    {item.number}. {item.name}
                                </li>
                            ))}
                        </ol>
                    </div>
                ) : (
                    <>
                        <Card className="mb-6 gap-0 py-0">
                            <CardContent className="grid gap-4 p-5">
                                <div className="flex flex-wrap items-end justify-between gap-3">
                                    <div className="grid gap-1">
                                        <p className="text-[13px] font-medium text-muted-foreground">
                                            Progress proyek
                                        </p>
                                        <p className="text-3xl leading-none font-semibold tracking-tight">
                                            {summary.overall_progress}%
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        <Badge variant="success">
                                            {summary.approved} dari{' '}
                                            {summary.total} step disetujui
                                        </Badge>
                                        {summary.in_review > 0 && (
                                            <Badge variant="violet">
                                                {summary.in_review} sedang
                                                direview
                                            </Badge>
                                        )}
                                        {summary.needs_revision > 0 && (
                                            <Badge variant="warning">
                                                <CircleAlert aria-hidden="true" />
                                                {summary.needs_revision} perlu
                                                revisi
                                            </Badge>
                                        )}
                                    </div>
                                </div>
                                <ProgressBar
                                    value={summary.overall_progress}
                                    label="Progress keseluruhan proyek"
                                />
                            </CardContent>
                        </Card>

                        <nav
                            aria-label="Langkah QCC"
                            className="-mx-4 mb-4 overflow-x-auto px-4 lg:hidden"
                        >
                            <ol className="flex gap-2 pb-2">
                                {steps.map((step) => (
                                    <li key={step.number}>
                                        <Link
                                            href={show.url({
                                                project: project.id,
                                                step: step.number,
                                            })}
                                            aria-current={
                                                step.number === selected.number
                                                    ? 'step'
                                                    : undefined
                                            }
                                            aria-label={`Step ${step.number}: ${step.name}, ${step.status_label}`}
                                            className={cn(
                                                'flex w-14 flex-col items-center gap-1.5 rounded-lg border p-2',
                                                step.number ===
                                                    selected.number &&
                                                    'border-primary bg-primary/5',
                                            )}
                                        >
                                            <StepMarker
                                                step={step}
                                                active={step.is_current}
                                            />
                                            <ProgressBar
                                                value={step.progress_pct}
                                                label={`Progress step ${step.number}`}
                                            />
                                        </Link>
                                    </li>
                                ))}
                            </ol>
                        </nav>

                        <div className="grid items-start gap-6 lg:grid-cols-[340px_minmax(0,1fr)]">
                            <Card className="hidden gap-0 py-0 lg:block">
                                <CardContent className="p-2">
                                    <nav aria-label="Langkah QCC">
                                        <ol className="grid gap-1">
                                            {steps.map((step) => (
                                                <li key={step.number}>
                                                    <Link
                                                        href={show.url({
                                                            project: project.id,
                                                            step: step.number,
                                                        })}
                                                        aria-current={
                                                            step.number ===
                                                            selected.number
                                                                ? 'step'
                                                                : undefined
                                                        }
                                                        className={cn(
                                                            'flex gap-3 rounded-lg p-3 transition-colors hover:bg-muted/60',
                                                            step.number ===
                                                                selected.number &&
                                                                'bg-primary/5 ring-1 ring-primary/30',
                                                        )}
                                                    >
                                                        <StepMarker
                                                            step={step}
                                                            active={
                                                                step.is_current
                                                            }
                                                        />
                                                        <div className="grid min-w-0 flex-1 gap-1.5">
                                                            <p className="text-[13px] leading-snug font-medium">
                                                                {step.name}
                                                            </p>
                                                            <div className="flex flex-wrap items-center gap-1.5">
                                                                <Badge
                                                                    variant={
                                                                        statusVariants[
                                                                            step
                                                                                .status
                                                                        ] ??
                                                                        'secondary'
                                                                    }
                                                                    className="font-normal"
                                                                >
                                                                    {
                                                                        step.status_label
                                                                    }
                                                                </Badge>
                                                                {step.is_current &&
                                                                    step.status !==
                                                                        'APPROVED' && (
                                                                        <span className="text-xs text-primary">
                                                                            Saat
                                                                            ini
                                                                        </span>
                                                                    )}
                                                            </div>
                                                            <ProgressBar
                                                                value={
                                                                    step.progress_pct
                                                                }
                                                                label={`Progress step ${step.number}`}
                                                            />
                                                        </div>
                                                    </Link>
                                                </li>
                                            ))}
                                        </ol>
                                    </nav>
                                </CardContent>
                            </Card>

                            <Card className="gap-0 py-0">
                                <CardContent className="grid gap-6 p-5 md:p-6">
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div className="grid gap-1">
                                            <p className="text-xs font-medium text-muted-foreground">
                                                Step {selected.number} dari{' '}
                                                {summary.total}
                                            </p>
                                            <h2 className="text-lg font-semibold">
                                                {selected.name}
                                            </h2>
                                        </div>
                                        <Badge
                                            variant={
                                                statusVariants[
                                                    selected.status
                                                ] ?? 'secondary'
                                            }
                                        >
                                            {selected.status_label}
                                        </Badge>
                                    </div>

                                    <div className="grid gap-1.5">
                                        <div className="flex items-center justify-between text-[13px]">
                                            <span className="font-medium">
                                                Progress step
                                            </span>
                                            <span className="text-muted-foreground">
                                                {selected.progress_pct}%
                                            </span>
                                        </div>
                                        <ProgressBar
                                            value={selected.progress_pct}
                                            label={`Progress step ${selected.number}`}
                                        />
                                    </div>

                                    {selected.can_edit ? (
                                        <StepForm
                                            key={`${selected.number}-${selected.lock_version}`}
                                            projectId={project.id}
                                            step={selected}
                                        />
                                    ) : (
                                        <dl className="grid gap-5">
                                            <Field
                                                label="Deskripsi aktivitas"
                                                value={
                                                    selected.activity_description
                                                }
                                                long
                                            />
                                            <Field
                                                label="Rencana"
                                                value={selected.plan_text}
                                                long
                                            />
                                            <Field
                                                label="Aktual / hasil"
                                                value={selected.actual_text}
                                                long
                                            />
                                            <div className="grid gap-5 sm:grid-cols-2">
                                                <Field
                                                    label="Target / tenggat"
                                                    value={
                                                        selected.target_date
                                                            ? formatDate(
                                                                  selected.target_date,
                                                              )
                                                            : null
                                                    }
                                                />
                                                <Field
                                                    label="Status"
                                                    value={
                                                        selected.status_label
                                                    }
                                                />
                                            </div>
                                            <Field
                                                label="Evidence"
                                                value={null}
                                            />
                                            <Field
                                                label="Risiko / isu terkait"
                                                value={null}
                                            />
                                            <Field
                                                label="Catatan pengaju"
                                                value={null}
                                            />
                                        </dl>
                                    )}

                                    <ReviewControls
                                        projectId={project.id}
                                        stepNumber={selected.number}
                                        canStartReview={
                                            selected.can_start_review
                                        }
                                        canApprove={selected.can_approve}
                                        canRequestRevision={
                                            selected.can_request_revision
                                        }
                                        errors={errors}
                                    />

                                    {selected.can_edit && (
                                        <dl className="grid gap-5">
                                            <Field
                                                label="Evidence"
                                                value={null}
                                            />
                                            <Field
                                                label="Risiko / isu terkait"
                                                value={null}
                                            />
                                        </dl>
                                    )}

                                    <p className="text-xs text-muted-foreground">
                                        {selected.approved_at
                                            ? `Disetujui ${formatDate(selected.approved_at)}.`
                                            : selected.submitted_at
                                              ? `Diajukan ${formatDate(selected.submitted_at)}.`
                                              : 'Belum diajukan untuk review.'}
                                    </p>

                                    <section
                                        className="grid gap-3 border-t pt-4"
                                        aria-label="Riwayat revisi"
                                    >
                                        <h3 className="text-sm font-semibold">
                                            Riwayat Revisi
                                        </h3>
                                        <StepHistory
                                            entries={selected.history}
                                            statusLabels={statusLabels}
                                        />
                                    </section>

                                    <div className="flex items-center justify-between gap-3 border-t pt-4">
                                        {previous ? (
                                            <Button
                                                variant="outline"
                                                render={
                                                    <Link
                                                        href={show.url({
                                                            project: project.id,
                                                            step: previous,
                                                        })}
                                                    />
                                                }
                                                nativeButton={false}
                                            >
                                                <ArrowLeft
                                                    data-icon="inline-start"
                                                    aria-hidden="true"
                                                />
                                                <span className="max-w-40 truncate">
                                                    {nameOf(previous)}
                                                </span>
                                            </Button>
                                        ) : (
                                            <span />
                                        )}
                                        {next ? (
                                            <Button
                                                variant="outline"
                                                render={
                                                    <Link
                                                        href={show.url({
                                                            project: project.id,
                                                            step: next,
                                                        })}
                                                    />
                                                }
                                                nativeButton={false}
                                            >
                                                <span className="max-w-40 truncate">
                                                    {nameOf(next)}
                                                </span>
                                                <ArrowRight
                                                    data-icon="inline-end"
                                                    aria-hidden="true"
                                                />
                                            </Button>
                                        ) : (
                                            <span />
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}
