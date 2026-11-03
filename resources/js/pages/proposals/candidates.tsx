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
    UserRound,
    Users,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { ApprovedTeamPanel } from '@/components/approved-team-panel';
import { DataTable } from '@/components/data-table';
import type { DataTableColumnDef } from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { TeamPanel } from '@/components/team-panel';
import type { TeamPanelPerson } from '@/components/team-panel';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
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

type CandidateListRow = {
    candidate: Candidate;
    suggestion?: Suggestion;
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
    avatar: string | null;
    organization: string | null;
    organization_id: number | null;
    role: string;
    competencies: TeamCandidate['competencies'];
    open_items: { risks: number; actions: number };
};

function TeamMemberAvatar({
    name,
    avatar,
}: {
    name: string;
    avatar: string | null;
}) {
    return (
        <Avatar className="size-10 shrink-0">
            <AvatarImage src={avatar ?? undefined} alt={name} />
            <AvatarFallback className="bg-primary/10 text-xs font-semibold text-primary">
                <UserRound aria-hidden="true" />
            </AvatarFallback>
        </Avatar>
    );
}

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

function availabilityLabel(status: string | null): string {
    if (status === 'AVAILABLE') {
        return 'Tersedia untuk proyek';
    }

    if (status === 'UNAVAILABLE') {
        return 'Belum tersedia';
    }

    return 'Belum diatur';
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
            className={`h-full min-w-0 gap-0 py-0 ${picked ? 'ring-2 ring-primary/40' : ''}`}
        >
            <CardContent className="grid min-w-0 grid-cols-[minmax(0,1fr)] gap-3 p-5">
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

                <ul className="grid min-w-0 grid-cols-[minmax(0,1fr)] gap-1.5">
                    {candidate.competencies.map((competency) => (
                        <li
                            key={competency.competency_id}
                            className="flex min-w-0 items-center justify-between gap-3 text-sm"
                        >
                            <span className="min-w-0 truncate">
                                {byId[competency.competency_id]?.name}
                            </span>
                            <span className="max-w-[45%] text-right text-xs leading-snug text-muted-foreground">
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
    const [detailRow, setDetailRow] = useState<CandidateListRow | null>(null);
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
    const filteredCandidates = candidates.filter((candidate) => {
        const query = candidateSearch.trim().toLocaleLowerCase();

        return (
            query === '' ||
            [candidate.name, candidate.position, candidate.organization]
                .filter(Boolean)
                .some((value) => value?.toLocaleLowerCase().includes(query))
        );
    });
    const visibleCandidates = filteredCandidates.slice(
        (candidatePage - 1) * visiblePageSize,
        candidatePage * visiblePageSize,
    );
    const searchQuery = candidateSearch.trim().toLocaleLowerCase();
    const filteredSuggestions = suggestedCandidates.filter(
        ({ candidate }) =>
            searchQuery === '' ||
            [candidate.name, candidate.position, candidate.organization]
                .filter(Boolean)
                .some((value) =>
                    value?.toLocaleLowerCase().includes(searchQuery),
                ),
    );
    const visibleSuggestions = filteredSuggestions.slice(
        (candidatePage - 1) * visiblePageSize,
        candidatePage * visiblePageSize,
    );
    const candidateColumns = useMemo<DataTableColumnDef<CandidateListRow>[]>(
        () => [
            {
                id: 'candidate',
                header: 'Kandidat',
                cell: ({ row }) => {
                    const { candidate } = row.original;

                    return (
                        <div className="flex min-w-52 items-center gap-3 py-1">
                            <Avatar className="size-10 shrink-0">
                                <AvatarFallback className="bg-primary/10 text-xs font-semibold text-primary">
                                    {initials(candidate.name)}
                                </AvatarFallback>
                            </Avatar>
                            <div className="grid min-w-0 gap-0.5">
                                <span className="font-medium">
                                    {candidate.name}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {candidate.position ??
                                        'Jabatan belum diisi'}{' '}
                                    · {candidate.organization}
                                </span>
                            </div>
                        </div>
                    );
                },
            },
            {
                id: 'competencies',
                header: 'Kompetensi',
                cell: ({ row }) => {
                    const { candidate } = row.original;
                    const percentage =
                        candidate.requirement_count > 0
                            ? Math.round(
                                  (candidate.matched_count /
                                      candidate.requirement_count) *
                                      100,
                              )
                            : 0;

                    return (
                        <div className="grid justify-items-start gap-1">
                            <Badge
                                variant={
                                    percentage === 100 ? 'success' : 'secondary'
                                }
                            >
                                {percentage}% terpenuhi
                            </Badge>
                            <span className="text-xs text-muted-foreground">
                                {candidate.matched_count}/
                                {candidate.requirement_count} kompetensi
                            </span>
                        </div>
                    );
                },
            },
            {
                id: 'availability',
                header: 'Ketersediaan',
                cell: ({ row }) => (
                    <Badge
                        variant={
                            row.original.candidate.availability_status ===
                            'AVAILABLE'
                                ? 'success'
                                : row.original.candidate.availability_status ===
                                    'UNAVAILABLE'
                                  ? 'warning'
                                  : 'secondary'
                        }
                    >
                        {availabilityLabel(
                            row.original.candidate.availability_status,
                        )}
                    </Badge>
                ),
            },
            {
                id: 'role',
                header: 'Peran saran',
                cell: ({ row }) => {
                    const role = row.original.suggestion?.proposed_role;

                    return role ? (
                        <Badge
                            variant={
                                role === 'LEADER' ? 'default' : 'secondary'
                            }
                        >
                            {role === 'LEADER' ? 'Leader' : 'Anggota'}
                        </Badge>
                    ) : (
                        <span className="text-sm text-muted-foreground">—</span>
                    );
                },
            },
            {
                id: 'actions',
                header: 'Aksi',
                cell: ({ row }) => {
                    const item = row.original;
                    const picked = picks.some(
                        (pick) => pick.user_id === item.candidate.user_id,
                    );

                    return (
                        <div className="flex min-w-max items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setDetailRow(item)}
                            >
                                <Eye
                                    data-icon="inline-start"
                                    aria-hidden="true"
                                />
                                Detail
                            </Button>
                            {canEdit && (
                                <Button
                                    type="button"
                                    variant={picked ? 'secondary' : 'default'}
                                    size="sm"
                                    disabled={
                                        !picked &&
                                        picks.length >= MAX_TEAM_MEMBERS
                                    }
                                    onClick={() =>
                                        togglePick(item.candidate.user_id)
                                    }
                                >
                                    {picked ? 'Di susunan' : 'Tambah'}
                                </Button>
                            )}
                        </div>
                    );
                },
            },
        ],
        [canEdit, picks, requirements.length, togglePick],
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
        <Card className="gap-0 py-0">
            <CardContent className="grid min-w-0 gap-6 p-5 @lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                <section
                    className="grid content-start gap-3"
                    aria-label="Tim saat ini"
                >
                    {team.length > 0 ? (
                        <>
                            <div className="flex items-center justify-between gap-3">
                                <h3 className="text-sm font-semibold">
                                    Tim saat ini
                                </h3>
                                <Badge variant="secondary">
                                    {team.length}/5
                                </Badge>
                            </div>
                            <ul className="grid gap-2">
                                {team.map((member) => {
                                    const picked = picks.some(
                                        (pick) =>
                                            pick.user_id === member.user_id,
                                    );

                                    return (
                                        <li
                                            key={member.user_id}
                                            className="flex min-w-0 items-center justify-between gap-2 rounded-lg border bg-background px-3 py-2"
                                        >
                                            <div className="flex min-w-0 items-center gap-3">
                                                <TeamMemberAvatar
                                                    name={member.name}
                                                    avatar={member.avatar}
                                                />
                                                <div className="grid min-w-0">
                                                    <span className="truncate text-sm font-medium">
                                                        {member.name}
                                                    </span>
                                                    <span className="truncate text-xs text-muted-foreground">
                                                        {member.organization}
                                                    </span>
                                                </div>
                                            </div>
                                            <div className="flex shrink-0 items-center gap-2">
                                                <Badge
                                                    variant={
                                                        member.role === 'LEADER'
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {member.role === 'LEADER'
                                                        ? 'Leader'
                                                        : 'Anggota'}
                                                </Badge>
                                                <Button
                                                    type="button"
                                                    variant={
                                                        picked
                                                            ? 'secondary'
                                                            : 'outline'
                                                    }
                                                    size="sm"
                                                    aria-pressed={picked}
                                                    disabled={
                                                        !picked &&
                                                        picks.length >=
                                                            MAX_TEAM_MEMBERS
                                                    }
                                                    onClick={() =>
                                                        togglePick(
                                                            member.user_id,
                                                        )
                                                    }
                                                >
                                                    {picked
                                                        ? 'Dipertahankan'
                                                        : 'Pertahankan'}
                                                </Button>
                                            </div>
                                        </li>
                                    );
                                })}
                            </ul>
                            <p className="text-xs text-muted-foreground">
                                Anggota lama masuk susunan baru setelah dipilih.
                            </p>
                        </>
                    ) : (
                        <div className="grid min-h-36 content-center gap-2 rounded-xl border border-dashed bg-muted/20 p-5">
                            <p className="text-sm font-semibold">
                                Belum ada tim saat ini
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Pilih kandidat untuk mulai menyusun tim
                                proposal.
                            </p>
                        </div>
                    )}
                </section>
                <div className="grid content-start gap-4">
                    <div className="grid gap-2 rounded-xl border bg-background p-4">
                        <label
                            htmlFor="proposal-pic"
                            className="text-sm font-medium"
                        >
                            PIC proposal{' '}
                            <span className="text-destructive">*</span>
                        </label>
                        <Select
                            value={picId?.toString() ?? ''}
                            onValueChange={(value) =>
                                setPicId(value ? Number(value) : null)
                            }
                            items={picks.map((pick) => ({
                                value: String(pick.user_id),
                                label:
                                    people.get(pick.user_id)?.name ??
                                    String(pick.user_id),
                            }))}
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
                                className="w-full min-w-0"
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
                            <p
                                role="alert"
                                className="text-sm text-destructive"
                            >
                                {errors.pic_id}
                            </p>
                        )}
                    </div>
                    <TeamPanel
                        embedded
                        picks={picks}
                        people={people}
                        problems={
                            picId === null
                                ? [
                                      ...problems,
                                      'Pilih PIC proposal dari anggota tim.',
                                  ]
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
            </CardContent>
        </Card>
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

    const allCandidatesSection = !manualMode && (
        <Card className="gap-0 py-0">
            <CardHeader className="gap-1 px-5 pt-5 pb-3">
                <CardTitle className="text-base">Semua kandidat</CardTitle>
                <CardDescription>
                    {candidates.length} kandidat teratas (maksimal {limit}),
                    diurutkan dari yang paling sesuai.
                    {canEdit &&
                        ' Pilih Detail untuk membaca profil atau Tambah untuk menyusun tim.'}
                </CardDescription>
            </CardHeader>
            <DataTable
                embedded
                columns={candidateColumns}
                data={visibleCandidates.map((candidate) => ({ candidate }))}
                page={candidatePage}
                pageSize={visiblePageSize}
                total={filteredCandidates.length}
                onPageChange={setCandidatePage}
                search={candidateSearch}
                onSearchChange={(value) => {
                    setCandidateSearch(value);
                    setCandidatePage(1);
                }}
                searchPlaceholder="Cari nama, jabatan, atau organisasi..."
                emptyTitle="Kandidat tidak ditemukan"
                emptyDescription="Ubah kata kunci pencarian lalu coba lagi."
                rowId={(row) => String(row.candidate.user_id)}
            />
        </Card>
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
                    <Card className="mb-6 gap-0 py-0">
                        <CardHeader className="gap-1 px-5 pt-5 pb-3">
                            <CardTitle className="text-base">
                                Susun tim proposal
                            </CardTitle>
                            <CardDescription>
                                Minta rekomendasi AI atau pilih anggota secara
                                manual.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-wrap items-center gap-3 px-5 pb-5">
                            {requirements.length > 0 &&
                                candidates.length > 0 &&
                                ai.ready && (
                                    <Button
                                        type="button"
                                        disabled={
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
                                )}
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
                            {isRunning && !isStalled && (
                                <span
                                    className="text-sm text-muted-foreground"
                                    role="status"
                                >
                                    {matching?.status_label}. Halaman diperbarui
                                    otomatis.
                                </span>
                            )}
                        </CardContent>
                    </Card>
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
                            <aside className="@container grid min-w-0 content-start gap-4 lg:sticky lg:top-0">
                                {teamControls}
                            </aside>
                        </div>
                    </DialogContent>
                </Dialog>

                <Dialog
                    open={detailRow !== null}
                    onOpenChange={(open) => {
                        if (!open) {
                            setDetailRow(null);
                        }
                    }}
                >
                    <DialogContent className="flex max-h-[90vh] w-[calc(100%-2rem)] max-w-6xl flex-col gap-0 overflow-hidden p-0 sm:max-w-6xl">
                        {detailRow &&
                            (() => {
                                const { candidate, suggestion } = detailRow;
                                const evidence =
                                    suggestion?.competencies ??
                                    candidate.competencies;
                                const matched = requirements.filter(
                                    (requirement) => {
                                        const held = evidence.find(
                                            (item) =>
                                                item.competency_id ===
                                                requirement.competency_id,
                                        );
                                        return (
                                            held !== undefined &&
                                            (held.verified_level ??
                                                held.self_level) >=
                                                (requirement.minimum_level ?? 0)
                                        );
                                    },
                                ).length;
                                const percentage =
                                    requirements.length > 0
                                        ? Math.round(
                                              (matched / requirements.length) *
                                                  100,
                                          )
                                        : 0;

                                return (
                                    <>
                                        <DialogHeader className="border-b bg-muted/20 px-6 py-5 pr-14 sm:px-8">
                                            <div className="flex flex-wrap items-start justify-between gap-4">
                                                <div className="flex min-w-0 items-center gap-4">
                                                    <Avatar className="size-14 shrink-0">
                                                        <AvatarFallback className="bg-primary/10 text-base font-semibold text-primary">
                                                            {initials(
                                                                candidate.name,
                                                            )}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div className="grid min-w-0 gap-1">
                                                        <DialogTitle className="text-xl">
                                                            {candidate.name}
                                                        </DialogTitle>
                                                        <DialogDescription>
                                                            {candidate.position ??
                                                                'Jabatan belum diisi'}{' '}
                                                            ·{' '}
                                                            {
                                                                candidate.organization
                                                            }
                                                        </DialogDescription>
                                                    </div>
                                                </div>
                                                <div className="flex flex-wrap gap-2">
                                                    <Badge
                                                        variant={
                                                            suggestion?.proposed_role ===
                                                            'LEADER'
                                                                ? 'default'
                                                                : 'secondary'
                                                        }
                                                    >
                                                        {suggestion
                                                            ? suggestion.proposed_role ===
                                                              'LEADER'
                                                                ? 'Saran: Leader'
                                                                : 'Saran: Anggota'
                                                            : 'Kandidat'}
                                                    </Badge>
                                                    <Badge
                                                        variant={
                                                            candidate.availability_status ===
                                                            'AVAILABLE'
                                                                ? 'success'
                                                                : candidate.availability_status ===
                                                                    'UNAVAILABLE'
                                                                  ? 'warning'
                                                                  : 'secondary'
                                                        }
                                                    >
                                                        {availabilityLabel(
                                                            candidate.availability_status,
                                                        )}
                                                    </Badge>
                                                </div>
                                            </div>
                                        </DialogHeader>
                                        <div className="min-h-0 overflow-y-auto px-6 py-6 sm:px-8">
                                            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_300px]">
                                                <div className="grid content-start gap-6">
                                                    {suggestion && (
                                                        <section className="grid gap-3">
                                                            <h3 className="text-sm font-semibold">
                                                                Ringkasan
                                                                rekomendasi
                                                            </h3>
                                                            <div className="grid gap-4 rounded-xl border bg-card p-4 sm:grid-cols-2">
                                                                <div className="grid content-start gap-1">
                                                                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                                                        Alasan
                                                                        kecocokan
                                                                    </span>
                                                                    <p className="text-sm leading-6">
                                                                        {
                                                                            suggestion.match_reason
                                                                        }
                                                                    </p>
                                                                </div>
                                                                <div className="grid content-start gap-1">
                                                                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                                                        Kontribusi
                                                                        yang
                                                                        disarankan
                                                                    </span>
                                                                    <p className="text-sm leading-6">
                                                                        {
                                                                            suggestion.contribution
                                                                        }
                                                                    </p>
                                                                </div>
                                                            </div>
                                                        </section>
                                                    )}
                                                    <section className="grid gap-3">
                                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                                            <h3 className="text-sm font-semibold">
                                                                Rincian
                                                                kompetensi
                                                            </h3>
                                                            <span className="text-xs text-muted-foreground">
                                                                Level
                                                                terverifikasi
                                                                dipakai bila
                                                                tersedia; jika
                                                                belum, level
                                                                mandiri
                                                                ditampilkan.
                                                            </span>
                                                        </div>
                                                        {requirements.length >
                                                        0 ? (
                                                            <ul className="grid gap-2">
                                                                {requirements.map(
                                                                    (
                                                                        requirement,
                                                                    ) => {
                                                                        const held =
                                                                            evidence.find(
                                                                                (
                                                                                    item,
                                                                                ) =>
                                                                                    item.competency_id ===
                                                                                    requirement.competency_id,
                                                                            );
                                                                        const level =
                                                                            held
                                                                                ? (held.verified_level ??
                                                                                  held.self_level)
                                                                                : null;
                                                                        const meets =
                                                                            level !==
                                                                                null &&
                                                                            level >=
                                                                                (requirement.minimum_level ??
                                                                                    0);

                                                                        return (
                                                                            <li
                                                                                key={
                                                                                    requirement.competency_id
                                                                                }
                                                                                className="grid gap-3 rounded-xl border bg-card p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"
                                                                            >
                                                                                <div className="grid gap-1">
                                                                                    <p className="text-sm font-medium">
                                                                                        {
                                                                                            requirement.code
                                                                                        }{' '}
                                                                                        ·{' '}
                                                                                        {
                                                                                            requirement.name
                                                                                        }
                                                                                    </p>
                                                                                    <p className="text-xs text-muted-foreground">
                                                                                        Minimum{' '}
                                                                                        {requirement.minimum_level ??
                                                                                            'tidak ditentukan'}{' '}
                                                                                        ·{' '}
                                                                                        {held
                                                                                            ? `Mandiri ${held.self_level}${held.verified_level !== null ? ` · Terverifikasi ${held.verified_level}` : ' · Belum diverifikasi'}`
                                                                                            : 'Belum ada data kompetensi'}
                                                                                    </p>
                                                                                </div>
                                                                                <Badge
                                                                                    variant={
                                                                                        meets
                                                                                            ? 'success'
                                                                                            : 'warning'
                                                                                    }
                                                                                    className="w-fit"
                                                                                >
                                                                                    {meets
                                                                                        ? 'Memenuhi'
                                                                                        : 'Perlu coaching'}
                                                                                </Badge>
                                                                            </li>
                                                                        );
                                                                    },
                                                                )}
                                                            </ul>
                                                        ) : (
                                                            <p className="rounded-xl border border-dashed p-5 text-sm text-muted-foreground">
                                                                Belum ada
                                                                kompetensi yang
                                                                disyaratkan
                                                                proposal.
                                                            </p>
                                                        )}
                                                    </section>
                                                    {suggestion &&
                                                        (suggestion.gaps
                                                            .length > 0 ||
                                                            suggestion
                                                                .uncertainties
                                                                .length >
                                                                0) && (
                                                            <section className="grid gap-3">
                                                                <h3 className="text-sm font-semibold">
                                                                    Catatan dan
                                                                    gap
                                                                </h3>
                                                                <div className="grid gap-3 rounded-xl border border-warning/30 bg-warning/10 p-4 text-sm">
                                                                    {suggestion
                                                                        .gaps
                                                                        .length >
                                                                        0 && (
                                                                        <p className="leading-6">
                                                                            <span className="font-medium">
                                                                                Gap
                                                                                kompetensi:
                                                                            </span>{' '}
                                                                            {suggestion.gaps.join(
                                                                                '; ',
                                                                            )}
                                                                        </p>
                                                                    )}
                                                                    {suggestion
                                                                        .uncertainties
                                                                        .length >
                                                                        0 && (
                                                                        <p className="leading-6">
                                                                            <span className="font-medium">
                                                                                Perlu
                                                                                diverifikasi:
                                                                            </span>{' '}
                                                                            {suggestion.uncertainties.join(
                                                                                '; ',
                                                                            )}
                                                                        </p>
                                                                    )}
                                                                </div>
                                                            </section>
                                                        )}
                                                    {matching &&
                                                        (matching.team_gaps
                                                            .length > 0 ||
                                                            matching.cautions
                                                                .length > 0 ||
                                                            matching.mitigations
                                                                .length >
                                                                0) && (
                                                            <section className="grid gap-3">
                                                                <div className="flex items-center gap-2">
                                                                    <Sparkles
                                                                        className="size-4 text-primary"
                                                                        aria-hidden="true"
                                                                    />
                                                                    <h3 className="text-sm font-semibold">
                                                                        Analisis
                                                                        AI Tim
                                                                    </h3>
                                                                </div>
                                                                <div className="grid gap-4 rounded-xl border bg-card p-4">
                                                                    {matching
                                                                        .team_gaps
                                                                        .length >
                                                                        0 && (
                                                                        <div className="grid gap-1">
                                                                            <p className="text-sm font-medium">
                                                                                Kekurangan
                                                                                tim
                                                                            </p>
                                                                            <p className="text-sm leading-6 text-muted-foreground">
                                                                                {matching.team_gaps.join(
                                                                                    '; ',
                                                                                )}
                                                                            </p>
                                                                        </div>
                                                                    )}
                                                                    {matching
                                                                        .cautions
                                                                        .length >
                                                                        0 && (
                                                                        <div className="grid gap-1">
                                                                            <p className="text-sm font-medium">
                                                                                Perlu
                                                                                diperhatikan
                                                                            </p>
                                                                            <p className="text-sm leading-6 text-muted-foreground">
                                                                                {matching.cautions.join(
                                                                                    '; ',
                                                                                )}
                                                                            </p>
                                                                        </div>
                                                                    )}
                                                                    {matching
                                                                        .mitigations
                                                                        .length >
                                                                        0 && (
                                                                        <div className="grid gap-3 border-t pt-4">
                                                                            <p className="text-sm font-medium">
                                                                                Rekomendasi
                                                                                pengembangan
                                                                            </p>
                                                                            {matching.mitigations.map(
                                                                                (
                                                                                    mitigation,
                                                                                    index,
                                                                                ) => (
                                                                                    <div
                                                                                        key={`${mitigation.type}-${index}`}
                                                                                        className="grid gap-2 rounded-lg bg-muted/50 p-3"
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
                                                                                            <span className="text-sm font-medium">
                                                                                                {
                                                                                                    mitigation.gap
                                                                                                }
                                                                                            </span>
                                                                                        </div>
                                                                                        <p className="text-sm leading-6 text-muted-foreground">
                                                                                            {
                                                                                                mitigation.recommendation
                                                                                            }
                                                                                        </p>
                                                                                    </div>
                                                                                ),
                                                                            )}
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            </section>
                                                        )}
                                                </div>
                                                <aside className="grid content-start gap-4">
                                                    <Card className="gap-0 py-0">
                                                        <CardHeader className="gap-1 p-4 pb-2">
                                                            <CardTitle className="text-sm">
                                                                Cakupan
                                                                kompetensi
                                                            </CardTitle>
                                                            <CardDescription>
                                                                Ringkasan
                                                                pemenuhan syarat
                                                                proyek
                                                            </CardDescription>
                                                        </CardHeader>
                                                        <CardContent className="grid gap-2 p-4 pt-2">
                                                            <p className="text-3xl font-semibold tabular-nums">
                                                                {percentage}%
                                                            </p>
                                                            <p className="text-sm text-muted-foreground">
                                                                {matched} dari{' '}
                                                                {
                                                                    requirements.length
                                                                }{' '}
                                                                kompetensi
                                                                memenuhi level
                                                                minimum.
                                                            </p>
                                                        </CardContent>
                                                    </Card>
                                                    <Alert className="border-primary/20 bg-primary/5">
                                                        <Info aria-hidden="true" />
                                                        <AlertTitle className="text-sm">
                                                            Cara membaca skor
                                                        </AlertTitle>
                                                        <AlertDescription className="text-xs leading-5">
                                                            Persentase hanya
                                                            menunjukkan cakupan
                                                            kompetensi.
                                                            Peringkat
                                                            rekomendasi juga
                                                            mempertimbangkan
                                                            organisasi,
                                                            ketersediaan, dan
                                                            pengalaman QCC.
                                                        </AlertDescription>
                                                    </Alert>
                                                </aside>
                                            </div>
                                        </div>
                                    </>
                                );
                            })()}
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
                            <p className="max-w-md text-xs text-amber-900/70">
                                Syarat: kandidat harus punya minimal satu
                                kompetensi yang dibutuhkan proyek. Jika daftar
                                kosong, cek atau ubah kebutuhan kompetensi di
                                langkah 4 proposal.
                            </p>
                        </div>
                    </div>
                )}

                <Tabs defaultValue="candidates" className="min-w-0 gap-5">
                    <TabsList className="h-auto flex-wrap justify-start">
                        <TabsTrigger value="candidates">
                            Kandidat
                            <Badge variant="secondary">
                                {candidates.length}
                            </Badge>
                        </TabsTrigger>
                        {(canEdit || team.length > 0) && (
                            <TabsTrigger value="team">
                                {canEdit ? 'Susunan Tim' : 'Tim Saat Ini'}
                                <Badge variant="secondary">
                                    {canEdit ? picks.length : team.length}
                                </Badge>
                            </TabsTrigger>
                        )}
                    </TabsList>
                    <TabsContent
                        value="candidates"
                        className="grid min-w-0 gap-6"
                    >
                        {isReady && !isConfirmed && (
                            <Tabs
                                defaultValue="recommendations"
                                className="min-w-0 gap-4"
                            >
                                <TabsList className="w-full flex-wrap justify-start">
                                    <TabsTrigger value="recommendations">
                                        {matching.source === 'MANUAL'
                                            ? 'Susunan tim'
                                            : 'Rekomendasi AI'}
                                        <Badge variant="secondary">
                                            {suggestedCandidates.length}
                                        </Badge>
                                    </TabsTrigger>
                                    <TabsTrigger value="all-candidates">
                                        Semua kandidat
                                        <Badge variant="secondary">
                                            {candidates.length}
                                        </Badge>
                                    </TabsTrigger>
                                </TabsList>
                                <TabsContent value="recommendations">
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
                                                    Saran AI ini melanggar
                                                    aturan tim
                                                </AlertTitle>
                                                <AlertDescription>
                                                    Periksa tab Susunan Tim dan
                                                    perbaiki susunan sebelum
                                                    dikonfirmasi.
                                                    <ul className="mt-1 list-disc pl-4">
                                                        {matching.rule_problems.map(
                                                            (problem) => (
                                                                <li
                                                                    key={
                                                                        problem
                                                                    }
                                                                >
                                                                    {problem}
                                                                </li>
                                                            ),
                                                        )}
                                                    </ul>
                                                </AlertDescription>
                                            </Alert>
                                        )}

                                        <Card className="gap-0 py-0">
                                            <CardHeader className="gap-1 px-5 pt-5 pb-3">
                                                <CardTitle className="text-base">
                                                    Kandidat yang
                                                    direkomendasikan
                                                </CardTitle>
                                                <CardDescription>
                                                    {suggestedCandidates.length}{' '}
                                                    kandidat; buka detail untuk
                                                    membaca kecocokan, gap, dan
                                                    kontribusi.
                                                </CardDescription>
                                            </CardHeader>
                                            <DataTable
                                                embedded
                                                columns={candidateColumns}
                                                data={visibleSuggestions}
                                                page={candidatePage}
                                                pageSize={visiblePageSize}
                                                total={
                                                    filteredSuggestions.length
                                                }
                                                onPageChange={setCandidatePage}
                                                search={candidateSearch}
                                                onSearchChange={(value) => {
                                                    setCandidateSearch(value);
                                                    setCandidatePage(1);
                                                }}
                                                searchPlaceholder="Cari nama, jabatan, atau organisasi..."
                                                emptyTitle="Rekomendasi tidak ditemukan"
                                                emptyDescription="Ubah kata kunci pencarian lalu coba lagi."
                                                rowId={(row) =>
                                                    String(
                                                        row.candidate.user_id,
                                                    )
                                                }
                                            />
                                        </Card>

                                        <p className="text-xs text-muted-foreground">
                                            {matching.source === 'MANUAL'
                                                ? 'Susunan ini dibuat manual oleh Owner QCC.'
                                                : 'Ini saran, bukan tim. Tim baru terbentuk setelah kamu mengonfirmasi susunannya.'}
                                        </p>
                                    </section>
                                </TabsContent>
                                <TabsContent value="all-candidates">
                                    {allCandidatesSection}
                                </TabsContent>
                            </Tabs>
                        )}

                        {(isConfirmed || !isReady) && allCandidatesSection}
                    </TabsContent>
                    {(canEdit || team.length > 0) && (
                        <TabsContent value="team" className="min-w-0">
                            {canEdit ? (
                                teamControls
                            ) : (
                                <Card className="gap-0 py-0">
                                    <CardHeader className="gap-1 px-5 pt-5 pb-3">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <UserCheck
                                                className="size-4 text-success"
                                                aria-hidden="true"
                                            />
                                            <CardTitle className="text-base">
                                                Tim Saat Ini
                                            </CardTitle>
                                            {isConfirmed && (
                                                <Badge variant="success">
                                                    Sudah dikonfirmasi
                                                </Badge>
                                            )}
                                        </div>
                                        <CardDescription>
                                            {team.length} anggota terdaftar pada
                                            tim proposal ini.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="grid gap-3 pb-5">
                                        <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                            {team.map((member) => (
                                                <li
                                                    key={member.user_id}
                                                    className="flex min-w-0 items-center justify-between gap-3 rounded-xl border bg-background p-4"
                                                >
                                                    <div className="flex min-w-0 items-center gap-3">
                                                        <TeamMemberAvatar
                                                            name={member.name}
                                                            avatar={
                                                                member.avatar
                                                            }
                                                        />
                                                        <div className="grid min-w-0 gap-1">
                                                            <p className="truncate text-sm font-medium">
                                                                {member.name}
                                                            </p>
                                                            <p className="truncate text-xs text-muted-foreground">
                                                                {member.organization ??
                                                                    'Organisasi belum diisi'}
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <Badge
                                                        variant={
                                                            member.role ===
                                                            'LEADER'
                                                                ? 'default'
                                                                : 'secondary'
                                                        }
                                                        className="shrink-0"
                                                    >
                                                        {member.role ===
                                                        'LEADER'
                                                            ? 'Leader proyek'
                                                            : 'Anggota'}
                                                    </Badge>
                                                </li>
                                            ))}
                                        </ul>
                                    </CardContent>
                                </Card>
                            )}
                        </TabsContent>
                    )}
                </Tabs>
            </div>
        </>
    );
}
