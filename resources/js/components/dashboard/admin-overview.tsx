import { Link } from '@inertiajs/react';
import { formatDistanceToNow, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import {
    ArrowDownRight,
    ArrowRight,
    ArrowUpRight,
    AlertTriangle,
    BookOpenCheck,
    CheckCircle2,
    Layers,
    Minus,
    Users,
    Zap,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { lazy, Suspense, useEffect, useState } from 'react';
import type { ActivitySeries } from '@/components/charts/activity-chart';
import type { CompetencyLevelData } from '@/components/charts/competency-level-chart';
import type { OrganizationCount } from '@/components/charts/organization-chart';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { index as auditIndex } from '@/routes/admin/audit';
import { index as rubricsIndex } from '@/routes/admin/rubrics';
import { index as settingsIndex } from '@/routes/admin/settings';
import { index as usersIndex } from '@/routes/admin/users';
import { index as competencyDirectoryIndex } from '@/routes/admin/competency-directory';

const ActivityChart = lazy(() => import('@/components/charts/activity-chart'));
const CompetencyLevelChart = lazy(
    () => import('@/components/charts/competency-level-chart'),
);
const OrganizationChart = lazy(
    () => import('@/components/charts/organization-chart'),
);

const attentionLinks: Record<
    string,
    () => { url: string; method: 'get' | 'head' }
> = {
    no_organization: usersIndex,
    no_sim_id: usersIndex,
    no_competency: competencyDirectoryIndex,
    unpublished_rubric: rubricsIndex,
    ai_not_configured: settingsIndex,
};

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
    competency_levels: CompetencyLevelData;
    members_by_organization: OrganizationCount[];
    attention: { key: string; label: string; count: number }[];
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
            <Badge variant="secondary" className="font-normal">
                <Minus aria-hidden="true" />
                Belum ada aktivitas
            </Badge>
        );
    }

    if (previous === 0) {
        return (
            <Badge variant="success" className="font-normal">
                <ArrowUpRight aria-hidden="true" />
                Baru, minggu lalu kosong
            </Badge>
        );
    }

    const change = Math.round(((current - previous) / previous) * 100);
    const Icon =
        change > 0 ? ArrowUpRight : change < 0 ? ArrowDownRight : Minus;
    const variant =
        change > 0 ? 'success' : change < 0 ? 'destructive' : 'secondary';

    return (
        <Badge variant={variant} className="font-normal">
            <Icon aria-hidden="true" />
            {change > 0 ? '+' : ''}
            {change}% dari minggu lalu
        </Badge>
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

function EmptyChart({ text }: { text: string }) {
    return (
        <div className="flex min-h-64 items-center justify-center rounded-lg border border-dashed border-border/80 px-4 text-center text-sm text-muted-foreground">
            {text}
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

    const { cards, activity, recent, attention } = overview;
    const levels = overview.competency_levels;
    const organizations = overview.members_by_organization;
    const hasLevels = Object.values(levels.levels).some((level) =>
        level.data.some((value) => value > 0),
    );
    const hasOrganizations = organizations.some((row) => row.total > 0);
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
                    tone="bg-success/10 text-success"
                    footer={<CompletionBar percent={completion} />}
                />
                <StatCard
                    label="Batch Aktif"
                    value={numberFormat.format(cards.batches_active)}
                    helper={`dari ${numberFormat.format(cards.batches_total)} batch terdaftar`}
                    icon={Layers}
                    tone="bg-warning/10 text-warning"
                />
                <StatCard
                    label="Aktivitas 7 Hari"
                    value={numberFormat.format(cards.activity_week)}
                    helper="login dan perubahan data"
                    icon={Zap}
                    tone="bg-violet/10 text-violet"
                    footer={
                        <WeekDelta
                            current={cards.activity_week}
                            previous={cards.activity_previous_week}
                        />
                    }
                />
            </section>

            <div className="flex flex-col gap-6">
                <Card className="min-w-0">
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
                    <CardContent className="min-w-0">
                        {hasActivity ? (
                            <div
                                className="w-full min-w-0"
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

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card className="min-w-0">
                        <CardHeader>
                            <CardTitle className="text-base font-semibold">
                                Level Kompetensi per Kelompok
                            </CardTitle>
                            <CardDescription className="text-[13px]">
                                Level terverifikasi dipakai lebih dulu, jika
                                belum ada dipakai penilaian diri.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="min-w-0">
                            {hasLevels ? (
                                <div
                                    className="w-full min-w-0"
                                    role="img"
                                    aria-label="Grafik jumlah kompetensi di setiap level untuk tiap kelompok."
                                >
                                    {isMounted ? (
                                        <Suspense fallback={<ChartSkeleton />}>
                                            <CompetencyLevelChart
                                                data={levels}
                                            />
                                        </Suspense>
                                    ) : (
                                        <ChartSkeleton />
                                    )}
                                </div>
                            ) : (
                                <EmptyChart text="Belum ada kompetensi yang diisi peserta." />
                            )}
                        </CardContent>
                    </Card>

                    <Card className="min-w-0">
                        <CardHeader>
                            <CardTitle className="text-base font-semibold">
                                Peserta per LOB / Divisi
                            </CardTitle>
                            <CardDescription className="text-[13px]">
                                Enam LOB terbesar, sisanya digabung.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="min-w-0">
                            {hasOrganizations ? (
                                <div
                                    className="w-full min-w-0"
                                    role="img"
                                    aria-label="Grafik jumlah peserta aktif per LOB atau divisi."
                                >
                                    {isMounted ? (
                                        <Suspense fallback={<ChartSkeleton />}>
                                            <OrganizationChart
                                                rows={organizations}
                                            />
                                        </Suspense>
                                    ) : (
                                        <ChartSkeleton />
                                    )}
                                </div>
                            ) : (
                                <EmptyChart text="Belum ada peserta aktif." />
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card className="min-w-0">
                        <CardHeader>
                            <CardTitle className="text-base font-semibold">
                                Perlu Perhatian
                            </CardTitle>
                            <CardDescription className="text-[13px]">
                                Hal yang sebaiknya dibereskan admin.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {attention.length === 0 ? (
                                <div className="flex items-center gap-3 rounded-lg bg-success/10 px-4 py-4 text-sm text-success">
                                    <CheckCircle2
                                        className="size-5 shrink-0"
                                        aria-hidden="true"
                                    />
                                    Semua beres, tidak ada yang perlu ditindak.
                                </div>
                            ) : (
                                <ul className="grid">
                                    {attention.map((item) => {
                                        const link = attentionLinks[item.key];

                                        return (
                                            <li
                                                key={item.key}
                                                className="border-b border-border/60 last:border-b-0"
                                            >
                                                <Link
                                                    href={
                                                        link ? link().url : '#'
                                                    }
                                                    className="flex items-center gap-3 py-3 text-[13px] hover:text-primary"
                                                >
                                                    <AlertTriangle
                                                        className="size-4 shrink-0 text-warning"
                                                        aria-hidden="true"
                                                    />
                                                    <span className="min-w-0 flex-1">
                                                        {item.label}
                                                    </span>
                                                    {item.key !==
                                                        'ai_not_configured' && (
                                                        <Badge
                                                            variant="secondary"
                                                            className="font-semibold"
                                                        >
                                                            {numberFormat.format(
                                                                item.count,
                                                            )}
                                                        </Badge>
                                                    )}
                                                    <ArrowRight
                                                        className="size-4 shrink-0 text-muted-foreground"
                                                        aria-hidden="true"
                                                    />
                                                </Link>
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="min-w-0">
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
                                    {recent.map((item) => (
                                        <li
                                            key={item.id}
                                            className="flex gap-3 border-b border-border/60 py-3 first:pt-0 last:border-b-0"
                                        >
                                            <span
                                                className="mt-1.5 size-2.5 shrink-0 rounded-full bg-primary"
                                                aria-hidden="true"
                                            />
                                            <div className="grid min-w-0 gap-0.5">
                                                <p className="text-[13px] leading-snug">
                                                    {item.description}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {item.module} ·{' '}
                                                    {formatDistanceToNow(
                                                        parseISO(
                                                            item.occurred_at,
                                                        ),
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
                                <ArrowRight
                                    className="size-4"
                                    aria-hidden="true"
                                />
                            </Link>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    );
}
