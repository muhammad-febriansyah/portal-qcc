import { Head, Link, router } from '@inertiajs/react';
import { formatDistanceToNow, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import { BellOff, Check, ChevronLeft, ChevronRight } from 'lucide-react';
import { NotificationKindIcon } from '@/components/notification-kind-icon';
import { PageHeader } from '@/components/page-header';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { dashboard } from '@/routes';
import {
    index as notificationsIndex,
    open as notificationOpen,
    read as notificationRead,
} from '@/routes/notifications';

type NotificationItem = {
    id: string;
    title: string;
    message: string;
    url: string;
    read_at: string | null;
    created_at: string | null;
    project_code: string | null;
    kind: string;
    kind_label: string;
    kind_icon: string;
};

type KindOption = { value: string; label: string };
type Filters = { status: 'all' | 'unread'; kind: string };

type Props = {
    items: {
        data: NotificationItem[];
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: Filters;
    groups: KindOption[];
};

export default function NotificationsIndex({ items, filters, groups }: Props) {
    function visit(next: Partial<Filters>, page = 1): void {
        router.get(
            notificationsIndex.url({
                query: {
                    status: next.status ?? filters.status,
                    kind: next.kind ?? filters.kind,
                    page,
                },
            }),
            {},
            { preserveScroll: true, preserveState: true, replace: true },
        );
    }

    const hasFilter = filters.status === 'unread' || filters.kind !== 'all';

    return (
        <>
            <Head title="Semua Notifikasi" />
            <div className="mx-auto w-full max-w-[1440px] px-4 py-6 md:px-6 md:py-8">
                <PageHeader
                    breadcrumbs={[
                        { title: 'Dashboard', href: dashboard() },
                        {
                            title: 'Semua Notifikasi',
                            href: notificationsIndex(),
                        },
                    ]}
                    title="Semua Notifikasi"
                    description="Pembaruan proposal, tugas, dan review untuk akun Anda."
                />

                <Card className="gap-0 py-0">
                    <div className="flex flex-col gap-3 border-b border-border/60 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <Tabs
                            value={filters.status}
                            onValueChange={(value) =>
                                visit({ status: value as Filters['status'] })
                            }
                        >
                            <TabsList aria-label="Filter status notifikasi">
                                <TabsTrigger value="all">Semua</TabsTrigger>
                                <TabsTrigger value="unread">
                                    Belum dibaca
                                </TabsTrigger>
                            </TabsList>
                        </Tabs>
                        <Select
                            value={filters.kind}
                            onValueChange={(value) =>
                                visit({ kind: value ?? 'all' })
                            }
                            items={[
                                { value: 'all', label: 'Semua jenis' },
                                ...groups,
                            ]}
                        >
                            <SelectTrigger
                                aria-label="Filter jenis notifikasi"
                                className="w-full sm:w-56"
                            >
                                <SelectValue placeholder="Semua jenis" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Semua jenis</SelectItem>
                                {groups.map((group) => (
                                    <SelectItem
                                        key={group.value}
                                        value={group.value}
                                    >
                                        {group.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {items.data.length === 0 ? (
                        <Empty className="min-h-72 rounded-none border-0 px-6 py-12">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <BellOff aria-hidden="true" />
                                </EmptyMedia>
                                <EmptyTitle>
                                    {hasFilter
                                        ? 'Tidak ada notifikasi yang cocok'
                                        : 'Belum ada notifikasi'}
                                </EmptyTitle>
                                <EmptyDescription>
                                    {hasFilter
                                        ? 'Coba ubah filter status atau jenis notifikasi.'
                                        : 'Pembaruan proposal, tugas, dan review akan muncul di sini.'}
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <ul className="divide-y divide-border/60">
                            {items.data.map((notification) => {
                                const createdAt = notification.created_at
                                    ? parseISO(notification.created_at)
                                    : null;

                                return (
                                    <li
                                        key={notification.id}
                                        className={
                                            notification.read_at
                                                ? ''
                                                : 'bg-primary/[0.035]'
                                        }
                                    >
                                        <div className="flex items-start gap-3 px-4 py-4 sm:px-5">
                                            <span className="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                                <NotificationKindIcon
                                                    icon={
                                                        notification.kind_icon
                                                    }
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
                                                        <span className="min-w-0 flex-1 text-sm font-semibold text-foreground">
                                                            {notification.title}
                                                        </span>
                                                        {!notification.read_at && (
                                                            <span
                                                                className="size-2 shrink-0 rounded-full bg-primary"
                                                                aria-label="Belum dibaca"
                                                            />
                                                        )}
                                                    </span>
                                                    <span className="mt-1 block text-sm leading-relaxed text-muted-foreground">
                                                        {notification.message}
                                                    </span>
                                                </Link>
                                                <div className="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1.5">
                                                    <Badge variant="secondary">
                                                        {
                                                            notification.kind_label
                                                        }
                                                    </Badge>
                                                    {notification.project_code && (
                                                        <span className="text-xs font-medium text-muted-foreground">
                                                            {
                                                                notification.project_code
                                                            }
                                                        </span>
                                                    )}
                                                    {createdAt && (
                                                        <span className="text-xs text-muted-foreground">
                                                            {formatDistanceToNow(
                                                                createdAt,
                                                                {
                                                                    addSuffix: true,
                                                                    locale: indonesianLocale,
                                                                },
                                                            )}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                            {!notification.read_at && (
                                                <Button
                                                    type="button"
                                                    variant="secondary"
                                                    size="sm"
                                                    className="shrink-0"
                                                    onClick={() =>
                                                        router.post(
                                                            notificationRead.url(
                                                                notification.id,
                                                            ),
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                                only: [
                                                                    'items',
                                                                    'notifications',
                                                                ],
                                                            },
                                                        )
                                                    }
                                                    aria-label={`Tandai ${notification.title} dibaca`}
                                                >
                                                    <Check data-icon="inline-start" />
                                                    <span className="hidden sm:inline">
                                                        Tandai dibaca
                                                    </span>
                                                </Button>
                                            )}
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    )}

                    {items.total > 0 && (
                        <CardContent className="flex flex-col gap-3 border-t border-border/60 py-3 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                            <span>
                                Menampilkan {items.from ?? 0}–{items.to ?? 0}{' '}
                                dari {items.total}
                            </span>
                            <div className="flex items-center justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    disabled={items.current_page <= 1}
                                    onClick={() =>
                                        visit({}, items.current_page - 1)
                                    }
                                >
                                    <ChevronLeft data-icon="inline-start" />
                                    Sebelumnya
                                </Button>
                                <span className="px-1 text-xs">
                                    Halaman {items.current_page} dari{' '}
                                    {items.last_page}
                                </span>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    disabled={
                                        items.current_page >= items.last_page
                                    }
                                    onClick={() =>
                                        visit({}, items.current_page + 1)
                                    }
                                >
                                    Berikutnya
                                    <ChevronRight data-icon="inline-end" />
                                </Button>
                            </div>
                        </CardContent>
                    )}
                </Card>
            </div>
        </>
    );
}
