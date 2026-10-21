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
import WhatsAppIcon from '@/components/whatsapp-icon';
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

const developerWhatsappNumber = '6281295916567';

/**
 * Build a WhatsApp link with a prefilled support message so the developer
 * immediately knows who is asking and from which page.
 */
function buildDeveloperWhatsappUrl(
    userName: string | undefined,
    role: string | undefined,
    pageUrl: string,
): string {
    const sender = [userName, role ? `(${role})` : null]
        .filter(Boolean)
        .join(' ');

    const message = [
        'Halo Tim Developer Portal QCC,',
        '',
        `Saya ${sender || 'pengguna Portal QCC'} membutuhkan bantuan.`,
        '',
        `Halaman: ${pageUrl}`,
        'Kendala: ',
        '',
        'Terima kasih.',
    ].join('\n');

    return `https://wa.me/${developerWhatsappNumber}?text=${encodeURIComponent(message)}`;
}

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
    const { props, url } = usePage();
    const { auth } = props;
    const developerWhatsappUrl = buildDeveloperWhatsappUrl(
        auth.user?.name,
        auth.roles[0],
        url,
    );
    const userSections = auth.roles.flatMap((role) => roleSections[role] ?? []);
    const sections = mergeSections([...sharedSections, ...userSections]);

    return (
        <Sidebar
            collapsible="icon"
            variant="sidebar"
            className="border-sidebar-border/60"
        >
            <SidebarHeader className="h-16 justify-center border-b border-sidebar-border/60 px-3 group-data-[collapsible=icon]:px-2">
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

            <SidebarFooter className="p-3 group-data-[collapsible=icon]:p-2">
                <div className="rounded-xl bg-primary/[0.04] p-3.5 ring-1 ring-primary/10 group-data-[collapsible=icon]:hidden">
                    <div className="flex items-start gap-2.5">
                        <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-card text-primary ring-1 ring-primary/10">
                            <Headset className="size-4" aria-hidden="true" />
                        </span>
                        <div className="min-w-0 space-y-0.5">
                            <p className="text-[13px] leading-tight font-semibold text-foreground">
                                Butuh bantuan?
                            </p>
                            <p className="text-[11px] leading-snug text-muted-foreground">
                                Tim developer siap membantu kendala Anda.
                            </p>
                        </div>
                    </div>
                    <a
                        href={developerWhatsappUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="mt-3 flex h-9 w-full items-center justify-center gap-2 rounded-lg bg-[#25D366] text-[12px] font-semibold text-white transition-colors hover:bg-[#1EBE5B] focus-visible:ring-2 focus-visible:ring-[#25D366] focus-visible:ring-offset-2 focus-visible:outline-none"
                    >
                        <WhatsAppIcon className="size-4" />
                        Chat via WhatsApp
                    </a>
                </div>

                <SidebarMenu className="hidden group-data-[collapsible=icon]:flex">
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            className="text-[#25D366] hover:bg-[#25D366]/10 hover:text-[#1EBE5B]"
                            tooltip={{
                                children: 'Bantuan developer via WhatsApp',
                            }}
                            render={
                                <a
                                    href={developerWhatsappUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    aria-label="Bantuan developer via WhatsApp"
                                />
                            }
                        >
                            <WhatsAppIcon />
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarFooter>
        </Sidebar>
    );
}
