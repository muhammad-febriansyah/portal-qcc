import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Info,
    ListChecks,
    Plus,
    Sparkles,
    TriangleAlert,
    UserCheck,
    Users,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { TeamPanel } from '@/components/team-panel';
import type { TeamPanelPerson } from '@/components/team-panel';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import {
    checkTeam,
    MAX_TEAM_MEMBERS,
    personGaps,
    sameTeam,
    teamGaps,
} from '@/lib/team-rules';
import type {
    TeamCandidate,
    TeamPick,
    TeamRequirement,
    TeamRole,
} from '@/lib/team-rules';
import { dashboard } from '@/routes';
import { index as settingsIndex } from '@/routes/admin/settings';
import { candidates as candidatesRoute } from '@/routes/proposals';
import { start } from '@/routes/proposals/matching';
import { confirm } from '@/routes/proposals/team';

type Candidate = TeamCandidate & {
    name: string;
    position: string | null;
    organization: string;
    availability_status: string | null;
    matched_count: number;
    requirement_count: number;
};

type Suggestion = {
    user_id: number;
    name: string | null;
    organization: string | null;
    organization_id: number | null;
    competencies: TeamCandidate['competencies'];
    proposed_role: TeamRole;
    matched_competency_ids: number[];
    match_reason: string;
    contribution: string;
    gaps: string[];
    uncertainties: string[];
};

type Matching = {
    id: number;
    status: 'QUEUED' | 'PROCESSING' | 'READY' | 'FAILED';
    status_label: string;
    source: 'AI' | 'MANUAL';
    error_code: string | null;
    confirmed_at: string | null;
    members: Suggestion[];
    team_gaps: string[];
    cautions: string[];
    mitigations: Mitigation[];
    rule_problems: string[];
} | null;

type Mitigation = {
    gap: string;
    recommendation: string;
    type: 'COACHING' | 'TRAINING' | 'MENTOR';
};

const mitigationTypeLabels: Record<Mitigation['type'], string> = {
    COACHING: 'Coaching',
    TRAINING: 'Pelatihan',
    MENTOR: 'Pendampingan mentor',
};

type CurrentTeamMember = {
    user_id: number;
    name: string;
    organization: string | null;
    organization_id: number | null;
    role: string;
    competencies: TeamCandidate['competencies'];
};

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
    requirements: TeamRequirement[];
    candidates: Candidate[];
    limit: number;
    ai: Ai;
    matching: Matching;
    team: CurrentTeamMember[];
    can_edit_team: boolean;
};

function initials(name: string): string {
    return name
        .split(' ')
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
}

type CardProps = {
    candidate: Candidate;
    requirements: TeamRequirement[];
    suggestion?: Suggestion;
    picked: boolean;
    canPick: boolean;
    pickDisabled: boolean;
    onTogglePick: () => void;
};

function CandidateCard({
    candidate,
    requirements,
    suggestion,
    picked,
    canPick,
    pickDisabled,
    onTogglePick,
}: CardProps) {
    const byId = Object.fromEntries(
        requirements.map((requirement) => [
            requirement.competency_id,
            requirement,
        ]),
    );
    const missing = personGaps(candidate, requirements);

    return (
        <Card
            className={`h-full gap-0 py-0 ${picked ? 'ring-2 ring-primary/40' : ''}`}
        >
            <CardContent className="grid gap-3 p-5">
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
                            {candidate.position ?? 'Jabatan belum diisi'} ·{' '}
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
                        {candidate.matched_count}/{candidate.requirement_count}{' '}
                        cocok
                    </Badge>
                </div>

                {suggestion && (
                    <>
                        <p className="text-[13px]">{suggestion.match_reason}</p>
                        <p className="text-[13px] text-muted-foreground">
                            Kontribusi: {suggestion.contribution}
                        </p>
                    </>
                )}

                <ul className="grid gap-1.5">
                    {candidate.competencies.map((competency) => (
                        <li
                            key={competency.competency_id}
                            className="flex items-center justify-between gap-3 text-[13px]"
                        >
                            <span className="min-w-0 truncate">
                                {byId[competency.competency_id]?.name}
                            </span>
                            <span className="shrink-0 text-xs text-muted-foreground">
                                Mandiri {competency.self_level}
                                {competency.verified_level !== null &&
                                    ` · Verifikasi ${competency.verified_level}`}
                            </span>
                        </li>
                    ))}
                </ul>

                {(missing.length > 0 || (suggestion?.gaps.length ?? 0) > 0) && (
                    <div className="grid gap-1 text-xs text-muted-foreground">
                        {missing.length > 0 && (
                            <p>
                                <span className="font-medium text-foreground">
                                    Belum memenuhi:
                                </span>{' '}
                                {missing.map((gap) => gap.name).join(', ')}
                            </p>
                        )}
                        {suggestion && suggestion.gaps.length > 0 && (
                            <p>
                                <span className="font-medium text-foreground">
                                    Catatan AI:
                                </span>{' '}
                                {suggestion.gaps.join('; ')}
                            </p>
                        )}
                    </div>
                )}

                {suggestion && suggestion.uncertainties.length > 0 && (
                    <p className="flex gap-1.5 text-xs text-muted-foreground">
                        <Info
                            className="mt-0.5 size-3.5 shrink-0"
                            aria-hidden="true"
                        />
                        {suggestion.uncertainties.join('; ')}
                    </p>
                )}

                {canPick && (
                    <Button
                        type="button"
                        variant={picked ? 'secondary' : 'outline'}
                        size="sm"
                        disabled={pickDisabled}
                        aria-pressed={picked}
                        onClick={onTogglePick}
                    >
                        {picked ? (
                            <UserCheck
                                data-icon="inline-start"
                                aria-hidden="true"
                            />
                        ) : (
                            <Plus data-icon="inline-start" aria-hidden="true" />
                        )}
                        {picked ? 'Ada di susunan' : 'Tambah ke susunan'}
                    </Button>
                )}
            </CardContent>
        </Card>
    );
}

export default function ProposalCandidates({
    proposal,
    requirements,
    candidates,
    limit,
    ai,
    matching,
    team,
    can_edit_team,
}: Props) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const [starting, setStarting] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const [manualMode, setManualMode] = useState(false);
    const isRunning =
        matching?.status === 'QUEUED' || matching?.status === 'PROCESSING';
    const isReady = matching?.status === 'READY';
    const isConfirmed = isReady && matching.confirmed_at !== null;
    const canEdit = can_edit_team && (manualMode || (isReady && !isConfirmed));

    const aiPicks = useMemo<TeamPick[]>(
        () =>
            isReady
                ? matching.members.map((member) => ({
                      user_id: member.user_id,
                      role: member.proposed_role,
                  }))
                : [],
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [matching?.id, matching?.status],
    );
    const [picks, setPicks] = useState<TeamPick[]>(aiPicks);

    useEffect(() => {
        setPicks(aiPicks);
    }, [aiPicks]);

    useEffect(() => {
        if (!isRunning) {
            return;
        }

        const timer = window.setInterval(() => {
            router.reload({ only: ['matching'] });
        }, 3000);

        return () => window.clearInterval(timer);
    }, [isRunning]);

    const candidateMap = useMemo(() => {
        const map = new Map<number, TeamCandidate>(
            candidates.map((candidate) => [candidate.user_id, candidate]),
        );

        for (const member of matching?.members ?? []) {
            if (!map.has(member.user_id) && member.organization_id !== null) {
                map.set(member.user_id, {
                    user_id: member.user_id,
                    organization_id: member.organization_id,
                    competencies: member.competencies,
                });
            }
        }

        for (const member of team) {
            if (!map.has(member.user_id) && member.organization_id !== null) {
                map.set(member.user_id, {
                    user_id: member.user_id,
                    organization_id: member.organization_id,
                    competencies: member.competencies,
                });
            }
        }

        return map;
    }, [candidates, matching, team]);

    const people = useMemo(() => {
        const map = new Map<number, TeamPanelPerson>();

        for (const member of matching?.members ?? []) {
            map.set(member.user_id, {
                user_id: member.user_id,
                name: member.name ?? `#${member.user_id}`,
                organization: member.organization ?? '',
            });
        }

        for (const candidate of candidates) {
            map.set(candidate.user_id, {
                user_id: candidate.user_id,
                name: candidate.name,
                organization: candidate.organization,
            });
        }

        for (const member of team) {
            if (!map.has(member.user_id)) {
                map.set(member.user_id, {
                    user_id: member.user_id,
                    name: member.name,
                    organization: member.organization ?? '',
                });
            }
        }

        return map;
    }, [candidates, matching, team]);

    const problems = checkTeam(picks, candidateMap);
    const gaps = teamGaps(picks, candidateMap, requirements);
    const changedFromAi =
        matching?.source === 'MANUAL' ? false : !sameTeam(picks, aiPicks);
    const serverErrors = Object.entries(errors)
        .filter(([key]) => key === 'team' || key.startsWith('members'))
        .map(([, message]) => message);

    function togglePick(userId: number): void {
        setPicks((current) => {
            if (current.some((pick) => pick.user_id === userId)) {
                return current.filter((pick) => pick.user_id !== userId);
            }

            const hasLeader = current.some((pick) => pick.role === 'LEADER');

            return [
                ...current,
                { user_id: userId, role: hasLeader ? 'MEMBER' : 'LEADER' },
            ];
        });
    }

    function changeRole(userId: number, role: TeamRole): void {
        setPicks((current) =>
            current.map((pick) =>
                pick.user_id === userId ? { ...pick, role } : pick,
            ),
        );
    }

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

    function confirmTeam(): void {
        if (!matching && !manualMode) {
            return;
        }

        router.post(
            confirm.url(proposal.id),
            {
                run_id: manualMode ? undefined : matching?.id,
                manual: manualMode,
                members: picks.map((pick) => ({
                    user_id: pick.user_id,
                    role_in_project: pick.role,
                })),
            },
            {
                preserveScroll: true,
                onSuccess: () => setManualMode(false),
                onStart: () => setConfirming(true),
                onFinish: () => setConfirming(false),
            },
        );
    }

    function startManualTeam(): void {
        setManualMode(true);
        setPicks(
            team.map((member) => ({
                user_id: member.user_id,
                role: member.role === 'LEADER' ? 'LEADER' : 'MEMBER',
            })),
        );
    }

    const suggestionById = new Map(
        (matching?.members ?? []).map((member) => [member.user_id, member]),
    );
    const suggestedCandidates = (matching?.members ?? []).map((member) => ({
        suggestion: member,
        candidate:
            candidates.find(
                (candidate) => candidate.user_id === member.user_id,
            ) ??
            ({
                user_id: member.user_id,
                name: member.name ?? `#${member.user_id}`,
                position: null,
                organization: member.organization ?? '',
                organization_id: member.organization_id ?? 0,
                availability_status: null,
                matched_count: 0,
                requirement_count: requirements.length,
                competencies: member.competencies,
            } satisfies Candidate),
    }));

    const cardsFor = (
        list: { candidate: Candidate; suggestion?: Suggestion }[],
    ) => (
        <ul className="grid gap-4 md:grid-cols-2">
            {list.map(({ candidate, suggestion }) => {
                const picked = picks.some(
                    (pick) => pick.user_id === candidate.user_id,
                );

                return (
                    <li key={candidate.user_id}>
                        <CandidateCard
                            candidate={candidate}
                            requirements={requirements}
                            suggestion={suggestion}
                            picked={picked}
                            canPick={canEdit}
                            pickDisabled={
                                !picked && picks.length >= MAX_TEAM_MEMBERS
                            }
                            onTogglePick={() => togglePick(candidate.user_id)}
                        />
                    </li>
                );
            })}
        </ul>
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

                <Alert className="mb-6">
                    <Info aria-hidden="true" />
                    <AlertTitle>
                        Leader proyek berbeda dari role akun
                    </AlertTitle>
                    <AlertDescription>
                        Label Leader hanya berlaku untuk susunan proyek ini.
                        Konfirmasi tim tidak mengubah role akun SIMer menjadi
                        Team Leader; aturan penetapannya masih menunggu
                        keputusan DEC-05.
                    </AlertDescription>
                </Alert>

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
                                    href={settingsIndex()}
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

                {can_edit_team && (
                    <div className="mb-6 flex flex-wrap items-center gap-3">
                        <Button
                            type="button"
                            variant={manualMode ? 'teal' : 'outline'}
                            onClick={startManualTeam}
                        >
                            <ListChecks
                                data-icon="inline-start"
                                aria-hidden="true"
                            />
                            Susun tim manual
                        </Button>
                        {manualMode && (
                            <span className="text-sm text-muted-foreground">
                                Pilih kandidat dan tetapkan satu leader. Aturan
                                tim tetap diperiksa server.
                            </span>
                        )}
                    </div>
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

                {team.length > 0 && (
                    <Card className="mb-6 gap-0 py-0">
                        <CardContent className="grid gap-3 p-5">
                            <div className="flex items-center gap-2">
                                <UserCheck
                                    className="size-4 text-success"
                                    aria-hidden="true"
                                />
                                <h2 className="text-base font-semibold">
                                    Tim Saat Ini
                                </h2>
                            </div>
                            <ul className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                {team.map((member) => (
                                    <li
                                        key={member.user_id}
                                        className="flex items-center justify-between gap-3 rounded-lg border p-3"
                                    >
                                        <div className="grid min-w-0">
                                            <p className="truncate text-[13px] font-medium">
                                                {member.name}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {member.organization}
                                            </p>
                                        </div>
                                        <Badge
                                            variant={
                                                member.role === 'LEADER'
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {member.role === 'LEADER'
                                                ? 'Leader proyek'
                                                : 'Anggota'}
                                        </Badge>
                                        {canEdit && (
                                            <Button
                                                type="button"
                                                variant={
                                                    picks.some(
                                                        (pick) =>
                                                            pick.user_id ===
                                                            member.user_id,
                                                    )
                                                        ? 'secondary'
                                                        : 'outline'
                                                }
                                                size="sm"
                                                aria-pressed={picks.some(
                                                    (pick) =>
                                                        pick.user_id ===
                                                        member.user_id,
                                                )}
                                                disabled={
                                                    !picks.some(
                                                        (pick) =>
                                                            pick.user_id ===
                                                            member.user_id,
                                                    ) &&
                                                    picks.length >=
                                                        MAX_TEAM_MEMBERS
                                                }
                                                onClick={() =>
                                                    togglePick(member.user_id)
                                                }
                                            >
                                                {picks.some(
                                                    (pick) =>
                                                        pick.user_id ===
                                                        member.user_id,
                                                )
                                                    ? 'Dipertahankan'
                                                    : 'Pertahankan'}
                                            </Button>
                                        )}
                                    </li>
                                ))}
                            </ul>
                            {canEdit && (
                                <p className="text-xs text-muted-foreground">
                                    Mengonfirmasi susunan baru menggantikan tim
                                    ini. Pilih "Pertahankan" untuk anggota yang
                                    tetap ikut.
                                </p>
                            )}
                        </CardContent>
                    </Card>
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

                <div
                    className={
                        canEdit
                            ? 'grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_380px]'
                            : 'grid gap-6'
                    }
                >
                    <div className="grid min-w-0 gap-8">
                        {isReady && (
                            <section
                                className="grid gap-4"
                                aria-label="Rekomendasi tim AI"
                            >
                                <div className="flex items-center gap-2">
                                    <Sparkles
                                        className="size-4 text-primary"
                                        aria-hidden="true"
                                    />
                                    <h2 className="text-base font-semibold">
                                        {matching.source === 'MANUAL'
                                            ? 'Susunan Tim Manual'
                                            : 'Rekomendasi Tim AI'}
                                    </h2>
                                    {isConfirmed && (
                                        <Badge variant="success">
                                            Sudah dikonfirmasi
                                        </Badge>
                                    )}
                                </div>

                                {matching.rule_problems.length > 0 && (
                                    <Alert variant="destructive">
                                        <TriangleAlert aria-hidden="true" />
                                        <AlertTitle>
                                            Saran AI ini melanggar aturan tim
                                        </AlertTitle>
                                        <AlertDescription>
                                            Ubah susunan di panel kanan sebelum
                                            dikonfirmasi.
                                            <ul className="mt-1 list-disc pl-4">
                                                {matching.rule_problems.map(
                                                    (problem) => (
                                                        <li key={problem}>
                                                            {problem}
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        </AlertDescription>
                                    </Alert>
                                )}

                                {cardsFor(suggestedCandidates)}

                                {(matching.team_gaps.length > 0 ||
                                    matching.cautions.length > 0 ||
                                    matching.mitigations.length > 0) && (
                                    <Card className="gap-0 py-0">
                                        <CardContent className="grid gap-2 p-5 text-[13px]">
                                            {matching.team_gaps.length > 0 && (
                                                <p>
                                                    <span className="font-medium">
                                                        Kekurangan tim menurut
                                                        AI:
                                                    </span>{' '}
                                                    {matching.team_gaps.join(
                                                        '; ',
                                                    )}
                                                </p>
                                            )}
                                            {matching.cautions.length > 0 && (
                                                <p>
                                                    <span className="font-medium">
                                                        Perlu diperhatikan:
                                                    </span>{' '}
                                                    {matching.cautions.join(
                                                        '; ',
                                                    )}
                                                </p>
                                            )}
                                            {matching.mitigations.length >
                                                0 && (
                                                <div className="grid gap-3 border-t pt-3">
                                                    <p className="font-medium">
                                                        Rekomendasi pengembangan
                                                    </p>
                                                    {matching.mitigations.map(
                                                        (mitigation, index) => (
                                                            <div
                                                                key={`${mitigation.type}-${index}`}
                                                                className="grid gap-1 rounded-lg bg-muted/50 p-3"
                                                            >
                                                                <div className="flex flex-wrap items-center gap-2">
                                                                    <Badge variant="secondary">
                                                                        {
                                                                            mitigationTypeLabels[
                                                                                mitigation
                                                                                    .type
                                                                            ]
                                                                        }
                                                                    </Badge>
                                                                    <span className="font-medium">
                                                                        {
                                                                            mitigation.gap
                                                                        }
                                                                    </span>
                                                                </div>
                                                                <p className="text-muted-foreground">
                                                                    {
                                                                        mitigation.recommendation
                                                                    }
                                                                </p>
                                                            </div>
                                                        ),
                                                    )}
                                                </div>
                                            )}
                                        </CardContent>
                                    </Card>
                                )}
                                <p className="text-xs text-muted-foreground">
                                    Ini saran, bukan tim. Tim baru terbentuk
                                    setelah kamu mengonfirmasi susunannya.
                                </p>
                            </section>
                        )}

                        {candidates.length > 0 && (
                            <section
                                className="grid gap-3"
                                aria-label="Semua kandidat"
                            >
                                <div>
                                    <h2 className="text-base font-semibold">
                                        Semua Kandidat
                                    </h2>
                                    <p className="text-[13px] text-muted-foreground">
                                        {candidates.length} kandidat teratas
                                        (maksimal {limit}), diurutkan dari yang
                                        paling sesuai.
                                        {canEdit &&
                                            ' Kamu bisa menambahkan siapa saja ke susunan.'}
                                    </p>
                                </div>
                                {cardsFor(
                                    candidates
                                        .filter(
                                            (candidate) =>
                                                !suggestionById.has(
                                                    candidate.user_id,
                                                ),
                                        )
                                        .map((candidate) => ({ candidate })),
                                )}
                            </section>
                        )}
                    </div>

                    {canEdit && (
                        <aside className="lg:sticky lg:top-6">
                            <TeamPanel
                                picks={picks}
                                people={people}
                                problems={problems}
                                gaps={gaps}
                                changedFromAi={changedFromAi}
                                processing={confirming}
                                serverErrors={serverErrors}
                                onRoleChange={changeRole}
                                onRemove={togglePick}
                                onReset={() => setPicks(aiPicks)}
                                onConfirm={confirmTeam}
                            />
                        </aside>
                    )}
                </div>
            </div>
        </>
    );
}
