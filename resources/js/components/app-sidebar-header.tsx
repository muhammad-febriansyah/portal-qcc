import { Link, router, usePage } from '@inertiajs/react';
import { formatDistanceToNow, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import { Bell, BellOff, Check, CheckCheck, ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { MenuSearch } from '@/components/menu-search';
import { NotificationKindIcon } from '@/components/notification-kind-icon';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Separator } from '@/components/ui/separator';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import {
    index as notificationsIndex,
    open as notificationOpen,
    read as notificationRead,
    readAll as notificationsReadAll,
} from '@/routes/notifications';
import { Badge } from '@/components/ui/badge';

function NotificationMenu() {
    const { notifications } = usePage().props;
    const [processing, setProcessing] = useState<string | null>(null);

    function markOneRead(id: string): void {
        setProcessing(id);
        router.post(
            notificationRead.url(id),
            {},
            {
                preserveScroll: true,
                only: ['notifications'],
                onFinish: () => setProcessing(null),
            },
        );
    }

    function markAllRead(): void {
        setProcessing('all');
        router.post(
            notificationsReadAll.url(),
            {},
            {
                preserveScroll: true,
                only: ['notifications'],
                onFinish: () => setProcessing(null),
            },
        );
    }

    return (
        <Sheet>
            <SheetTrigger
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
                {notifications.unread_count > 0 && (
                    <span className="absolute -top-0.5 -right-0.5 flex min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] leading-4 text-white">
                        {notifications.unread_count > 9
                            ? '9+'
                            : notifications.unread_count}
                    </span>
                )}
            </SheetTrigger>
            <SheetContent side="right" className="gap-0 p-0 sm:max-w-md">
                <SheetHeader className="border-b border-border/60 pr-12">
                    <div className="flex items-start justify-between gap-3">
                        <div className="space-y-1">
                            <SheetTitle>Notifikasi</SheetTitle>
                            <SheetDescription>
                                {notifications.unread_count} belum dibaca
                            </SheetDescription>
                        </div>
                        {notifications.unread_count > 0 && (
                            <Button
                                variant="secondary"
                                size="sm"
                                disabled={processing !== null}
                                onClick={markAllRead}
                            >
                                <CheckCheck data-icon="inline-start" />
                                Tandai semua dibaca
                            </Button>
                        )}
                    </div>
                </SheetHeader>
                {notifications.recent.length === 0 ? (
                    <Empty className="flex-1 gap-2 rounded-none border-0 px-6 py-8">
                        <EmptyHeader>
                            <EmptyMedia variant="icon">
                                <BellOff aria-hidden="true" />
                            </EmptyMedia>
                            <EmptyTitle>Belum ada notifikasi</EmptyTitle>
                            <EmptyDescription>
                                Pembaruan proposal, tugas, dan review akan
                                muncul di sini.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                ) : (
                    <ul className="flex-1 overflow-y-auto">
                        {notifications.recent.map((notification) => {
                            const createdAt = notification.created_at
                                ? parseISO(notification.created_at)
                                : null;

                            return (
                                <li
                                    key={notification.id}
                                    className={`border-b border-border/50 last:border-0 ${notification.read_at ? '' : 'bg-primary/[0.035]'}`}
                                >
                                    <div className="flex items-start gap-3 px-4 py-3">
                                        <span className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                            <NotificationKindIcon
                                                icon={notification.kind_icon}
                                                className="size-4"
                                            />
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <Link
                                                href={notificationOpen.url(
                                                    notification.id,
                                                )}
                                                className="block rounded-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            >
                                                <span className="flex items-center gap-2">
                                                    <span className="min-w-0 flex-1 truncate text-sm font-medium text-foreground">
                                                        {notification.title}
                                                    </span>
                                                    {!notification.read_at && (
                                                        <span
                                                            className="size-2 shrink-0 rounded-full bg-primary"
                                                            aria-label="Belum dibaca"
                                                        />
                                                    )}
                                                </span>
                                                <span className="mt-1 block text-xs leading-relaxed text-muted-foreground">
                                                    {notification.message}
                                                </span>
                                            </Link>
                                            <div className="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1">
                                                <Badge variant="secondary">
                                                    {notification.kind_label}
                                                </Badge>
                                                {notification.project_code && (
                                                    <span className="text-[11px] font-medium text-muted-foreground">
                                                        {
                                                            notification.project_code
                                                        }
                                                    </span>
                                                )}
                                                {createdAt && (
                                                    <span className="text-[11px] text-muted-foreground">
                                                        {formatDistanceToNow(
                                                            createdAt,
                                                            {
                                                                addSuffix: true,
                                                                locale: indonesianLocale,
                                                            },
                                                        )}
                                                    </span>
                                                )}
                                                {!notification.read_at && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="xs"
                                                        className="ml-auto"
                                                        disabled={
                                                            processing !== null
                                                        }
                                                        onClick={() =>
                                                            markOneRead(
                                                                notification.id,
                                                            )
                                                        }
                                                        aria-label={`Tandai ${notification.title} dibaca`}
                                                    >
                                                        <Check data-icon="inline-start" />
                                                        Tandai dibaca
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                )}
                <div className="border-t border-border/60 p-3">
                    <Button
                        variant="secondary"
                        className="w-full"
                        nativeButton={false}
                        render={<Link href={notificationsIndex()} />}
                    >
                        Lihat semua notifikasi
                    </Button>
                </div>
            </SheetContent>
        </Sheet>
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
                    <AvatarImage
                        src={auth.user.avatar ?? undefined}
                        alt={auth.user.name}
                    />
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
            <MenuSearch />

            <div className="ml-auto flex items-center gap-2">
                <NotificationMenu />
                <Separator orientation="vertical" className="mx-1 h-5!" />
                <UserMenu />
            </div>
        </header>
    );
}
