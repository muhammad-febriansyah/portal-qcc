import { usePage } from '@inertiajs/react';
import { Bell, BellOff, ChevronDown, Search } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Separator } from '@/components/ui/separator';
import { SidebarTrigger } from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';

function GlobalSearch() {
    return (
        <Tooltip>
            <TooltipTrigger
                render={
                    <button
                        type="button"
                        aria-disabled="true"
                        className="hidden h-10 w-full max-w-sm cursor-not-allowed items-center gap-2 rounded-lg border border-border/70 bg-background px-3 text-left text-[13px] text-muted-foreground md:flex"
                    />
                }
            >
                <Search className="size-4 shrink-0" aria-hidden="true" />
                <span className="flex-1 truncate">
                    Cari proyek, proposal, anggota
                </span>
                <kbd className="rounded border border-border bg-card px-1.5 font-sans text-[10px] font-medium">
                    Ctrl K
                </kbd>
            </TooltipTrigger>
            <TooltipContent>Pencarian global segera hadir</TooltipContent>
        </Tooltip>
    );
}

function NotificationMenu() {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                render={
                    <Button
                        variant="ghost"
                        size="icon"
                        className="relative size-10 rounded-full border border-border/70 bg-card text-foreground/70 hover:bg-muted hover:text-foreground data-popup-open:bg-muted data-popup-open:text-foreground"
                        aria-label="Notifikasi"
                    />
                }
            >
                <Bell className="size-5" aria-hidden="true" />
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" sideOffset={8} className="w-80 p-0">
                <div className="border-b border-border/60 px-4 py-3">
                    <p className="text-sm font-semibold">Notifikasi</p>
                </div>
                <div className="flex flex-col items-center gap-2 px-6 py-8 text-center">
                    <span className="flex size-10 items-center justify-center rounded-full bg-muted">
                        <BellOff
                            className="size-[18px] text-muted-foreground"
                            aria-hidden="true"
                        />
                    </span>
                    <p className="text-sm font-medium">Belum ada notifikasi</p>
                    <p className="text-xs text-muted-foreground">
                        Pembaruan proposal, review, dan mentoring akan muncul
                        di sini.
                    </p>
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function UserMenu() {
    const { auth } = usePage().props;
    const getInitials = useInitials();

    if (!auth.user) {
        return null;
    }

    const primaryRole = auth.roles[0];

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                render={
                    <button
                        type="button"
                        className="flex h-11 items-center gap-2.5 rounded-lg px-1.5 text-left transition-colors outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring data-popup-open:bg-muted"
                        data-test="sidebar-menu-button"
                    />
                }
            >
                <Avatar className="size-9">
                    <AvatarImage src={auth.user.avatar} alt={auth.user.name} />
                    <AvatarFallback className="bg-primary/10 text-xs font-semibold text-primary">
                        {getInitials(auth.user.name)}
                    </AvatarFallback>
                </Avatar>
                <span className="hidden min-w-0 flex-col leading-tight sm:flex">
                    <span className="max-w-40 truncate text-[13px] font-medium text-foreground">
                        {auth.user.name}
                    </span>
                    {primaryRole && (
                        <span className="max-w-40 truncate text-[11px] text-muted-foreground">
                            {primaryRole}
                        </span>
                    )}
                </span>
                <ChevronDown
                    className="hidden size-4 text-muted-foreground sm:block"
                    aria-hidden="true"
                />
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                sideOffset={8}
                className="w-60 rounded-lg"
            >
                <UserMenuContent user={auth.user} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function AppSidebarHeader() {
    return (
        <header className="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-3 border-b border-border/60 bg-card px-4 md:px-6">
            <SidebarTrigger className="-ml-1.5 size-9 text-muted-foreground hover:text-foreground" />
            <Separator orientation="vertical" className="h-5!" />
            <GlobalSearch />

            <div className="ml-auto flex items-center gap-2">
                <NotificationMenu />
                <Separator orientation="vertical" className="mx-1 h-5!" />
                <UserMenu />
            </div>
        </header>
    );
}
