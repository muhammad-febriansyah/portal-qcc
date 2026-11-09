export type ProgramPhaseKey = 'registration' | 'selection' | 'execution';

export type ProgramPhase = {
    key: ProgramPhaseKey;
    number: number;
    label: string;
    starts_on: string | null;
    ends_on: string | null;
    state: 'done' | 'active' | 'upcoming';
    /** Registration is always readable; later phases open on their start date. */
    viewable: boolean;
};

export type ProgramBatch = {
    id: number;
    name: string;
    starts_on: string;
    ends_on: string;
};

export type RegistrationPhaseData = {
    participant_target: number | null;
    participants: number;
    registered: number;
    competency_completed: number;
    competency_completed_percent: number;
    proposals_submitted: number;
    submitters: number;
    submitters_percent: number | null;
    organizations_count: number;
    submissions_by_organization: { name: string; count: number }[];
    top_competencies: { name: string; count: number; percent: number }[];
    themes: { key: string; label: string; count: number; percent: number }[];
    trend: { date: string; registered: number; submitted: number }[];
};

export type SelectionPhaseData = {
    total: number;
    passed: number;
    passed_percent: number;
    failed: number;
    failed_percent: number;
    in_selection: number;
    teams_formed: number;
    teams_formed_percent: number;
    teams_pending: number;
    teams_pending_percent: number;
    funnel: { key: string; label: string; count: number }[];
    passed_by_organization: { name: string; count: number }[];
    matching: { key: string; label: string; count: number; percent: number }[];
    team_sizes: {
        size: number;
        label: string;
        count: number;
        percent: number;
    }[];
};

export type ExecutionProjectRow = {
    id: number;
    code: string;
    title: string;
    organization: string | null;
    leader: string | null;
    current_step: { number: number; label: string } | null;
    steps: ('done' | 'current' | 'todo')[];
    status: 'on_time' | 'overdue';
};

export type ExecutionPhaseData = {
    total: number;
    average_progress: number;
    biweekly: { compliant: number; late: number; percent: number };
    mentoring: { compliant: number; late: number; percent: number };
    on_time: { count: number; overdue: number; percent: number };
    steps: {
        number: number;
        label: string;
        average_progress: number;
        projects_started: number;
    }[];
    /** `own` when the viewer (a Team Leader) only gets their own projects listed. */
    projects_scope: 'all' | 'own';
    projects: ExecutionProjectRow[];
};

export type ProgramOverview = {
    batch: ProgramBatch;
    phases: ProgramPhase[];
    active_phase: ProgramPhaseKey | null;
    /** Null when the viewer may not open the registration phase. */
    registration: RegistrationPhaseData | null;
    selection: SelectionPhaseData | null;
    execution: ExecutionPhaseData | null;
};
