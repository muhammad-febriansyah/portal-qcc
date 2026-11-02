import { Link } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useAppName } from '@/hooks/use-app-name';
import { useNavigationSections } from '@/hooks/use-navigation-sections';
import { dashboard } from '@/routes';

export function AppSidebar() {
    const appName = useAppName();
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
                            className="h-10 group-data-[collapsible=icon]:p-0! hover:bg-transparent active:bg-transparent"
                            render={<Link href={dashboard()} prefetch />}
                            aria-label={`Dashboard ${appName}`}
                        >
                            <AppLogo />
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-0 py-3">
                <NavMain sections={sections} />
            </SidebarContent>
        </Sidebar>
    );
}
