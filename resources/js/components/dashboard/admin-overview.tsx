import { Link } from '@inertiajs/react';
import { formatDistanceToNow, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import {
    ArrowDownRight,
    ArrowRight,
    ArrowUpRight,
    BookOpenCheck,
    Layers,
    Minus,
    Users,
    Zap,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { lazy, Suspense, useEffect, useState } from 'react';
import type { ActivitySeries } from '@/components/charts/activity-chart';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { index as auditIndex } from '@/routes/admin/audit';

const ActivityChart = lazy(() => import('@/components/charts/activity-chart'));

export type AdminOverviewData = {
    cards: {
        members_active: number;
        members_inactive: number;
        participants: number;
        participants_filled: number;
        batches_active: number;
        batches_total: number;
        activity_week: number;
        activity_previous_week: number;
    };
    activity: ActivitySeries;
    recent: {
        id: number;
        description: string;
        module: string;
        occurred_at: string;
    }[];
};

const numberFormat = new Intl.NumberFormat('id-ID');

function sum(values: number[]): number {
    return values.reduce((total, value) => total + value, 0);
}

function StatCard({
    label,
    value,
    helper,
    icon: Icon,
    tone,
    footer,
}: {
    label: string;
    value: string;
    helper: string;
    icon: LucideIcon;
    tone: string;
    footer?: React.ReactNode;
}) {
    return (
        <Card className="gap-0 py-0">
            <CardContent className="grid gap-4 p-5">
                <div className="flex items-start justify-between gap-3">
                    <p className="text-[13px] font-medium text-muted-foreground">
                        {label}
                    </p>
                    <span
                        className={cn(
                            'flex size-10 shrink-0 items-center justify-center rounded-xl',
                            tone,
                        )}
                    >
                        <Icon className="size-5" aria-hidden="true" />
                    </span>
                </div>
                <div className="grid gap-1">
                    <p className="text-3xl leading-none font-semibold tracking-tight">
                        {value}
                    </p>
                    <p className="text-xs text-muted-foreground">{helper}</p>
                </div>
                {footer}
            </CardContent>
        </Card>
    );
}

function WeekDelta({
    current,
    previous,
}: {
    current: number;
    previous: number;
}) {
    if (previous === 0 && current === 0) {
        return (
            <span className="inline-flex w-fit items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">
                <Minus className="size-3" aria-hidden="true" />
                Belum ada aktivitas
            </span>
        );
    }

    if (previous === 0) {
        return (
            <span className="inline-flex w-fit items-center gap-1 rounded-full bg-[#16834a]/10 px-2 py-0.5 text-xs font-medium text-[#16834a]">
                <ArrowUpRight className="size-3" aria-hidden="true" />
                Baru, minggu lalu kosong
            </span>
        );
    }

    const change = Math.round(((current - previous) / previous) * 100);
    const Icon =
        change > 0 ? ArrowUpRight : change < 0 ? ArrowDownRight : Minus;
    const color =
        change > 0
            ? 'bg-[#16834a]/10 text-[#16834a]'
            : change < 0
              ? 'bg-destructive/10 text-destructive'
              : 'bg-muted text-muted-foreground';

    return (
        <span
            className={cn(
                'inline-flex w-fit items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
                color,
            )}
        >
            <Icon className="size-3" aria-hidden="true" />
            {change > 0 ? '+' : ''}
            {change}% dari minggu lalu
        </span>
    );
}

function CompletionBar({ percent }: { percent: number }) {
    return (
        <div
            role="progressbar"
            aria-valuenow={percent}
            aria-valuemin={0}
            aria-valuemax={100}
            aria-label="Kelengkapan kompetensi"
            className="h-2 overflow-hidden rounded-full bg-muted"
        >
            <div
                className="h-full rounded-full bg-primary transition-[width] duration-500"
                style={{ width: `${percent}%` }}
            />
        </div>
    );
}

function ChartSkeleton() {
    return (
        <div role="status" aria-label="Memuat grafik" className="grid gap-3">
            <Skeleton className="h-6 w-48" />
            <Skeleton className="h-[280px] w-full" />
        </div>
    );
}

function Legend({
    color,
    label,
    value,
}: {
    color: string;
    label: string;
    value: number;
}) {
    return (
        <div className="flex items-center gap-2">
            <span
                className="size-2.5 rounded-full"
                style={{ backgroundColor: color }}
                aria-hidden="true"
            />
            <span className="text-xs text-muted-foreground">{label}</span>
            <span className="text-sm font-semibold">
                {numberFormat.format(value)}
            </span>
        </div>
    );
}

export function AdminOverview({ overview }: { overview: AdminOverviewData }) {
    // The chart library needs the browser, so it is only rendered after mount.
    const [isMounted, setIsMounted] = useState(false);

    useEffect(() => {
        setIsMounted(true);
    }, []);

    const { cards, activity, recent } = overview;
    const completion =
        cards.participants === 0
            ? 0
            : Math.round(
                  (cards.participants_filled / cards.participants) * 100,
              );
    const totals = {
        login: sum(activity.login),
        changes: sum(activity.changes),
        failed: sum(activity.failed),
    };
    const hasActivity = totals.login + totals.changes + totals.failed > 0;

    return (
        <div className="flex flex-col gap-6">
            <section
                aria-label="Ringkasan sistem"
                className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
            >
                <StatCard
                    label="Anggota Aktif"
                    value={numberFormat.format(cards.members_active)}
                    helper={`${numberFormat.format(cards.members_inactive)} akun nonaktif`}
                    icon={Users}
                    tone="bg-primary/10 text-primary"
                />
                <StatCard
                    label="Kelengkapan Kompetensi"
                    value={`${completion}%`}
                    helper={`${numberFormat.format(cards.participants_filled)} dari ${numberFormat.format(cards.participants)} peserta sudah mengisi`}
                    icon={BookOpenCheck}
                    tone="bg-[#16834a]/10 text-[#16834a]"
                    footer={<CompletionBar percent={completion} />}
                />
                <StatCard
                    label="Batch Aktif"
                    value={numberFormat.format(cards.batches_active)}
                    helper={`dari ${numberFormat.format(cards.batches_total)} batch terdaftar`}
                    icon={Layers}
                    tone="bg-[#c96b0d]/10 text-[#c96b0d]"
                />
                <StatCard
                    label="Aktivitas 7 Hari"
                    value={numberFormat.format(cards.activity_week)}
                    helper="login dan perubahan data"
                    icon={Zap}
                    tone="bg-[#7c3aed]/10 text-[#7c3aed]"
                    footer={
                        <WeekDelta
                            current={cards.activity_week}
                            previous={cards.activity_previous_week}
                        />
                    }
                />
            </section>

            <div className="grid gap-6 lg:grid-cols-3">
                <Card className="lg:col-span-2">
                    <CardHeader className="gap-4">
                        <div className="grid gap-1">
                            <CardTitle className="text-base font-semibold">
                                Aktivitas 30 Hari Terakhir
                            </CardTitle>
                            <CardDescription className="text-[13px]">
                                Login, perubahan data, dan percobaan login yang
                                gagal setiap hari.
                            </CardDescription>
                        </div>
                        <div className="flex flex-wrap gap-x-6 gap-y-2">
                            <Legend
                                color="#2547F9"
                                label="Login"
                                value={totals.login}
                            />
                            <Legend
                                color="#16834A"
                                label="Perubahan data"
                                value={totals.changes}
                            />
                            <Legend
                                color="#D92D20"
                                label="Login gagal"
                                value={totals.failed}
                            />
                        </div>
                    </CardHeader>
                    <CardContent>
                        {hasActivity ? (
                            <div
                                role="img"
                                aria-label={`Grafik aktivitas 30 hari: ${totals.login} login, ${totals.changes} perubahan data, ${totals.failed} login gagal.`}
                            >
                                {isMounted ? (
                                    <Suspense fallback={<ChartSkeleton />}>
                                        <ActivityChart series={activity} />
                                    </Suspense>
                                ) : (
                                    <ChartSkeleton />
                                )}
                            </div>
                        ) : (
                            <div className="flex min-h-64 flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border/80 text-center">
                                <Zap
                                    className="size-6 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <p className="text-sm font-medium">
                                    Belum ada aktivitas
                                </p>
                                <p className="max-w-xs text-xs text-muted-foreground">
                                    Grafik akan terisi setelah ada login atau
                                    perubahan data dalam 30 hari terakhir.
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex-row items-start justify-between gap-3">
                        <div className="grid gap-1">
                            <CardTitle className="text-base font-semibold">
                                Aktivitas Terbaru
                            </CardTitle>
                            <CardDescription className="text-[13px]">
                                Yang baru saja terjadi di portal.
                            </CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent className="grid gap-4">
                        {recent.length === 0 ? (
                            <p className="rounded-lg bg-muted px-4 py-6 text-center text-sm text-muted-foreground">
                                Belum ada aktivitas tercatat.
                            </p>
                        ) : (
                            <ol className="grid">
                                {recent.map((item, index) => (
                                    <li
                                        key={item.id}
                                        className="relative grid grid-cols-[auto_1fr] gap-3 pb-4 last:pb-0"
                                    >
                                        <span className="relative flex flex-col items-center">
                                            <span
                                                className="mt-1.5 size-2.5 rounded-full bg-primary"
                                                aria-hidden="true"
                                            />
                                            {index < recent.length - 1 && (
                                                <span
                                                    className="mt-1 w-px flex-1 bg-border"
                                                    aria-hidden="true"
                                                />
                                            )}
                                        </span>
                                        <div className="grid gap-0.5">
                                            <p className="text-[13px] leading-snug">
                                                {item.description}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {item.module} ·{' '}
                                                {formatDistanceToNow(
                                                    parseISO(item.occurred_at),
                                                    {
                                                        addSuffix: true,
                                                        locale: indonesianLocale,
                                                    },
                                                )}
                                            </p>
                                        </div>
                                    </li>
                                ))}
                            </ol>
                        )}
                        <Link
                            href={auditIndex()}
                            className="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline"
                        >
                            Lihat semua aktivitas
                            <ArrowRight className="size-4" aria-hidden="true" />
                        </Link>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
