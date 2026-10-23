import { Head, Link, router } from '@inertiajs/react';
import { format, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import {
    BrainCircuit,
    CalendarClock,
    ClipboardCheck,
    FileText,
    ListChecks,
    Search,
} from 'lucide-react';
import { useEffect, useState } from 'react';
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
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { dashboard } from '@/routes';
import { index as approvalIndex } from '@/routes/approval';

type Tab = 'proposals' | 'steps' | 'biweekly' | 'scoring';

type ReviewItem = {
    id: number;
    title: string;
    description: string;
    status: string;
    status_label: string;
    submitted_at: string | null;
    url: string;
};

type PaginationLink = { url: string | null; label: string; active: boolean };

type Props = {
    items: {
        data: ReviewItem[];
        current_page: number;
        from: number | null;
        to: number | null;
        last_page: number;
        total: number;
        links: PaginationLink[];
    };
    counts: Record<Tab, number>;
    availableTabs: Tab[];
    filters: { tab: Tab; search: string; per_page: number };
};

const tabs: { value: Tab; label: string; icon: typeof FileText }[] = [
    { value: 'proposals', label: 'Proposal QCC', icon: FileText },
    { value: 'steps', label: 'Progress 8 Step', icon: ListChecks },
    { value: 'biweekly', label: 'Bi-weekly Report', icon: CalendarClock },
    { value: 'scoring', label: 'AI Scoring', icon: BrainCircuit },
];

const statusVariants: Record<
    string,
    'info' | 'violet' | 'warning' | 'success' | 'destructive' | 'secondary'
> = {
    SUBMITTED: 'info',
    UNDER_REVIEW: 'violet',
    REVISION_REQUIRED: 'warning',
    APPROVED: 'success',
    REJECTED: 'destructive',
};

export default function ApprovalIndex({
    items,
    counts,
    availableTabs,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search);

    function visit(query: { tab?: Tab; search?: string; page?: number }): void {
        router.get(
            approvalIndex.url({
                query: {
                    tab: query.tab ?? filters.tab,
                    search: query.search ?? filters.search,
                    per_page: filters.per_page,
                    page: query.page ?? 1,
                },
            }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['items', 'counts', 'filters'],
            },
        );
    }

    useEffect(() => setSearch(filters.search), [filters.search]);
    useEffect(() => {
        if (search === filters.search) return;

        const timeout = window.setTimeout(() => visit({ search }), 350);
        return () => window.clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const visibleTabs = tabs.filter((tab) => availableTabs.includes(tab.value));
    const activeTab = visibleTabs.find((tab) => tab.value === filters.tab);
    const EmptyIcon = activeTab?.icon ?? ClipboardCheck;

    return (
        <>
            <Head title="Review & Approval" />
            <div className="mx-auto w-full max-w-[1440px] px-4 py-6 md:px-6 md:py-8">
                <PageHeader
                    breadcrumbs={[
                        { title: 'Dashboard', href: dashboard() },
                        { title: 'Review & Approval', href: approvalIndex() },
                    ]}
                    title="Review & Approval"
                    description="Tinjau proposal, progress 8 Step, laporan bi-weekly, dan hasil AI Scoring yang menunggu keputusan."
                />

                <Card className="gap-0 py-0">
                    <CardHeader className="gap-4 border-b py-4 sm:flex-row sm:items-center sm:justify-between">
                        <CardTitle className="text-base">
                            Daftar yang perlu ditinjau
                        </CardTitle>
                        <div className="relative w-full sm:max-w-sm">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                aria-label="Cari review"
                                className="h-9 pl-9"
                                placeholder="Cari judul, proyek, atau pengaju..."
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                            />
                        </div>
                    </CardHeader>

                    <CardContent className="p-0">
                        <div className="border-b px-4 py-3 sm:px-5">
                            <Tabs
                                value={filters.tab}
                                onValueChange={(value) =>
                                    visit({ tab: String(value) as Tab })
                                }
                            >
                                <TabsList className="h-auto w-full flex-wrap justify-start gap-1 bg-transparent p-0">
                                    {visibleTabs.map(
                                        ({ value, label, icon: Icon }) => (
                                            <TabsTrigger
                                                key={value}
                                                value={value}
                                                className="h-9 flex-none gap-2 rounded-lg border border-transparent px-3 aria-selected:border-border aria-selected:bg-background aria-selected:shadow-sm"
                                            >
                                                <Icon className="size-4" />
                                                {label}
                                                <Badge variant="secondary">
                                                    {counts[value]}
                                                </Badge>
                                            </TabsTrigger>
                                        ),
                                    )}
                                </TabsList>
                            </Tabs>
                        </div>

                        {items.data.length === 0 ? (
                            <Empty className="min-h-64 rounded-none px-6 py-12">
                                <EmptyHeader>
                                    <EmptyMedia variant="icon">
                                        <EmptyIcon />
                                    </EmptyMedia>
                                    <EmptyTitle>
                                        Tidak ada item yang menunggu review
                                    </EmptyTitle>
                                    <EmptyDescription>
                                        Item yang masuk untuk ditinjau akan
                                        tampil di daftar ini.
                                    </EmptyDescription>
                                </EmptyHeader>
                            </Empty>
                        ) : (
                            <ul className="divide-y">
                                {items.data.map((item) => (
                                    <li
                                        key={`${filters.tab}-${item.id}`}
                                        className="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:px-5"
                                    >
                                        <div className="grid min-w-0 flex-1 gap-1">
                                            <p className="truncate text-sm font-medium">
                                                {item.title}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {item.description}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Diajukan{' '}
                                                {item.submitted_at
                                                    ? format(
                                                          parseISO(
                                                              item.submitted_at,
                                                          ),
                                                          'd MMM yyyy, HH:mm',
                                                          {
                                                              locale: indonesianLocale,
                                                          },
                                                      )
                                                    : '—'}
                                            </p>
                                        </div>
                                        <Badge
                                            variant={
                                                statusVariants[item.status] ??
                                                'secondary'
                                            }
                                        >
                                            {item.status_label}
                                        </Badge>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            render={<Link href={item.url} />}
                                            nativeButton={false}
                                        >
                                            <ClipboardCheck data-icon="inline-start" />
                                            Buka review
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}

                        {items.last_page > 1 && (
                            <div className="flex flex-col gap-3 border-t px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                                <p className="text-sm text-muted-foreground">
                                    Menampilkan {items.from}–{items.to} dari{' '}
                                    {items.total} item
                                </p>
                                <nav
                                    aria-label="Navigasi halaman review"
                                    className="flex flex-wrap gap-2"
                                >
                                    {items.links.map((link, index) => (
                                        <Button
                                            key={`${link.label}-${index}`}
                                            variant={
                                                link.active
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            size="sm"
                                            disabled={!link.url}
                                            render={
                                                link.url ? (
                                                    <Link
                                                        href={link.url}
                                                        preserveScroll
                                                    />
                                                ) : undefined
                                            }
                                            nativeButton={!link.url}
                                        >
                                            {link.label
                                                .replace(/&laquo;|&raquo;/g, '')
                                                .trim()}
                                        </Button>
                                    ))}
                                </nav>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
