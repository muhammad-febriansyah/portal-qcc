import { Link, usePage } from '@inertiajs/react';
import { Headset } from 'lucide-react';
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
import { useNavigationSections } from '@/hooks/use-navigation-sections';
import { dashboard } from '@/routes';

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

export function AppSidebar() {
    const { props, url } = usePage();
    const { auth } = props;
    const developerWhatsappUrl = buildDeveloperWhatsappUrl(
        auth.user?.name,
        auth.roles[0],
        url,
    );
    const sections = useNavigationSections();

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
