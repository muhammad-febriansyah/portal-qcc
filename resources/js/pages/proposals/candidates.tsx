import { Head, Link, router, usePage } from '@inertiajs/react';
import { Info, Sparkles, TriangleAlert, Users } from 'lucide-react';
import { useEffect, useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { Card, CardContent } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { candidates as candidatesRoute } from '@/routes/proposals';
import { start } from '@/routes/proposals/matching';

type Requirement = {
    competency_id: number;
    code: string;
    name: string;
    minimum_level: number | null;
};

type Candidate = {
    user_id: number;
    name: string;
    position: string | null;
    organization: string;
    availability_status: string | null;
    matched_count: number;
    requirement_count: number;
    competencies: {
        competency_id: number;
        self_level: number;
        verified_level: number | null;
    }[];
};

type Matching = {
    id: number;
    status: 'QUEUED' | 'PROCESSING' | 'READY' | 'FAILED';
    status_label: string;
    error_code: string | null;
    members: {
        user_id: number;
        name: string | null;
        organization: string | null;
        proposed_role: 'LEADER' | 'MEMBER';
        matched_competency_ids: number[];
        match_reason: string;
        contribution: string;
        gaps: string[];
        uncertainties: string[];
    }[];
    team_gaps: string[];
    cautions: string[];
    rule_problems: string[];
} | null;

type Ai = { ready: boolean; message: string; can_manage: boolean };

const failureMessages: Record<string, string> = {
    ai_not_configured: 'Integrasi AI belum diatur atau sedang dimatikan.',
    ai_timeout:
        'Layanan AI tidak merespons tepat waktu. Coba lagi sebentar lagi.',
    ai_busy: 'Layanan AI sedang sibuk. Coba lagi sebentar lagi.',
    ai_rejected: 'Permintaan ditolak oleh layanan AI.',
    invalid_output:
        'Jawaban AI tidak lolos pemeriksaan sistem, jadi tidak dipakai. Coba jalankan lagi.',
};

type Props = {
    proposal: { id: number; code: string; title: string };
    requirements: Requirement[];
    candidates: Candidate[];
    limit: number;
    ai: Ai;
    matching: Matching;
};

function initials(name: string): string {
    return name
        .split(' ')
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
}

export default function ProposalCandidates({
    proposal,
    requirements,
    candidates,
    limit,
    ai,
    matching,
}: Props) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const [starting, setStarting] = useState(false);
    const isRunning =
        matching?.status === 'QUEUED' || matching?.status === 'PROCESSING';

    useEffect(() => {
        if (!isRunning) {
            return;
        }

        const timer = window.setInterval(() => {
            router.reload({ only: ['matching'] });
        }, 3000);

        return () => window.clearInterval(timer);
    }, [isRunning]);

    function runMatching(): void {
        router.post(
            start.url(proposal.id),
            {},
            {
                preserveScroll: true,
                onStart: () => setStarting(true),
                onFinish: () => setStarting(false),
            },
        );
    }

    const byId = Object.fromEntries(
        requirements.map((requirement) => [
            requirement.competency_id,
            requirement,
        ]),
    );

    return (
        <>
            <Head title={`Kandidat ${proposal.code}`} />
            <div className="mx-auto w-full max-w-[1440px] px-4 py-6 md:px-6 md:py-8">
                <PageHeader
                    breadcrumbs={[
                        { title: 'Dashboard', href: dashboard() },
                        {
                            title: 'Kandidat Tim',
                            href: candidatesRoute(proposal.id),
                        },
                    ]}
                    title="Kandidat Tim"
                    description={`${proposal.code} · ${proposal.title}`}
                />

                <Card className="mb-6 gap-0 py-0">
                    <CardContent className="grid gap-3 p-5">
                        <p className="text-sm font-medium">
                            Kompetensi yang dibutuhkan
                        </p>
                        {requirements.length === 0 ? (
                            <p className="text-[13px] text-muted-foreground">
                                Proyek ini belum punya kebutuhan kompetensi.
                                Tambahkan dulu agar kandidat bisa dicari.
                            </p>
                        ) : (
                            <div className="flex flex-wrap gap-2">
                                {requirements.map((requirement) => (
                                    <Badge
                                        key={requirement.competency_id}
                                        variant="secondary"
                                        className="font-normal"
                                    >
                                        {requirement.code} · {requirement.name}
                                        {requirement.minimum_level
                                            ? ` (min. level ${requirement.minimum_level})`
                                            : ''}
                                    </Badge>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {!ai.ready && (
                    <Alert variant="destructive" className="mb-6">
                        <TriangleAlert aria-hidden="true" />
                        <AlertTitle>AI belum bisa dipakai</AlertTitle>
                        <AlertDescription>
                            {ai.message}{' '}
                            {ai.can_manage && (
                                <Link
                                    href="/admin/settings"
                                    className="font-medium underline underline-offset-2"
                                >
                                    Buka Pengaturan Sistem
                                </Link>
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                {(errors.ai || errors.matching) && (
                    <Alert variant="destructive" className="mb-6">
                        <TriangleAlert aria-hidden="true" />
                        <AlertDescription>
                            {errors.ai ?? errors.matching}
                        </AlertDescription>
                    </Alert>
                )}

                {requirements.length > 0 && candidates.length > 0 && (
                    <div className="mb-6 flex flex-wrap items-center gap-3">
                        <Button
                            type="button"
                            className="h-10 px-4 text-sm"
                            disabled={!ai.ready || isRunning || starting}
                            onClick={runMatching}
                        >
                            {isRunning || starting ? (
                                <Spinner data-icon="inline-start" />
                            ) : (
                                <Sparkles
                                    data-icon="inline-start"
                                    aria-hidden="true"
                                />
                            )}
                            {matching
                                ? 'Jalankan Ulang AI Matching'
                                : 'Jalankan AI Matching'}
                        </Button>
                        {isRunning && (
                            <span
                                className="text-[13px] text-muted-foreground"
                                role="status"
                            >
                                {matching?.status_label}. Halaman diperbarui
                                otomatis.
                            </span>
                        )}
                    </div>
                )}

                {matching?.status === 'FAILED' && (
                    <Alert variant="destructive" className="mb-6">
                        <TriangleAlert aria-hidden="true" />
                        <AlertTitle>Rekomendasi gagal dibuat</AlertTitle>
                        <AlertDescription>
                            {failureMessages[matching.error_code ?? ''] ??
                                'Terjadi kendala saat memproses rekomendasi. Coba lagi.'}
                        </AlertDescription>
                    </Alert>
                )}

                {matching?.status === 'READY' && (
                    <section
                        className="mb-8 grid gap-4"
                        aria-label="Rekomendasi tim AI"
                    >
                        <div className="flex items-center gap-2">
                            <Sparkles
                                className="size-4 text-primary"
                                aria-hidden="true"
                            />
                            <h2 className="text-base font-semibold">
                                Rekomendasi Tim AI
                            </h2>
                        </div>

                        {matching.rule_problems.length > 0 && (
                            <Alert variant="destructive">
                                <TriangleAlert aria-hidden="true" />
                                <AlertTitle>
                                    Susunan ini belum bisa dikonfirmasi
                                </AlertTitle>
                                <AlertDescription>
                                    <ul className="list-disc pl-4">
                                        {matching.rule_problems.map(
                                            (problem) => (
                                                <li key={problem}>{problem}</li>
                                            ),
                                        )}
                                    </ul>
                                </AlertDescription>
                            </Alert>
                        )}

                        <ul className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {matching.members.map((member) => (
                                <li key={member.user_id}>
                                    <Card className="h-full gap-0 py-0">
                                        <CardContent className="grid gap-3 p-5">
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="grid min-w-0">
                                                    <p className="truncate text-sm font-semibold">
                                                        {member.name}
                                                    </p>
                                                    <p className="truncate text-xs text-muted-foreground">
                                                        {member.organization}
                                                    </p>
                                                </div>
                                                <Badge
                                                    variant={
                                                        member.proposed_role ===
                                                        'LEADER'
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {member.proposed_role ===
                                                    'LEADER'
                                                        ? 'Leader'
                                                        : 'Anggota'}
                                                </Badge>
                                            </div>
                                            <p className="text-[13px]">
                                                {member.match_reason}
                                            </p>
                                            <p className="text-[13px] text-muted-foreground">
                                                Kontribusi:{' '}
                                                {member.contribution}
                                            </p>
                                            {member.matched_competency_ids
                                                .length > 0 && (
                                                <div className="flex flex-wrap gap-1.5">
                                                    {member.matched_competency_ids.map(
                                                        (id) => (
                                                            <Badge
                                                                key={id}
                                                                variant="outline"
                                                                className="font-normal"
                                                            >
                                                                {byId[id]
                                                                    ?.name ??
                                                                    id}
                                                            </Badge>
                                                        ),
                                                    )}
                                                </div>
                                            )}
                                            {member.gaps.length > 0 && (
                                                <p className="text-xs text-muted-foreground">
                                                    Kekurangan:{' '}
                                                    {member.gaps.join('; ')}
                                                </p>
                                            )}
                                            {member.uncertainties.length >
                                                0 && (
                                                <p className="flex gap-1.5 text-xs text-muted-foreground">
                                                    <Info
                                                        className="mt-0.5 size-3.5 shrink-0"
                                                        aria-hidden="true"
                                                    />
                                                    {member.uncertainties.join(
                                                        '; ',
                                                    )}
                                                </p>
                                            )}
                                        </CardContent>
                                    </Card>
                                </li>
                            ))}
                        </ul>

                        {(matching.team_gaps.length > 0 ||
                            matching.cautions.length > 0) && (
                            <Card className="gap-0 py-0">
                                <CardContent className="grid gap-2 p-5 text-[13px]">
                                    {matching.team_gaps.length > 0 && (
                                        <p>
                                            <span className="font-medium">
                                                Kekurangan tim:
                                            </span>{' '}
                                            {matching.team_gaps.join('; ')}
                                        </p>
                                    )}
                                    {matching.cautions.length > 0 && (
                                        <p>
                                            <span className="font-medium">
                                                Perlu diperhatikan:
                                            </span>{' '}
                                            {matching.cautions.join('; ')}
                                        </p>
                                    )}
                                </CardContent>
                            </Card>
                        )}
                        <p className="text-xs text-muted-foreground">
                            Ini saran, bukan tim. Tim baru terbentuk setelah ada
                            yang menyusun dan mengonfirmasinya.
                        </p>
                    </section>
                )}

                {requirements.length > 0 && candidates.length === 0 && (
                    <div className="flex min-h-48 flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-border/80 text-center">
                        <Users
                            className="size-6 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p className="text-sm font-medium">
                            Belum ada kandidat yang cocok
                        </p>
                        <p className="max-w-sm text-xs text-muted-foreground">
                            Tidak ada anggota aktif dan tersedia yang memenuhi
                            kompetensi dan level minimum proyek ini.
                        </p>
                    </div>
                )}

                {candidates.length > 0 && (
                    <>
                        <p className="mb-3 text-[13px] text-muted-foreground">
                            {candidates.length} kandidat teratas (maksimal{' '}
                            {limit}), diurutkan dari yang paling sesuai.
                        </p>
                        <ul className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {candidates.map((candidate) => (
                                <li key={candidate.user_id}>
                                    <Card className="h-full gap-0 py-0">
                                        <CardContent className="grid gap-4 p-5">
                                            <div className="flex items-start gap-3">
                                                <span
                                                    className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary"
                                                    aria-hidden="true"
                                                >
                                                    {initials(candidate.name)}
                                                </span>
                                                <div className="grid min-w-0">
                                                    <p className="truncate text-sm font-semibold">
                                                        {candidate.name}
                                                    </p>
                                                    <p className="truncate text-xs text-muted-foreground">
                                                        {candidate.position ??
                                                            'Jabatan belum diisi'}{' '}
                                                        ·{' '}
                                                        {candidate.organization}
                                                    </p>
                                                </div>
                                                <Badge
                                                    variant={
                                                        candidate.matched_count ===
                                                        candidate.requirement_count
                                                            ? 'success'
                                                            : 'secondary'
                                                    }
                                                    className="ml-auto shrink-0"
                                                >
                                                    {candidate.matched_count}/
                                                    {
                                                        candidate.requirement_count
                                                    }{' '}
                                                    cocok
                                                </Badge>
                                            </div>
                                            <ul className="grid gap-1.5">
                                                {candidate.competencies.map(
                                                    (competency) => {
                                                        const requirement =
                                                            byId[
                                                                competency
                                                                    .competency_id
                                                            ];

                                                        return (
                                                            <li
                                                                key={
                                                                    competency.competency_id
                                                                }
                                                                className="flex items-center justify-between gap-3 text-[13px]"
                                                            >
                                                                <span className="min-w-0 truncate">
                                                                    {
                                                                        requirement?.name
                                                                    }
                                                                </span>
                                                                <span className="shrink-0 text-xs text-muted-foreground">
                                                                    Mandiri{' '}
                                                                    {
                                                                        competency.self_level
                                                                    }
                                                                    {competency.verified_level !==
                                                                        null &&
                                                                        ` · Verifikasi ${competency.verified_level}`}
                                                                </span>
                                                            </li>
                                                        );
                                                    },
                                                )}
                                            </ul>
                                        </CardContent>
                                    </Card>
                                </li>
                            ))}
                        </ul>
                    </>
                )}
            </div>
        </>
    );
}
