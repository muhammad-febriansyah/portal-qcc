import { errorAlertClass } from '@/lib/alert-styles';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Info,
    ListChecks,
    Eye,
    Plus,
    Search,
    Sparkles,
    TriangleAlert,
    UserCheck,
    Users,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { ApprovedTeamPanel } from '@/components/approved-team-panel';
import { PageHeader } from '@/components/page-header';
import { TeamPanel } from '@/components/team-panel';
import type { TeamPanelPerson } from '@/components/team-panel';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
import {
    destroy as removeTeamMember,
    store as addTeamMember,
} from '@/routes/proposals/team/members';
import { update as changeTeamLeader } from '@/routes/proposals/team/leader';

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
    input_stale: boolean;
    created_at: string | null;
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

const infoAlertClass =
    'mb-6 border-primary/20 bg-primary/10 text-primary [&_[data-slot=alert-description]]:text-primary/80';

type CurrentTeamMember = {
    user_id: number;
    name: string;
    organization: string | null;
    organization_id: number | null;
    role: string;
    competencies: TeamCandidate['competencies'];
    open_items: { risks: number; actions: number };
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
    stale_input:
        'Informasi proposal atau kebutuhan kompetensi berubah. Jalankan ulang matching untuk rekomendasi terbaru.',
    queue_timeout:
        'Pekerjaan AI terlalu lama menunggu antrean. Pastikan queue worker aktif, lalu jalankan ulang matching.',
    processing_timeout:
        'Proses AI melewati batas waktu. Pastikan queue worker dan koneksi OpenAI aktif, lalu jalankan ulang matching.',
};

const MATCHING_QUEUE_TIMEOUT_SECONDS = 120;
const MATCHING_PROCESSING_TIMEOUT_SECONDS = 180;

type Props = {
    proposal: {
        id: number;
        code: string;
        title: string;
        pic_id: number | null;
    };
    requirements: TeamRequirement[];
    candidates: Candidate[];
    manual_candidates: Candidate[];
    manual_search: string;
    limit: number;
    ai: Ai;
    matching: Matching;
    team: CurrentTeamMember[];
    can_edit_team: boolean;
    can_manage_approved_team: boolean;
};

function initials(name: string): string {
    return name
        .replace(/^\[[^\]]+\]\s*/, '')
        .split(' ')
        .filter(Boolean)
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
    action?: ReactNode;
};

function CandidateCard({
    candidate,
    requirements,
    suggestion,
    picked,
    canPick,
    pickDisabled,
    onTogglePick,
    action,
}: CardProps) {
    const [detailsOpen, setDetailsOpen] = useState(false);
    const byId = Object.fromEntries(
        requirements.map((requirement) => [
            requirement.competency_id,
            requirement,
        ]),
    );
    const missing = personGaps(candidate, requirements);
    const evidence = suggestion?.competencies ?? candidate.competencies;
    const matchedRequirements = requirements.filter((requirement) => {
        const held = evidence.find(
            (competency) =>
                competency.competency_id === requirement.competency_id,
        );

        return (
            held !== undefined &&
            (held.verified_level ?? held.self_level) >=
                (requirement.minimum_level ?? 0)
        );
    }).length;
    const matchPercentage = requirements.length
        ? Math.round((matchedRequirements / requirements.length) * 100)
        : 0;

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
                            matchedRequirements === requirements.length
                                ? 'success'
                                : 'secondary'
                        }
                        className="ml-auto shrink-0"
                    >
                        {matchPercentage}% kompetensi
                    </Badge>
                </div>

                {suggestion && (
                    <>
                        <p className="text-sm">{suggestion.match_reason}</p>
                        <p className="text-sm text-muted-foreground">
                            Kontribusi: {suggestion.contribution}
                        </p>
                    </>
                )}

                <ul className="grid gap-1.5">
                    {candidate.competencies.map((competency) => (
                        <li
                            key={competency.competency_id}
                            className="flex items-center justify-between gap-3 text-sm"
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
                                {
                                    ' · Saran coaching: dampingi praktik kompetensi ini hingga mencapai level minimum.'
                                }
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
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            size="sm"
                            className="bg-warning text-white hover:bg-warning/90 focus-visible:ring-warning"
                            onClick={() => setDetailsOpen(true)}
                        >
                            <Eye data-icon="inline-start" aria-hidden="true" />
                            Detail kecocokan
                        </Button>
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
                                <Plus
                                    data-icon="inline-start"
                                    aria-hidden="true"
                                />
                            )}
                            {picked ? 'Ada di susunan' : 'Tambah ke susunan'}
                        </Button>
                    </div>
                )}

                {!canPick && (
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            size="sm"
                            className="bg-warning text-white hover:bg-warning/90 focus-visible:ring-warning"
                            onClick={() => setDetailsOpen(true)}
                        >
                            <Eye data-icon="inline-start" aria-hidden="true" />
                            Detail kecocokan
                        </Button>
                        {action}
                    </div>
                )}
            </CardContent>

            <Dialog open={detailsOpen} onOpenChange={setDetailsOpen}>
                <DialogContent className="max-h-[85vh] max-w-4xl gap-6 overflow-y-auto p-6 sm:max-w-4xl">
                    <DialogHeader className="pr-8">
                        <DialogTitle className="text-lg">
                            Detail kecocokan · {candidate.name}
                        </DialogTitle>
                        <DialogDescription>
                            {candidate.position ?? 'Jabatan belum diisi'} ·{' '}
                            {candidate.organization}
                        </DialogDescription>
                    </DialogHeader>

                    <section className="grid gap-3 rounded-xl border bg-muted/30 p-4 sm:grid-cols-[auto_1fr] sm:items-center">
                        <span className="text-3xl font-semibold text-primary">
                            {matchPercentage}%
                        </span>
                        <div className="grid gap-1 text-sm">
                            <p className="font-medium">
                                {matchedRequirements} dari {requirements.length}{' '}
                                kompetensi memenuhi level minimum
                            </p>
                            <p className="text-muted-foreground">
                                Persentase ini mengukur cakupan kompetensi saja:
                                kompetensi yang memenuhi dibagi seluruh
                                kompetensi yang diminta. Ranking AI juga
                                mempertimbangkan organisasi, ketersediaan, dan
                                pengalaman QCC; persentase bukan skor keyakinan
                                AI.
                            </p>
                        </div>
                    </section>

                    <section className="grid gap-3">
                        <h3 className="text-sm font-semibold">
                            Rincian kompetensi
                        </h3>
                        <ul className="grid gap-2">
                            {requirements.map((requirement) => {
                                const held = evidence.find(
                                    (competency) =>
                                        competency.competency_id ===
                                        requirement.competency_id,
                                );
                                const effectiveLevel = held
                                    ? (held.verified_level ?? held.self_level)
                                    : null;
                                const meets =
                                    effectiveLevel !== null &&
                                    effectiveLevel >=
                                        (requirement.minimum_level ?? 0);

                                return (
                                    <li
                                        key={requirement.competency_id}
                                        className="grid gap-1 rounded-lg border p-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"
                                    >
                                        <div className="grid gap-1">
                                            <span className="text-sm font-medium">
                                                {requirement.code} ·{' '}
                                                {requirement.name}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                Minimum level{' '}
                                                {requirement.minimum_level ??
                                                    'tidak ditentukan'}
                                                {' · '}
                                                {held
                                                    ? `Self ${held.self_level}${held.verified_level !== null ? ` · Terverifikasi ${held.verified_level}` : ' · Belum diverifikasi'}`
                                                    : 'Belum tercantum di profil kompetensi'}
                                            </span>
                                        </div>
                                        <Badge
                                            variant={
                                                meets ? 'success' : 'warning'
                                            }
                                        >
                                            {meets
                                                ? 'Memenuhi'
                                                : 'Perlu coaching'}
                                        </Badge>
                                    </li>
                                );
                            })}
                        </ul>
                    </section>

                    {suggestion && (
                        <section className="grid gap-3">
                            <h3 className="text-sm font-semibold">
                                Alasan rekomendasi AI
                            </h3>
                            <div className="grid gap-3 rounded-xl border p-4 text-sm">
                                <p>{suggestion.match_reason}</p>
                                <p className="text-muted-foreground">
                                    <span className="font-medium text-foreground">
                                        Kontribusi:
                                    </span>{' '}
                                    {suggestion.contribution}
                                </p>
                                {suggestion.gaps.length > 0 && (
                                    <p className="text-muted-foreground">
                                        <span className="font-medium text-foreground">
                                            Gap:
                                        </span>{' '}
                                        {suggestion.gaps.join('; ')}
                                    </p>
                                )}
                                {suggestion.uncertainties.length > 0 && (
                                    <p className="text-muted-foreground">
                                        <span className="font-medium text-foreground">
                                            Ketidakpastian:
                                        </span>{' '}
                                        {suggestion.uncertainties.join('; ')}
                                    </p>
                                )}
                            </div>
                        </section>
                    )}

                    {missing.length > 0 && (
                        <Alert
                            variant="warning"
                            className="border-warning/30 bg-warning/10 text-amber-950 [&_[data-slot=alert-description]]:text-amber-900/80"
                        >
                            <TriangleAlert aria-hidden="true" />
                            <AlertTitle>Rekomendasi coaching</AlertTitle>
                            <AlertDescription>
                                Dampingi praktik{' '}
                                {missing.map((gap) => gap.name).join(', ')}{' '}
                                sampai level minimum tercapai.
                            </AlertDescription>
                        </Alert>
                    )}
                </DialogContent>
            </Dialog>
        </Card>
    );
}

export default function ProposalCandidates({
    proposal,
    requirements,
    candidates,
    manual_candidates,
    manual_search,
    limit,
    ai,
    matching,
    team,
    can_edit_team,
    can_manage_approved_team,
}: Props) {
    const { errors } = usePage().props as { errors: Record<string, string> };
    const [starting, setStarting] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const [teamBusyUserId, setTeamBusyUserId] = useState<number | null>(null);
    const [manualMode, setManualMode] = useState(false);
    const [stalledDialogOpen, setStalledDialogOpen] = useState(false);
    const [picId, setPicId] = useState<number | null>(null);
    const [searchText, setSearchText] = useState(manual_search);
    const [candidateSearch, setCandidateSearch] = useState('');
    const [approvedSearch, setApprovedSearch] = useState('');
    const [candidatePage, setCandidatePage] = useState(1);
    const [manualPage, setManualPage] = useState(1);
    const [matchingClock, setMatchingClock] = useState(Date.now());
    const [manualCandidateCache, setManualCandidateCache] = useState<
        Candidate[]
    >([]);
    const isRunning =
        matching?.status === 'QUEUED' || matching?.status === 'PROCESSING';
    const matchingStartedAt = matching?.created_at
        ? new Date(matching.created_at).getTime()
        : null;
    const matchingTimeoutSeconds =
        matching?.status === 'PROCESSING'
            ? MATCHING_PROCESSING_TIMEOUT_SECONDS
            : MATCHING_QUEUE_TIMEOUT_SECONDS;
    const isStalled =
        isRunning &&
        matchingStartedAt !== null &&
        matchingClock - matchingStartedAt > matchingTimeoutSeconds * 1000;
    const matchingLoadingDialogOpen = starting || (isRunning && !isStalled);
    const isReady = matching?.status === 'READY';
    const isConfirmed = isReady && matching.confirmed_at !== null;
    const canEdit = can_edit_team && (manualMode || (isReady && !isConfirmed));

    useEffect(() => {
        setSearchText(manual_search);
    }, [manual_search]);

    useEffect(() => {
        setStalledDialogOpen(isStalled);
    }, [isStalled]);

    useEffect(() => {
        if (manual_candidates.length === 0) {
            return;
        }

        setManualCandidateCache((current) => {
            const merged = new Map(
                current.map((candidate) => [candidate.user_id, candidate]),
            );
            manual_candidates.forEach((candidate) =>
                merged.set(candidate.user_id, candidate),
            );

            return [...merged.values()];
        });
    }, [manual_candidates]);

    useEffect(() => {
        if (!manualMode || searchText.trim().length < 2) {
            return;
        }

        const timer = window.setTimeout(() => {
            router.get(
                candidatesRoute.url(proposal.id),
                { manual_search: searchText.trim() },
                {
                    only: ['manual_candidates', 'manual_search'],
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                },
            );
        }, 250);

        return () => window.clearTimeout(timer);
    }, [manualMode, proposal.id, searchText]);

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
            const now = Date.now();
            setMatchingClock(now);

            if (
                matchingStartedAt !== null &&
                now - matchingStartedAt <= matchingTimeoutSeconds * 1000
            ) {
                router.reload({ only: ['matching'] });
            }
        }, 3000);

        return () => window.clearInterval(timer);
    }, [isRunning, matchingStartedAt, matchingTimeoutSeconds]);

    const candidateMap = useMemo(() => {
        const map = new Map<number, TeamCandidate>(
            [...candidates, ...manualCandidateCache].map((candidate) => [
                candidate.user_id,
                candidate,
            ]),
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
    }, [candidates, manualCandidateCache, matching, team]);

    const people = useMemo(() => {
        const map = new Map<number, TeamPanelPerson>();

        for (const member of matching?.members ?? []) {
            map.set(member.user_id, {
                user_id: member.user_id,
                name: member.name ?? `#${member.user_id}`,
                organization: member.organization ?? '',
            });
        }

        for (const candidate of [...candidates, ...manualCandidateCache]) {
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
    }, [candidates, manualCandidateCache, matching, team]);

    const problems = checkTeam(picks, candidateMap);
    const gaps = teamGaps(picks, candidateMap, requirements);
    const hasAiSuggestion = isReady && matching?.source === 'AI';
    const changedFromAi = hasAiSuggestion && !sameTeam(picks, aiPicks);
    const serverErrors = Object.entries(errors)
        .filter(
            ([key]) =>
                key === 'team' || key === 'pic_id' || key.startsWith('members'),
        )
        .map(([, message]) => message);

    function togglePick(userId: number): void {
        if (picId === userId && picks.some((pick) => pick.user_id === userId)) {
            setPicId(null);
        }

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

    function addApprovedMember(userId: number): void {
        router.post(
            addTeamMember.url(proposal.id),
            { user_id: userId },
            {
                preserveScroll: true,
                onStart: () => setTeamBusyUserId(userId),
                onFinish: () => setTeamBusyUserId(null),
            },
        );
    }

    function removeApprovedMember(
        userId: number,
        replacementPicId: number | null,
    ): void {
        router.delete(
            removeTeamMember.url({ project: proposal.id, member: userId }),
            {
                data:
                    replacementPicId === null
                        ? {}
                        : { replacement_pic_id: replacementPicId },
                preserveScroll: true,
                onStart: () => setTeamBusyUserId(userId),
                onFinish: () => setTeamBusyUserId(null),
            },
        );
    }

    function changeApprovedLeader(userId: number): void {
        router.post(
            changeTeamLeader.url(proposal.id),
            { user_id: userId },
            {
                preserveScroll: true,
                onStart: () => setTeamBusyUserId(userId),
                onFinish: () => setTeamBusyUserId(null),
            },
        );
    }

    useEffect(() => {
        if (!can_manage_approved_team || approvedSearch.trim().length < 2) {
            return;
        }

        const timer = window.setTimeout(() => {
            router.get(
                candidatesRoute(proposal.id),
                { manual_search: approvedSearch.trim() },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: ['manual_candidates', 'manual_search'],
                },
            );
        }, 250);

        return () => window.clearTimeout(timer);
    }, [can_manage_approved_team, proposal.id, approvedSearch]);

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
        if ((!matching && !manualMode) || picId === null) {
            return;
        }

        router.post(
            confirm.url(proposal.id),
            {
                run_id: manualMode ? undefined : matching?.id,
                manual: manualMode,
                pic_id: picId,
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
        if (
            picId !== null &&
            !team.some((member) => member.user_id === picId)
        ) {
            setPicId(null);
        }
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
    const visiblePageSize = 6;
    const filteredCandidates = candidates
        .filter((candidate) => !suggestionById.has(candidate.user_id))
        .filter((candidate) => {
            const query = candidateSearch.trim().toLocaleLowerCase();

            return (
                query === '' ||
                [candidate.name, candidate.position, candidate.organization]
                    .filter(Boolean)
                    .some((value) => value?.toLocaleLowerCase().includes(query))
            );
        });
    const candidatePageCount = Math.max(
        1,
        Math.ceil(filteredCandidates.length / visiblePageSize),
    );
    const visibleCandidates = filteredCandidates.slice(
        (candidatePage - 1) * visiblePageSize,
        candidatePage * visiblePageSize,
    );
    const manualPageCount = Math.max(
        1,
        Math.ceil(manual_candidates.length / visiblePageSize),
    );
    const visibleManualCandidates = manual_candidates.slice(
        (manualPage - 1) * visiblePageSize,
        manualPage * visiblePageSize,
    );
    const teamControls = (
        <div className="grid gap-4">
            <div className="grid gap-2 rounded-xl border bg-card p-4">
                <label htmlFor="proposal-pic" className="text-sm font-medium">
                    PIC proposal <span className="text-destructive">*</span>
                </label>
                <Select
                    value={picId?.toString() ?? ''}
                    onValueChange={(value) =>
                        setPicId(value ? Number(value) : null)
                    }
                    disabled={picks.length === 0 || confirming}
                >
                    <SelectTrigger
                        id="proposal-pic"
                        aria-invalid={Boolean(errors.pic_id)}
                    >
                        <SelectValue placeholder="Pilih dari anggota tim" />
                    </SelectTrigger>
                    <SelectContent
                        align="start"
                        className="w-[min(26rem,calc(100vw-2rem))] max-w-[calc(100vw-2rem)]"
                    >
                        <SelectGroup>
                            {picks.map((pick) => (
                                <SelectItem
                                    key={pick.user_id}
                                    value={String(pick.user_id)}
                                >
                                    {people.get(pick.user_id)?.name ??
                                        `#${pick.user_id}`}{' '}
                                    ·{' '}
                                    {pick.role === 'LEADER'
                                        ? 'Leader'
                                        : 'Anggota'}
                                </SelectItem>
                            ))}
                        </SelectGroup>
                    </SelectContent>
                </Select>
                <p className="text-xs text-muted-foreground">
                    Penanggung jawab proposal harus anggota tim yang
                    dikonfirmasi.
                </p>
                {errors.pic_id && (
                    <p role="alert" className="text-sm text-destructive">
                        {errors.pic_id}
                    </p>
                )}
            </div>
            <TeamPanel
                picks={picks}
                people={people}
                problems={
                    picId === null
                        ? [...problems, 'Pilih PIC proposal dari anggota tim.']
                        : problems
                }
                gaps={gaps}
                changedFromAi={changedFromAi}
                hasAiSuggestion={hasAiSuggestion}
                processing={confirming}
                serverErrors={serverErrors}
                onRoleChange={changeRole}
                onRemove={togglePick}
                onReset={() => setPicks(aiPicks)}
                onConfirm={confirmTeam}
            />
        </div>
    );

    function candidatePagination(
        total: number,
        page: number,
        pageCount: number,
        onPageChange: (page: number) => void,
    ) {
        const first = total === 0 ? 0 : (page - 1) * visiblePageSize + 1;
        const last = Math.min(page * visiblePageSize, total);

        return (
            <div className="flex flex-col gap-3 border-t pt-4 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                <span>
                    Menampilkan {first}–{last} dari {total} kandidat
                </span>
                <div className="flex items-center gap-2 self-end sm:self-auto">
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        disabled={page <= 1}
                        onClick={() => onPageChange(page - 1)}
                    >
                        <ChevronLeft
                            data-icon="inline-start"
                            aria-hidden="true"
                        />
                        Sebelumnya
                    </Button>
                    <span aria-live="polite">
                        Halaman {page} dari {pageCount}
                    </span>
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        disabled={page >= pageCount}
                        onClick={() => onPageChange(page + 1)}
                    >
                        Berikutnya
                        <ChevronRight
                            data-icon="inline-end"
                            aria-hidden="true"
                        />
                    </Button>
                </div>
            </div>
        );
    }

    const cardsFor = (
        list: { candidate: Candidate; suggestion?: Suggestion }[],
        renderAction?: (candidate: Candidate) => ReactNode,
    ) => (
        <ul className="grid gap-4 md:grid-cols-2">
            {list.map(({ candidate, suggestion }) => {
                const picked = picks.some(
                    (pick) => pick.user_id === candidate.user_id,
                );

                return (
                    <li key={candidate.user_id}>
                        <CandidateCard
                            action={renderAction?.(candidate)}
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

    if (can_manage_approved_team) {
        const approvedTeamErrors = [
            'team',
            'members',
            'user_id',
            'replacement_pic_id',
        ]
            .map((key) => errors[key])
            .filter((message): message is string => Boolean(message));
        const teamFull = team.length >= MAX_TEAM_MEMBERS;
        const approvedSearching = approvedSearch.trim().length >= 2;
        const approvedList = approvedSearching ? manual_candidates : candidates;
        const approvedPageCount = Math.max(
            1,
            Math.ceil(approvedList.length / visiblePageSize),
        );
        const approvedPage = Math.min(candidatePage, approvedPageCount);
        const approvedVisible = approvedList.slice(
            (approvedPage - 1) * visiblePageSize,
            approvedPage * visiblePageSize,
        );

        return (
            <>
                <Head title={`Tim ${proposal.code}`} />
                <div className="mx-auto w-full max-w-[1440px] px-4 py-6 md:px-6 md:py-8">
                    <PageHeader
                        breadcrumbs={[
                            { title: 'Dashboard', href: dashboard() },
                            {
                                title: 'Tim Proposal',
                                href: candidatesRoute(proposal.id),
                            },
                        ]}
                        title="Tim Proposal"
                        description={`${proposal.code} · ${proposal.title}`}
                    />

                    <div className="grid items-start gap-6 lg:grid-cols-[380px_minmax(0,1fr)]">
                        <aside className="lg:sticky lg:top-6">
                            <ApprovedTeamPanel
                                team={team}
                                errors={approvedTeamErrors}
                                busyUserId={teamBusyUserId}
                                onRemove={removeApprovedMember}
                                onChangeLeader={changeApprovedLeader}
                            />
                        </aside>

                        <section
                            className="grid min-w-0 content-start gap-4"
                            aria-label="Tambah anggota tim"
                        >
                            <div>
                                <h2 className="text-base font-semibold">
                                    Tambah Anggota
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Kandidat yang memenuhi kebutuhan kompetensi
                                    proyek dan belum menjadi anggota tim.
                                    {teamFull &&
                                        ' Tim sudah penuh, keluarkan anggota dulu untuk menambah.'}
                                </p>
                            </div>
                            <div className="relative max-w-xl">
                                <Search
                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <Input
                                    value={approvedSearch}
                                    onChange={(event) => {
                                        setApprovedSearch(event.target.value);
                                        setCandidatePage(1);
                                    }}
                                    placeholder="Cari nama, jabatan, atau organisasi..."
                                    className="pl-9"
                                    aria-label="Cari kandidat tambahan"
                                />
                            </div>
                            {approvedVisible.length > 0 ? (
                                <>
                                    {cardsFor(
                                        approvedVisible.map((candidate) => ({
                                            candidate,
                                        })),
                                        (candidate) => (
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                disabled={
                                                    teamFull ||
                                                    teamBusyUserId !== null
                                                }
                                                onClick={() =>
                                                    addApprovedMember(
                                                        candidate.user_id,
                                                    )
                                                }
                                            >
                                                {teamBusyUserId ===
                                                candidate.user_id ? (
                                                    <Spinner data-icon="inline-start" />
                                                ) : (
                                                    <Plus
                                                        data-icon="inline-start"
                                                        aria-hidden="true"
                                                    />
                                                )}
                                                Tambah ke tim
                                            </Button>
                                        ),
                                    )}
                                    {candidatePagination(
                                        approvedList.length,
                                        approvedPage,
                                        approvedPageCount,
                                        setCandidatePage,
                                    )}
                                </>
                            ) : (
                                <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                                    {approvedSearching
                                        ? 'Tidak ada kandidat yang cocok dengan pencarian.'
                                        : 'Belum ada kandidat lain yang memenuhi kebutuhan kompetensi proyek.'}
                                </p>
                            )}
                        </section>
                    </div>
                </div>
            </>
        );
    }

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

                <Alert className={infoAlertClass}>
                    <Info aria-hidden="true" />
                    <AlertTitle>
                        Leader proyek berbeda dari role akun
                    </AlertTitle>
                    <AlertDescription>
                        Label Leader hanya berlaku untuk susunan proyek ini.
                        Konfirmasi tim tidak mengubah role akun SIMer menjadi
                        Team Leader.
                    </AlertDescription>
                </Alert>

                <Card className="mb-6 gap-0 py-0">
                    <CardContent className="grid gap-3 p-5">
                        <p className="text-sm font-medium">
                            Kompetensi yang dibutuhkan
                        </p>
                        {requirements.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
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
                    <Alert
                        variant="destructive"
                        className={`${errorAlertClass} mb-6`}
                    >
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
                    <Alert
                        variant="destructive"
                        className={`${errorAlertClass} mb-6`}
                    >
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

                {requirements.length > 0 &&
                    candidates.length > 0 &&
                    can_edit_team &&
                    ai.ready && (
                        <div className="mb-6 flex flex-wrap items-center gap-3">
                            <Button
                                type="button"
                                className="h-10 px-4 text-sm"
                                disabled={
                                    !ai.ready ||
                                    (isRunning && !isStalled) ||
                                    starting
                                }
                                onClick={runMatching}
                            >
                                <Sparkles
                                    data-icon="inline-start"
                                    aria-hidden="true"
                                />
                                {(isRunning && !isStalled) || starting
                                    ? 'AI Matching berjalan'
                                    : matching
                                      ? 'Jalankan Ulang AI Matching'
                                      : 'Jalankan AI Matching'}
                            </Button>
                            {isRunning && !isStalled && (
                                <span
                                    className="text-sm text-muted-foreground"
                                    role="status"
                                >
                                    {matching?.status_label}. Halaman diperbarui
                                    otomatis.
                                </span>
                            )}
                        </div>
                    )}

                <Dialog open={matchingLoadingDialogOpen}>
                    <DialogContent
                        showCloseButton={false}
                        className="max-w-sm gap-4 p-8 text-center sm:max-w-sm"
                    >
                        <div className="flex justify-center">
                            <Spinner
                                className="size-10 text-primary"
                                aria-label="Matching tim sedang diproses"
                            />
                        </div>
                        <DialogHeader className="items-center">
                            <DialogTitle>
                                {starting
                                    ? 'Mengirim permintaan matching'
                                    : matching?.status === 'QUEUED'
                                      ? 'Menunggu antrean AI'
                                      : 'AI sedang mencocokkan tim'}
                            </DialogTitle>
                            <DialogDescription>
                                {starting || matching?.status === 'QUEUED'
                                    ? 'Permintaan sedang menunggu queue worker mengambil pekerjaan.'
                                    : 'Profil kompetensi, organisasi, ketersediaan, dan pengalaman QCC sedang dianalisis.'}
                            </DialogDescription>
                        </DialogHeader>
                    </DialogContent>
                </Dialog>

                <Dialog
                    open={stalledDialogOpen}
                    onOpenChange={setStalledDialogOpen}
                >
                    <DialogContent className="max-w-lg gap-5 p-6 sm:max-w-lg">
                        <DialogHeader className="pr-8">
                            <div className="flex size-11 items-center justify-center rounded-xl bg-warning/15 text-amber-700">
                                <TriangleAlert
                                    className="size-5"
                                    aria-hidden="true"
                                />
                            </div>
                            <DialogTitle className="text-lg">
                                Matching terlalu lama di antrean
                            </DialogTitle>
                            <DialogDescription>
                                {matching?.status === 'QUEUED'
                                    ? 'Pekerjaan belum diambil queue worker setelah 2 menit. Periksa worker pada antrean AI, lalu coba jalankan ulang.'
                                    : 'Proses melewati batas 3 menit. Periksa queue worker dan koneksi OpenAI, lalu coba jalankan ulang.'}
                            </DialogDescription>
                        </DialogHeader>
                        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <DialogClose
                                render={
                                    <Button type="button" variant="outline">
                                        Tutup
                                    </Button>
                                }
                            />
                            <Button
                                type="button"
                                onClick={runMatching}
                                disabled={!ai.ready || starting}
                            >
                                <Sparkles
                                    data-icon="inline-start"
                                    aria-hidden="true"
                                />
                                Jalankan ulang
                            </Button>
                        </div>
                    </DialogContent>
                </Dialog>

                <Dialog
                    open={manualMode}
                    onOpenChange={(open) => setManualMode(open)}
                >
                    <DialogContent className="flex max-h-[90vh] w-[calc(100%-2rem)] max-w-7xl flex-col gap-0 overflow-hidden p-0 sm:max-w-7xl">
                        <DialogHeader className="border-b px-6 py-5 pr-14">
                            <DialogTitle className="text-xl">
                                Susun Tim Manual
                            </DialogTitle>
                            <DialogDescription>
                                Cari kandidat dari seluruh daftar, pilih
                                anggota, lalu tentukan PIC dan satu leader.
                                Maksimal 5 anggota; validasi akhir tetap
                                dilakukan server.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid min-h-0 gap-6 overflow-y-auto p-6 lg:grid-cols-[minmax(0,1fr)_380px]">
                            <section
                                className="grid min-w-0 content-start gap-4"
                                aria-label="Pencarian kandidat manual"
                            >
                                <div>
                                    <h3 className="font-semibold">
                                        Cari kandidat
                                    </h3>
                                    <p className="text-sm text-muted-foreground">
                                        Hasil pencarian mencakup kandidat di
                                        luar 30 rekomendasi teratas. Anggota
                                        dengan gap kompetensi tetap bisa
                                        dipilih.
                                    </p>
                                </div>
                                <div className="relative">
                                    <Search
                                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <Input
                                        value={searchText}
                                        onChange={(event) => {
                                            setSearchText(event.target.value);
                                            setManualPage(1);
                                        }}
                                        placeholder="Cari nama, divisi, atau jabatan..."
                                        className="pl-9"
                                        aria-label="Cari kandidat untuk susun tim manual"
                                    />
                                </div>
                                {searchText.trim().length < 2 ? (
                                    <div className="rounded-xl border border-dashed bg-muted/30 px-5 py-10 text-center text-sm text-muted-foreground">
                                        Masukkan minimal 2 karakter untuk
                                        mencari kandidat.
                                    </div>
                                ) : visibleManualCandidates.length > 0 ? (
                                    <>
                                        {cardsFor(
                                            visibleManualCandidates.map(
                                                (candidate) => ({ candidate }),
                                            ),
                                        )}
                                        {candidatePagination(
                                            manual_candidates.length,
                                            manualPage,
                                            manualPageCount,
                                            setManualPage,
                                        )}
                                    </>
                                ) : (
                                    <div className="rounded-xl border border-dashed bg-muted/30 px-5 py-10 text-center text-sm text-muted-foreground">
                                        Tidak ada kandidat yang cocok dengan
                                        pencarian.
                                    </div>
                                )}
                            </section>
                            <aside className="grid content-start gap-4 lg:sticky lg:top-0">
                                {teamControls}
                            </aside>
                        </div>
                    </DialogContent>
                </Dialog>

                {matching?.status === 'FAILED' && (
                    <Alert
                        variant="destructive"
                        className={`${errorAlertClass} mb-6`}
                    >
                        <TriangleAlert aria-hidden="true" />
                        <AlertTitle>Rekomendasi gagal dibuat</AlertTitle>
                        <AlertDescription>
                            {failureMessages[matching.error_code ?? ''] ??
                                'Terjadi kendala saat memproses rekomendasi. Coba lagi.'}
                        </AlertDescription>
                    </Alert>
                )}

                {isReady && matching.input_stale && (
                    <Alert
                        variant="warning"
                        className="mb-6 border-warning/30 bg-warning/10 text-amber-950 [&_[data-slot=alert-description]]:text-amber-900/80"
                    >
                        <TriangleAlert aria-hidden="true" />
                        <AlertTitle>Rekomendasi perlu diperbarui</AlertTitle>
                        <AlertDescription>
                            Informasi proposal atau kebutuhan kompetensi berubah
                            setelah matching. Jalankan ulang rekomendasi sebelum
                            konfirmasi atau pengajuan.
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
                                            <p className="truncate text-sm font-medium">
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
                    <div className="flex min-h-48 flex-col items-center justify-center gap-3 rounded-2xl border border-warning/30 bg-warning/10 px-6 py-8 text-center">
                        <span className="flex size-11 items-center justify-center rounded-xl bg-warning/15 text-amber-800">
                            <Users className="size-5" aria-hidden="true" />
                        </span>
                        <div className="grid gap-1">
                            <p className="text-sm font-semibold text-amber-950">
                                Belum ada kandidat yang cocok
                            </p>
                            <p className="max-w-md text-sm text-amber-900/80">
                                Kandidat dengan gap kompetensi bisa dicari dan
                                dipertimbangkan lewat susun tim manual. Sistem
                                tetap menampilkan gap serta saran coaching.
                            </p>
                        </div>
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
                                    <Alert
                                        variant="destructive"
                                        className={errorAlertClass}
                                    >
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
                                        <CardContent className="grid gap-2 p-5 text-sm">
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
                                    {matching.source === 'MANUAL'
                                        ? 'Susunan ini dibuat manual oleh Owner QCC.'
                                        : 'Ini saran, bukan tim. Tim baru terbentuk setelah kamu mengonfirmasi susunannya.'}
                                </p>
                            </section>
                        )}

                        {candidates.length > 0 && !manualMode && (
                            <section
                                className="grid gap-3"
                                aria-label="Semua kandidat"
                            >
                                <div>
                                    <h2 className="text-base font-semibold">
                                        Semua Kandidat
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        {candidates.length} kandidat teratas
                                        (maksimal {limit}), diurutkan dari yang
                                        paling sesuai.
                                        {canEdit &&
                                            ' Kamu bisa menambahkan siapa saja ke susunan.'}
                                    </p>
                                </div>
                                <div className="relative max-w-xl">
                                    <Search
                                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <Input
                                        value={candidateSearch}
                                        onChange={(event) => {
                                            setCandidateSearch(
                                                event.target.value,
                                            );
                                            setCandidatePage(1);
                                        }}
                                        placeholder="Cari nama, jabatan, atau organisasi..."
                                        className="pl-9"
                                        aria-label="Cari kandidat"
                                    />
                                </div>
                                {visibleCandidates.length > 0 ? (
                                    <>
                                        {cardsFor(
                                            visibleCandidates.map(
                                                (candidate) => ({ candidate }),
                                            ),
                                        )}
                                        {candidatePagination(
                                            filteredCandidates.length,
                                            candidatePage,
                                            candidatePageCount,
                                            setCandidatePage,
                                        )}
                                    </>
                                ) : (
                                    <p className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                                        Tidak ada kandidat yang cocok dengan
                                        pencarian.
                                    </p>
                                )}
                            </section>
                        )}
                    </div>

                    {canEdit && !manualMode && (
                        <aside className="lg:sticky lg:top-6">
                            {teamControls}
                        </aside>
                    )}
                </div>
            </div>
        </>
    );
}
