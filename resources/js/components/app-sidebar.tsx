import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    Award,
    BookOpenCheck,
    BriefcaseBusiness,
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
    NotebookTabs,
    ShieldCheck,
    SlidersHorizontal,
    UserRound,
    UserRoundCog,
    Users,
    Headset,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavSection } from '@/types';

const developerWhatsappUrl = 'https://wa.me/6281295916567';

const sharedSections: NavSection[] = [
    {
        title: 'Utama',
        items: [{ title: 'Dashboard', href: dashboard(), icon: LayoutGrid }],
    },
    {
        title: 'Akun Saya',
        items: [
            { title: 'Profil', icon: UserRound },
            { title: 'Kompetensi Saya', icon: BookOpenCheck },
        ],
    },
];

const roleSections: Record<string, NavSection[]> = {
    'Super Admin': [
        {
            title: 'Pengguna & Akses',
            items: [
                { title: 'Pengguna', icon: Users },
                { title: 'Role & Izin', icon: ShieldCheck },
                { title: 'Log Aktivitas', icon: History },
            ],
        },
        {
            title: 'Data Master',
            items: [
                { title: 'Organisasi', icon: Building2 },
                { title: 'Kompetensi', icon: BookOpenCheck },
                { title: 'Batch', icon: Layers },
                { title: 'Rubrik Penilaian', icon: Award },
            ],
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
                { title: 'Proposal', icon: FileText },
                { title: 'Tim', icon: Users },
                { title: 'Penugasan Reviewer', icon: UserRoundCog },
            ],
        },
        {
            title: 'Penilaian',
            items: [
                { title: 'Persetujuan', icon: ClipboardCheck },
                { title: 'Bi-weekly & Mentoring', icon: CalendarDays },
                { title: 'AI Scoring', icon: Activity },
            ],
        },
    ],
    Mentor: [
        {
            title: 'Pendampingan',
            items: [
                { title: 'Proyek Binaan', icon: FolderKanban },
                { title: 'Sesi Mentoring', icon: MessageCircle },
                { title: 'Bi-weekly', icon: CalendarDays },
                { title: 'Evidence', icon: FileCheck2 },
                { title: 'Perlu Review', icon: ClipboardCheck },
            ],
        },
    ],
    'Team Leader': [
        {
            title: 'Proyek',
            items: [
                { title: 'Proposal', icon: FileText },
                { title: 'Tim', icon: Users },
                { title: 'Proyek QCC', icon: FolderKanban },
                { title: '8 Step QCC', icon: ListChecks },
            ],
        },
        {
            title: 'Aktivitas',
            items: [
                { title: 'Bi-weekly', icon: CalendarDays },
                { title: 'Laporan Mentoring', icon: NotebookTabs },
                { title: 'Aksi & Risiko', icon: ListTodo },
                { title: 'Evidence', icon: FileCheck2 },
            ],
        },
    ],
    'SIMer / Member': [
        {
            title: 'Proyek',
            items: [
                { title: 'Proposal', icon: FileText },
                { title: 'Tim & Proyek', icon: BriefcaseBusiness },
                { title: '8 Step QCC', icon: ListChecks },
                { title: 'Evidence', icon: FileCheck2 },
            ],
        },
    ],
};

/**
 * Combine sections that share a title so users with several roles
 * do not see duplicate groups or menu items.
 */
function mergeSections(sections: NavSection[]): NavSection[] {
    const merged = new Map<string, NavSection>();

    for (const section of sections) {
        const existing = merged.get(section.title);

        if (!existing) {
            merged.set(section.title, {
                ...section,
                items: [...section.items],
            });

            continue;
        }

        for (const item of section.items) {
            if (
                !existing.items.some((current) => current.title === item.title)
            ) {
                existing.items.push(item);
            }
        }
    }

    return [...merged.values()];
}

export function AppSidebar() {
    const { auth } = usePage().props;
    const userSections = auth.roles.flatMap((role) => roleSections[role] ?? []);
    const sections = mergeSections([...sharedSections, ...userSections]);

    return (
        <Sidebar collapsible="icon" variant="sidebar">
            <SidebarHeader className="h-16 justify-center border-b border-sidebar-border px-3 group-data-[collapsible=icon]:px-2">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            className="h-10 hover:bg-transparent active:bg-transparent group-data-[collapsible=icon]:p-0!"
                            render={<Link href={dashboard()} prefetch />}
                            aria-label="Dashboard Portal QCC"
                        >
                            <AppLogo />
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-0 py-3">
                <NavMain sections={sections} />
            </SidebarContent>

            <SidebarFooter className="border-t border-sidebar-border p-3 group-data-[collapsible=icon]:p-2">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            className="h-auto items-start gap-3 rounded-lg p-2.5 hover:bg-muted group-data-[collapsible=icon]:items-center"
                            tooltip={{
                                children: 'Bantuan developer via WhatsApp',
                            }}
                            render={
                                <a
                                    href={developerWhatsappUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                />
                            }
                        >
                            <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/8 text-primary group-data-[collapsible=icon]:size-4 group-data-[collapsible=icon]:bg-transparent">
                                <Headset aria-hidden="true" />
                            </span>
                            <span className="flex min-w-0 flex-col gap-0.5 leading-tight">
                                <span className="text-[13px] font-medium text-foreground">
                                    Butuh bantuan?
                                </span>
                                <span className="text-[11px] text-muted-foreground">
                                    WhatsApp developer
                                </span>
                                <span className="text-[12px] font-medium text-primary">
                                    0812-9591-6567
                                </span>
                            </span>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarFooter>
        </Sidebar>
    );
}
