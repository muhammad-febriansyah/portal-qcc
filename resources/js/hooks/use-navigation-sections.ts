import { usePage } from '@inertiajs/react';
import {
    BookOpenCheck,
    Building2,
    ClipboardCheck,
    FileText,
    FolderKanban,
    History,
    Layers,
    LayoutGrid,
    ShieldCheck,
    SlidersHorizontal,
    Users,
    UsersRound,
} from 'lucide-react';
import { useMemo } from 'react';
import { dashboard } from '@/routes';
import { index as myCompetenciesIndex } from '@/routes/my-competencies';
import { index as directoryIndex } from '@/routes/admin/competency-directory';
import { index as proposalsIndex } from '@/routes/proposals';
import { index as approvalIndex } from '@/routes/approval';
import { index as auditIndex } from '@/routes/admin/audit';
import { index as batchesIndex } from '@/routes/admin/batches';
import { index as competenciesIndex } from '@/routes/admin/competencies';
import { index as organizationsIndex } from '@/routes/admin/organizations';
import { index as rolesIndex } from '@/routes/admin/roles';
import { index as systemSettingsIndex } from '@/routes/admin/settings';
import { index as usersIndex } from '@/routes/admin/users';
import { index as qccProjectsIndex } from '@/routes/qcc-projects';
import type { NavSection, SidebarItem } from '@/types';

const REVIEW_PERMISSIONS = ['projects.review', 'projects.approve'];

const sharedSections: NavSection[] = [
    {
        title: 'Utama',
        items: [{ title: 'Dashboard', href: dashboard(), icon: LayoutGrid }],
    },
    {
        title: 'Data Saya',
        items: [
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
    'Super Admin': [],
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
                    title: 'Proyek QCC',
                    href: qccProjectsIndex(),
                    icon: FolderKanban,
                    permission: 'projects.view',
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
                    permission: REVIEW_PERMISSIONS,
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
            ],
        },
        {
            title: 'Review',
            items: [
                {
                    title: 'Review & Approval',
                    href: approvalIndex(),
                    icon: ClipboardCheck,
                    permission: REVIEW_PERMISSIONS,
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
                    title: 'Proyek QCC',
                    href: qccProjectsIndex(),
                    icon: FolderKanban,
                    permission: 'projects.view',
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
                    permission: 'projects.view',
                },
            ],
        },
    ],
};

/**
 * Administration menus, shown to whoever holds their permission, whatever their role.
 * Giving a role one of these permissions in Role & Permission brings the menu along;
 * taking it away removes the menu. A role's own sections above decide where an item
 * sits when it appears there too.
 */
const permissionSections: NavSection[] = [
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
];

function itemKey(item: SidebarItem): string {
    if (item.href === undefined) {
        return item.title;
    }

    return typeof item.href === 'string' ? item.href : item.href.url;
}

function hasPermission(item: SidebarItem, permissions: string[]): boolean {
    if (item.permission === undefined) {
        return true;
    }

    const required = Array.isArray(item.permission)
        ? item.permission
        : [item.permission];

    return required.some((permission) => permissions.includes(permission));
}

/**
 * Combine sections that share a title and show each menu item only once,
 * so users with several roles do not see duplicate groups or items.
 */
function mergeSections(sections: NavSection[]): NavSection[] {
    const merged = new Map<string, NavSection>();
    const seenItems = new Set<string>();

    for (const section of sections) {
        const newItems = section.items.filter(
            (item) => !seenItems.has(itemKey(item)),
        );

        if (newItems.length === 0) {
            continue;
        }

        newItems.forEach((item) => seenItems.add(itemKey(item)));

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
            mergeSections(
                [
                    ...sharedSections,
                    ...auth.roles.flatMap((role) => roleSections[role] ?? []),
                    ...permissionSections,
                ].map((section) => ({
                    ...section,
                    // Filter before merging, so an item the user may not open never
                    // hides the same link offered elsewhere under a permission they hold.
                    items: section.items.filter(
                        (item) =>
                            hasPermission(item, auth.permissions) &&
                            !isHiddenForRoles(item, auth.roles),
                    ),
                })),
            )
                .filter((section) => section.items.length > 0),
        [auth.permissions, auth.roles],
    );
}
