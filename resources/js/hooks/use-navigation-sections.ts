import { usePage } from '@inertiajs/react';
import {
    Activity,
    Award,
    BookOpenCheck,
    Building2,
    CalendarDays,
    ClipboardCheck,
    FileCheck2,
    FileText,
    FolderKanban,
    Gauge,
    History,
    Layers,
    LayoutGrid,
    ListChecks,
    ListTodo,
    MessageCircle,
    ShieldCheck,
    SlidersHorizontal,
    UserRound,
    UserRoundCog,
    Users,
    UsersRound,
} from 'lucide-react';
import { useMemo } from 'react';
import { dashboard } from '@/routes';
import { index as myCompetenciesIndex } from '@/routes/my-competencies';
import { index as directoryIndex } from '@/routes/admin/competency-directory';
import { index as proposalsIndex } from '@/routes/proposals';
import { index as auditIndex } from '@/routes/admin/audit';
import { index as batchesIndex } from '@/routes/admin/batches';
import { index as competenciesIndex } from '@/routes/admin/competencies';
import { index as organizationsIndex } from '@/routes/admin/organizations';
import { index as rolesIndex } from '@/routes/admin/roles';
import { index as rubricsIndex } from '@/routes/admin/rubrics';
import { index as usersIndex } from '@/routes/admin/users';
import { edit as profileEdit } from '@/routes/profile';
import type { NavSection, SidebarItem } from '@/types';

const sharedSections: NavSection[] = [
    {
        title: 'Utama',
        items: [{ title: 'Dashboard', href: dashboard(), icon: LayoutGrid }],
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
            items: [{ title: 'AI Scoring', icon: Activity }],
        },
        {
            title: 'Sistem',
            items: [{ title: 'Pengaturan', icon: SlidersHorizontal }],
        },
    ],
    'Owner QCC': [
        {
            title: 'Program',
            items: [
                { title: 'Monitoring Proyek', icon: Gauge },
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
                { title: 'Pembentukan Tim', icon: Users },
                { title: 'Proyek QCC', icon: FolderKanban },
                { title: 'Bi-weekly Report', icon: CalendarDays },
                { title: 'Mentoring', icon: MessageCircle },
                { title: 'Evidence Repository', icon: FileCheck2 },
            ],
        },
        {
            title: 'Penilaian',
            items: [
                { title: 'Review & Approval', icon: ClipboardCheck },
                { title: 'Penugasan Reviewer', icon: UserRoundCog },
                { title: 'AI Scoring', icon: Activity },
                { title: 'Rubrik', icon: Award },
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
                { title: 'Proyek Binaan', icon: FolderKanban },
                { title: 'Mentoring', icon: MessageCircle },
                { title: 'Bi-weekly Report', icon: CalendarDays },
                { title: 'Evidence Repository', icon: FileCheck2 },
                { title: 'Review & Approval', icon: ClipboardCheck },
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
                { title: 'Pembentukan Tim', icon: Users },
                { title: 'Proyek QCC', icon: FolderKanban },
                { title: 'Progress 8 Step QCC', icon: ListChecks },
            ],
        },
        {
            title: 'Aktivitas',
            items: [
                { title: 'Bi-weekly Report', icon: CalendarDays },
                { title: 'Mentoring', icon: MessageCircle },
                { title: 'Aksi & Risiko', icon: ListTodo },
                { title: 'Evidence Repository', icon: FileCheck2 },
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
                { title: 'Proyek QCC', icon: FolderKanban },
                { title: 'Progress 8 Step QCC', icon: ListChecks },
                { title: 'Evidence Repository', icon: FileCheck2 },
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
