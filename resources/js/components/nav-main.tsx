import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavSection } from '@/types';

const menuButtonClassName =
    'h-10 gap-3 rounded-lg px-3 text-[13px] text-foreground/80 hover:text-foreground data-active:bg-primary data-active:font-medium data-active:text-primary-foreground data-active:shadow-[0_6px_16px_-4px_rgba(37,71,249,0.5)] data-active:hover:bg-primary data-active:hover:text-primary-foreground [&_svg]:text-muted-foreground data-active:[&_svg]:text-primary-foreground';

export function NavMain({ sections }: { sections: NavSection[] }) {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <nav aria-label="Navigasi utama" className="flex flex-col gap-4">
            {sections.map((section) => (
                <SidebarGroup className="px-3 py-0" key={section.title}>
                    <SidebarGroupLabel className="h-7 px-2.5 text-[11px] font-medium tracking-wider text-muted-foreground uppercase">
                        {section.title}
                    </SidebarGroupLabel>
                    <SidebarMenu className="gap-0.5">
                        {section.items.map((item) => {
                            const isActive = item.href
                                ? isCurrentUrl(item.href)
                                : false;
                            const content = (
                                <>
                                    {item.icon && <item.icon />}
                                    <span>{item.title}</span>
                                </>
                            );

                            return (
                                <SidebarMenuItem key={item.title}>
                                    {item.href ? (
                                        <SidebarMenuButton
                                            className={menuButtonClassName}
                                            render={
                                                <Link
                                                    href={item.href}
                                                    prefetch
                                                    aria-current={
                                                        isActive
                                                            ? 'page'
                                                            : undefined
                                                    }
                                                >
                                                    {content}
                                                </Link>
                                            }
                                            isActive={isActive}
                                            tooltip={{ children: item.title }}
                                        />
                                    ) : (
                                        <SidebarMenuButton
                                            className={menuButtonClassName}
                                            tooltip={{ children: item.title }}
                                        >
                                            {content}
                                        </SidebarMenuButton>
                                    )}
                                </SidebarMenuItem>
                            );
                        })}
                    </SidebarMenu>
                </SidebarGroup>
            ))}
        </nav>
    );
}
