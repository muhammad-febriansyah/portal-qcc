import { usePage } from '@inertiajs/react';
import {
    Activity,
    Award,
    Bell,
    BookOpenCheck,
    Building2,
    CalendarDays,
    ClipboardCheck,
    FileCheck2,
    FileText,
    FolderKanban,
    History,
    Layers,
    LayoutGrid,
    ListChecks,
    ListTodo,
    MessageCircle,
    ShieldCheck,
    SlidersHorizontal,
    UserRound,
    Users,
    UsersRound,
    UserRoundCog,
} from 'lucide-react';
import { useMemo } from 'react';
import { dashboard } from '@/routes';
import { index as notificationsIndex } from '@/routes/notifications';
import { index as myCompetenciesIndex } from '@/routes/my-competencies';
import { index as directoryIndex } from '@/routes/admin/competency-directory';
import { index as proposalsIndex } from '@/routes/proposals';
import { index as approvalIndex } from '@/routes/approval';
import { index as auditIndex } from '@/routes/admin/audit';
import { index as batchesIndex } from '@/routes/admin/batches';
import { index as competenciesIndex } from '@/routes/admin/competencies';
import { index as organizationsIndex } from '@/routes/admin/organizations';
import { index as rolesIndex } from '@/routes/admin/roles';
import { index as rubricsIndex } from '@/routes/admin/rubrics';
import { index as scoringIndex } from '@/routes/admin/scoring';
import { index as systemSettingsIndex } from '@/routes/admin/settings';
import { index as usersIndex } from '@/routes/admin/users';
import { index as reviewerAssignmentsIndex } from '@/routes/admin/reviewer-assignments';
import { index as evidenceRepositoryIndex } from '@/routes/evidence-repository';
import { index as biweeklyDirectoryIndex } from '@/routes/biweekly-directory';
import { index as mentoringDirectoryIndex } from '@/routes/mentoring-directory';
import { index as riskDirectoryIndex } from '@/routes/risk-directory';
import { index as teamFormationIndex } from '@/routes/team-formation';
import { index as projectStepsIndex } from '@/routes/project-steps';
import { index as qccProjectsIndex } from '@/routes/qcc-projects';
import { edit as profileEdit } from '@/routes/profile';
import type { NavSection, SidebarItem } from '@/types';

const sharedSections: NavSection[] = [
    {
        title: 'Utama',
        items: [
            { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
            { title: 'Notifikasi', href: notificationsIndex(), icon: Bell },
        ],
    },
    {
        title: 'Akun Saya',
        items: [
            { title: 'Profil Saya', href: profileEdit(), icon: UserRound },
            {
                title: 'Kompetensi Saya',
                href: myCompetenciesIndex(),
                icon: BookOpenCheck,
                hiddenForOnlyRoles: ['Super Admin'],
            },
        ],
    },
];

const roleSections: Record<string, NavSection[]> = {
    'Super Admin': [
        {
            title: 'Pengguna & Akses',
            items: [
                {
                    title: 'Anggota',
                    href: usersIndex(),
                    icon: Users,
                    permission: 'users.manage',
                },
                {
                    title: 'Role & Permission',
                    href: rolesIndex(),
                    icon: ShieldCheck,
                    permission: 'roles.manage',
                },
                {
                    title: 'Audit Log',
                    href: auditIndex(),
                    icon: History,
                    permission: 'audit.view',
                },
                {
                    title: 'Inbox Review',
                    href: approvalIndex(),
                    icon: ClipboardCheck,
                    permission: 'projects.review',
                },
            ],
        },
        {
            title: 'Data Master',
            items: [
                {
                    title: 'Master Organisasi',
                    href: organizationsIndex(),
                    icon: Building2,
                    permission: 'organizations.manage',
                },
                {
                    title: 'Master Kompetensi',
                    href: competenciesIndex(),
                    icon: BookOpenCheck,
                    permission: 'competencies.manage',
                },
                {
                    title: 'Direktori Kompetensi',
                    href: directoryIndex(),
                    icon: UsersRound,
                    permission: 'people.report',
                },
                {
                    title: 'Batch',
                    href: batchesIndex(),
                    icon: Layers,
                    permission: 'batches.manage',
                },
                {
                    title: 'Rubrik',
                    href: rubricsIndex(),
                    icon: Award,
                    permission: 'rubrics.manage',
                },
            ],
        },
        {
            title: 'Penilaian',
            items: [
                {
                    title: 'AI Scoring',
                    href: scoringIndex(),
                    icon: Activity,
                    permission: 'scoring.view',
                },
            ],
        },
        {
            title: 'Sistem',
            items: [
                {
                    title: 'Pengaturan Sistem',
                    href: systemSettingsIndex(),
                    icon: SlidersHorizontal,
                    permission: 'system-settings.view',
                },
            ],
        },
    ],
    'Owner QCC': [
        {
            title: 'Program',
            items: [
                {
                    title: 'Direktori Kompetensi',
                    href: directoryIndex(),
                    icon: UsersRound,
                    permission: 'people.report',
                },
                {
                    title: 'Proposal QCC',
                    href: proposalsIndex(),
                    icon: FileText,
                    permission: 'projects.view',
                },
                {
                    title: 'Pembentukan Tim',
                    href: teamFormationIndex(),
                    icon: Users,
                    permission: 'projects.view',
                },
                {
                    title: 'Proyek QCC',
                    href: qccProjectsIndex(),
                    icon: FolderKanban,
                },
                {
                    title: 'Penugasan Reviewer',
                    href: reviewerAssignmentsIndex(),
                    icon: UserRoundCog,
                    permission: 'review-assignments.manage',
                },
                {
                    title: 'Progress 8 Step QCC',
                    href: projectStepsIndex(),
                    icon: ListChecks,
                },
                {
                    title: 'Bi-weekly Report',
                    href: biweeklyDirectoryIndex(),
                    icon: CalendarDays,
                    permission: 'projects.view',
                },
                {
                    title: 'Mentoring',
                    href: mentoringDirectoryIndex(),
                    icon: MessageCircle,
                    permission: 'projects.view',
                },
                {
                    title: 'Aksi & Risiko',
                    href: riskDirectoryIndex(),
                    icon: ListTodo,
                    permission: 'projects.view',
                },
                {
                    title: 'Evidence Repository',
                    href: evidenceRepositoryIndex(),
                    icon: FileCheck2,
                    permission: 'evidence.view',
                },
            ],
        },
        {
            title: 'Penilaian',
            items: [
                {
                    title: 'Review & Approval',
                    href: approvalIndex(),
                    icon: ClipboardCheck,
                    permission: 'projects.review',
                },
                {
                    title: 'AI Scoring',
                    href: scoringIndex(),
                    icon: Activity,
                    permission: 'scoring.view',
                },
                {
                    title: 'Rubrik',
                    href: rubricsIndex(),
                    icon: Award,
                    permission: 'rubrics.manage',
                },
            ],
        },
        {
            title: 'Pengawasan',
            items: [
                {
                    title: 'Audit Log',
                    href: auditIndex(),
                    icon: History,
                    permission: 'audit.view',
                },
            ],
        },
    ],
    Mentor: [
        {
            title: 'Pendampingan',
            items: [
                {
                    title: 'Proyek Binaan',
                    href: qccProjectsIndex(),
                    icon: FolderKanban,
                    permission: 'projects.view',
                },
                {
                    title: 'Mentoring',
                    href: mentoringDirectoryIndex(),
                    icon: MessageCircle,
                    permission: 'projects.view',
                },
                {
                    title: 'Bi-weekly Report',
                    href: biweeklyDirectoryIndex(),
                    icon: CalendarDays,
                    permission: 'projects.view',
                },
                {
                    title: 'Aksi & Risiko',
                    href: riskDirectoryIndex(),
                    icon: ListTodo,
                    permission: 'projects.view',
                },
                {
                    title: 'Evidence Repository',
                    href: evidenceRepositoryIndex(),
                    icon: FileCheck2,
                    permission: 'evidence.view',
                },
                {
                    title: 'Review & Approval',
                    href: approvalIndex(),
                    icon: ClipboardCheck,
                    permission: 'projects.view',
                },
            ],
        },
    ],
    'Team Leader': [
        {
            title: 'Proyek',
            items: [
                {
                    title: 'Proposal QCC',
                    href: proposalsIndex(),
                    icon: FileText,
                    permission: 'projects.view',
                },
                {
                    title: 'Pembentukan Tim',
                    href: teamFormationIndex(),
                    icon: Users,
                    permission: 'projects.view',
                },
                {
                    title: 'Proyek QCC',
                    href: qccProjectsIndex(),
                    icon: FolderKanban,
                },
                {
                    title: 'Progress 8 Step QCC',
                    href: projectStepsIndex(),
                    icon: ListChecks,
                },
            ],
        },
        {
            title: 'Aktivitas',
            items: [
                {
                    title: 'Bi-weekly Report',
                    href: biweeklyDirectoryIndex(),
                    icon: CalendarDays,
                    permission: 'projects.view',
                },
                {
                    title: 'Mentoring',
                    href: mentoringDirectoryIndex(),
                    icon: MessageCircle,
                    permission: 'projects.view',
                },
                {
                    title: 'Aksi & Risiko',
                    href: riskDirectoryIndex(),
                    icon: ListTodo,
                    permission: 'projects.view',
                },
                {
                    title: 'Evidence Repository',
                    href: evidenceRepositoryIndex(),
                    icon: FileCheck2,
                    permission: 'evidence.view',
                },
            ],
        },
    ],
    'SIMer / Member': [
        {
            title: 'Proyek',
            items: [
                {
                    title: 'Proposal QCC',
                    href: proposalsIndex(),
                    icon: FileText,
                    permission: 'projects.view',
                },
                {
                    title: 'Proyek QCC',
                    href: qccProjectsIndex(),
                    icon: FolderKanban,
                },
                {
                    title: 'Progress 8 Step QCC',
                    href: projectStepsIndex(),
                    icon: ListChecks,
                },
                {
                    title: 'Evidence Repository',
                    href: evidenceRepositoryIndex(),
                    icon: FileCheck2,
                    permission: 'evidence.view',
                },
            ],
        },
    ],
};

/**
 * Combine sections that share a title and show each menu item only once,
 * so users with several roles do not see duplicate groups or items.
 */
function mergeSections(sections: NavSection[]): NavSection[] {
    const merged = new Map<string, NavSection>();
    const seenItemTitles = new Set<string>();

    for (const section of sections) {
        const newItems = section.items.filter(
            (item) => !seenItemTitles.has(item.title),
        );

        if (newItems.length === 0) {
            continue;
        }

        newItems.forEach((item) => seenItemTitles.add(item.title));

        const existing = merged.get(section.title);

        if (existing) {
            existing.items.push(...newItems);
        } else {
            merged.set(section.title, { ...section, items: newItems });
        }
    }

    return [...merged.values()];
}

/**
 * Navigation sections the signed-in user may see, based on their roles.
 * Shared by the sidebar and the menu search so both show the same items.
 */
/**
 * An item marked `hiddenForOnlyRoles` is hidden when every role of the user is in
 * that list, so someone who is also a participant (for example Super Admin and
 * Team Leader) still sees it.
 */
function isHiddenForRoles(item: SidebarItem, roles: string[]): boolean {
    return (
        item.hiddenForOnlyRoles !== undefined &&
        roles.length > 0 &&
        roles.every((role) => item.hiddenForOnlyRoles?.includes(role))
    );
}

export function useNavigationSections(): NavSection[] {
    const { auth } = usePage().props;

    return useMemo(
        () =>
            mergeSections([
                ...sharedSections,
                ...auth.roles.flatMap((role) => roleSections[role] ?? []),
            ])
                .map((section) => ({
                    ...section,
                    items: section.items.filter(
                        (item) =>
                            (!item.permission ||
                                auth.permissions.includes(item.permission)) &&
                            !isHiddenForRoles(item, auth.roles),
                    ),
                }))
                .filter((section) => section.items.length > 0),
        [auth.permissions, auth.roles],
    );
}
