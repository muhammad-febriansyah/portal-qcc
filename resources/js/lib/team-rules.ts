export const MAX_TEAM_MEMBERS = 5;
export const MIN_TEAM_ORGANIZATIONS = 2;

export type TeamRole = 'LEADER' | 'MEMBER';

export type TeamPick = { user_id: number; role: TeamRole };

export type TeamCandidate = {
    user_id: number;
    organization_id: number;
    competencies: {
        competency_id: number;
        self_level: number;
        verified_level: number | null;
    }[];
};

export type TeamRequirement = {
    competency_id: number;
    code: string;
    name: string;
    minimum_level: number | null;
};

/**
 * Same rules the server enforces (TeamRuleValidator), so the screen can warn
 * while people change the team. The server still has the last word.
 */
export function checkTeam(
    picks: TeamPick[],
    candidates: Map<number, TeamCandidate>,
): string[] {
    if (picks.length === 0) {
        return ['Pilih minimal satu anggota tim.'];
    }

    const problems: string[] = [];

    if (picks.length > MAX_TEAM_MEMBERS) {
        problems.push('Jumlah anggota tim maksimal 5 orang, termasuk leader.');
    }

    if (picks.filter((pick) => pick.role === 'LEADER').length !== 1) {
        problems.push('Tim harus memiliki tepat 1 leader.');
    }

    const organizations = new Set(
        picks
            .map((pick) => candidates.get(pick.user_id)?.organization_id)
            .filter((id): id is number => typeof id === 'number'),
    );

    if (organizations.size < MIN_TEAM_ORGANIZATIONS) {
        problems.push('Anggota tim harus berasal dari minimal 2 LOB/Divisi.');
    }

    return problems;
}

export function effectiveLevel(
    held: TeamCandidate['competencies'][number],
): number {
    return held.verified_level ?? held.self_level;
}

export function meetsRequirement(
    candidate: TeamCandidate,
    requirement: TeamRequirement,
): boolean {
    return candidate.competencies.some(
        (held) =>
            held.competency_id === requirement.competency_id &&
            effectiveLevel(held) >= (requirement.minimum_level ?? 0),
    );
}

/** Requirements this person does not cover. */
export function personGaps(
    candidate: TeamCandidate,
    requirements: TeamRequirement[],
): TeamRequirement[] {
    return requirements.filter(
        (requirement) => !meetsRequirement(candidate, requirement),
    );
}

/** Requirements nobody in the chosen team covers. */
export function teamGaps(
    picks: TeamPick[],
    candidates: Map<number, TeamCandidate>,
    requirements: TeamRequirement[],
): TeamRequirement[] {
    const members = picks
        .map((pick) => candidates.get(pick.user_id))
        .filter(
            (candidate): candidate is TeamCandidate => candidate !== undefined,
        );

    return requirements.filter(
        (requirement) =>
            !members.some((member) => meetsRequirement(member, requirement)),
    );
}

export function sameTeam(a: TeamPick[], b: TeamPick[]): boolean {
    const key = (picks: TeamPick[]) =>
        picks
            .map((pick) => `${pick.user_id}:${pick.role}`)
            .sort()
            .join('|');

    return key(a) === key(b);
}
