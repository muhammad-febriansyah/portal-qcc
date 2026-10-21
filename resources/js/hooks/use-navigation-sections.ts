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
} from 'lucide-react';
import { useMemo } from 'react';
import { dashboard } from '@/routes';
import type { NavSection } from '@/types';

const sharedSections: NavSection[] = [
    {
        title: 'Utama',
        items: [{ title: 'Dashboard', href: dashboard(), icon: LayoutGrid }],
    },
    {
        title: 'Akun Saya',
        items: [
            { title: 'Profil Saya', icon: UserRound },
            { title: 'Kompetensi Saya', icon: BookOpenCheck },
        ],
    },
];

const roleSections: Record<string, NavSection[]> = {
    'Super Admin': [
        {
            title: 'Pengguna & Akses',
            items: [
                { title: 'Anggota', icon: Users },
                { title: 'Role & Permission', icon: ShieldCheck },
                { title: 'Audit Log', icon: History },
            ],
        },
        {
            title: 'Data Master',
            items: [
                { title: 'Master Organisasi', icon: Building2 },
                { title: 'Master Kompetensi', icon: BookOpenCheck },
                { title: 'Batch', icon: Layers },
                { title: 'Rubrik', icon: Award },
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
                { title: 'Proposal QCC', icon: FileText },
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
            items: [{ title: 'Audit Log', icon: History }],
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
                { title: 'Proposal QCC', icon: FileText },
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
                { title: 'Proposal QCC', icon: FileText },
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
export function useNavigationSections(): NavSection[] {
    const { auth } = usePage().props;

    return useMemo(
        () =>
            mergeSections([
                ...sharedSections,
                ...auth.roles.flatMap((role) => roleSections[role] ?? []),
            ]),
        [auth.roles],
    );
}
