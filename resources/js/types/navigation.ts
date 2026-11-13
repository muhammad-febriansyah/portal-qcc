import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
};

export type SidebarItem = {
    title: string;
    href?: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    /** Shown only to users holding this permission, or any one of these permissions. */
    permission?: string | string[];
    /** Hide the item when the user holds none of these roles (for example Super Admin only). */
    hiddenForOnlyRoles?: string[];
};

export type NavSection = {
    title: string;
    items: SidebarItem[];
};
