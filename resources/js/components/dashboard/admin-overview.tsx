import { Link } from '@inertiajs/react';
import { formatDistanceToNow, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import {
    ArrowRight,
    AlertTriangle,
    BookOpenCheck,
    CheckCircle2,
    Layers,
    Users,
    Zap,
} from 'lucide-react';
import { lazy, Suspense } from 'react';
import type { ActivitySeries } from '@/components/charts/activity-chart';
import { Skeleton } from '@/components/ui/skeleton';
import { Badge } from '@/components/ui/badge';
import { PanelEmpty, useMounted } from '@/components/dashboard/dashboard-kit';
import {
    BarList,
    Panel,
    programColors,
    StatTiles,
} from '@/components/program/program-kit';
import { index as auditIndex } from '@/routes/admin/audit';
import { index as batchesIndex } from '@/routes/admin/batches';
import { index as settingsIndex } from '@/routes/admin/settings';
import { index as usersIndex } from '@/routes/admin/users';
import { index as competencyDirectoryIndex } from '@/routes/admin/competency-directory';

const ActivityChart = lazy(() => import('@/components/charts/activity-chart'));

const attentionLinks: Record<
    string,
    () => { url: string; method: 'get' | 'head' }
> = {
    no_organization: usersIndex,
    no_sim_id: usersIndex,
    incomplete_contacts: usersIndex,
    no_competency: competencyDirectoryIndex,
    batch_schedules_incomplete: batchesIndex,
    ai_not_configured: settingsIndex,
};

export type CompetencyLevelData = {
    groups: string[];
    levels: Record<string, { label: string; data: number[] }>;
};

export type OrganizationCount = { label: string; total: number };

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

const LEVEL_COLORS = ['#D0D5DD', '#80C8E0', '#0191C2', '#16834A'];

function weekDelta(current: number, previous: number): string {
    if (previous === 0) {
        return current === 0
            ? 'Belum ada aktivitas'
            : 'Baru, minggu lalu kosong';
    }

    const change = Math.round(((current - previous) / previous) * 100);

    return `${change > 0 ? '+' : ''}${change}% dari minggu lalu`;
}

function ChartSkeleton() {
    return (
        <div role="status" aria-label="Memuat grafik" className="grid gap-3">
            <Skeleton className="h-[220px] w-full" />
        </div>
    );
}

/** Competency entries per level for each group, as one stacked bar per group. */
function LevelBars({ data }: { data: CompetencyLevelData }) {
    const levels = Object.values(data.levels);
    const totals = data.groups.map((_, index) =>
        sum(levels.map((level) => level.data[index] ?? 0)),
    );
    const max = Math.max(1, ...totals);

    return (
        <div className="grid gap-3">
            <ul className="flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground">
                {levels.map((level, index) => (
                    <li key={level.label} className="flex items-center gap-1.5">
                        <span
                            aria-hidden="true"
                            className="size-2 rounded-full"
                            style={{ backgroundColor: LEVEL_COLORS[index] }}
                        />
                        {level.label}
                    </li>
                ))}
            </ul>
            <ul
                className="grid"
                aria-label="Kompetensi per level untuk tiap kelompok"
            >
                {data.groups.map((group, groupIndex) => (
                    <li
                        key={group}
                        className="grid grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_auto] items-center gap-3 py-1.5 text-sm"
                    >
                        <span className="leading-tight text-foreground">
                            {group}
                        </span>
                        <span
                            className="flex h-1.5 gap-px overflow-hidden rounded-full"
                            style={{ backgroundColor: '#E8EEF2' }}
                            title={levels
                                .map(
                                    (level) =>
                                        `${level.label}: ${level.data[groupIndex] ?? 0}`,
                                )
                                .join(' · ')}
                        >
                            {levels.map((level, levelIndex) => (
                                <span
                                    key={level.label}
                                    className="h-full"
                                    style={{
                                        width: `${((level.data[groupIndex] ?? 0) / max) * 100}%`,
                                        backgroundColor:
                                            LEVEL_COLORS[levelIndex],
                                    }}
                                />
                            ))}
                        </span>
                        <span className="min-w-8 text-right font-medium text-foreground tabular-nums">
                            {numberFormat.format(totals[groupIndex])}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

export function AdminOverview({ overview }: { overview: AdminOverviewData }) {
    const isMounted = useMounted();
    const { cards, activity, recent, attention } = overview;
    const levels = overview.competency_levels;
    const hasLevels = Object.values(levels.levels).some((level) =>
        level.data.some((value) => value > 0),
    );
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
        <div className="grid gap-4">
            <StatTiles
                tiles={[
                    {
                        label: 'Anggota aktif',
                        value: numberFormat.format(cards.members_active),
                        helper: `${numberFormat.format(cards.members_inactive)} akun nonaktif`,
                        icon: Users,
                        color: programColors.primary,
                    },
                    {
                        label: 'Kelengkapan kompetensi',
                        value: `${completion}%`,
                        helper: `${numberFormat.format(cards.participants_filled)} dari ${numberFormat.format(cards.participants)} peserta`,
                        icon: BookOpenCheck,
                        color: programColors.green,
                    },
                    {
                        label: 'Batch aktif',
                        value: numberFormat.format(cards.batches_active),
                        helper: `Dari ${numberFormat.format(cards.batches_total)} batch terdaftar`,
                        icon: Layers,
                        color: programColors.tan,
                    },
                    {
                        label: 'Aktivitas 7 hari',
                        value: numberFormat.format(cards.activity_week),
                        helper: weekDelta(
                            cards.activity_week,
                            cards.activity_previous_week,
                        ),
                        icon: Zap,
                        color: programColors.violet,
                    },
                ]}
            />

            <div className="grid gap-4 xl:grid-cols-[minmax(0,8fr)_minmax(0,4fr)]">
                <Panel
                    title="Aktivitas 30 hari terakhir"
                    description={`${numberFormat.format(totals.login)} login · ${numberFormat.format(totals.changes)} perubahan data · ${numberFormat.format(totals.failed)} login gagal`}
                >
                    {hasActivity ? (
                        <div
                            className="w-full min-w-0"
                            role="img"
                            aria-label={`Grafik aktivitas 30 hari: ${totals.login} login, ${totals.changes} perubahan data, ${totals.failed} login gagal.`}
                        >
                            {isMounted ? (
                                <Suspense fallback={<ChartSkeleton />}>
                                    <ActivityChart
                                        series={activity}
                                        height={240}
                                    />
                                </Suspense>
                            ) : (
                                <ChartSkeleton />
                            )}
                        </div>
                    ) : (
                        <PanelEmpty
                            icon={Zap}
                            title="Belum ada aktivitas"
                            description="Grafik terisi setelah ada login atau perubahan data dalam 30 hari terakhir."
                        />
                    )}
                </Panel>

                <Panel
                    title="Perlu perhatian"
                    description="Hal yang sebaiknya dibereskan admin."
                >
                    {attention.length === 0 ? (
                        <div className="flex items-center gap-3 rounded-lg bg-success/10 px-4 py-4 text-sm text-success">
                            <CheckCircle2
                                className="size-5 shrink-0"
                                aria-hidden="true"
                            />
                            Semua beres, tidak ada yang perlu ditindak.
                        </div>
                    ) : (
                        <ul className="grid divide-y divide-border/60">
                            {attention.map((item) => {
                                const link = attentionLinks[item.key];

                                return (
                                    <li key={item.key}>
                                        <Link
                                            href={link ? link().url : '#'}
                                            className="flex items-center gap-3 py-2.5 text-sm hover:text-primary"
                                        >
                                            <AlertTriangle
                                                className="size-4 shrink-0 text-warning"
                                                aria-hidden="true"
                                            />
                                            <span className="min-w-0 flex-1 leading-snug">
                                                {item.label}
                                            </span>
                                            {item.key !==
                                                'ai_not_configured' && (
                                                <Badge
                                                    variant="secondary"
                                                    className="font-semibold tabular-nums"
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
                </Panel>
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <Panel
                    title="Level kompetensi per kelompok"
                    description="Berdasarkan level penilaian diri peserta."
                >
                    {hasLevels ? (
                        <LevelBars data={levels} />
                    ) : (
                        <PanelEmpty
                            title="Belum ada data"
                            description="Belum ada kompetensi yang diisi peserta."
                        />
                    )}
                </Panel>

                <Panel
                    title="Peserta per LOB/Div"
                    description="Peserta aktif, terbanyak di atas."
                >
                    <BarList
                        label="Peserta per LOB/Div"
                        limit={7}
                        emptyLabel="LOB/Div tanpa peserta"
                        rows={overview.members_by_organization.map((row) => ({
                            name: row.label,
                            value: row.total,
                            display: numberFormat.format(row.total),
                        }))}
                    />
                </Panel>

                <Panel
                    title="Aktivitas terbaru"
                    description="Yang baru saja terjadi di portal."
                    action={
                        <Link
                            href={auditIndex()}
                            className="inline-flex shrink-0 items-center gap-1 text-xs font-medium text-primary hover:underline"
                        >
                            Lihat semua
                            <ArrowRight
                                className="size-3.5"
                                aria-hidden="true"
                            />
                        </Link>
                    }
                >
                    {recent.length === 0 ? (
                        <p className="rounded-lg bg-muted px-4 py-6 text-center text-sm text-muted-foreground">
                            Belum ada aktivitas tercatat.
                        </p>
                    ) : (
                        <ol className="grid divide-y divide-border/60">
                            {recent.slice(0, 6).map((item) => (
                                <li
                                    key={item.id}
                                    className="flex gap-3 py-2 first:pt-0"
                                >
                                    <span
                                        className="mt-1.5 size-2 shrink-0 rounded-full bg-primary"
                                        aria-hidden="true"
                                    />
                                    <div className="grid min-w-0 gap-0.5">
                                        <p className="line-clamp-2 text-sm leading-snug">
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
                </Panel>
            </div>
        </div>
    );
}
