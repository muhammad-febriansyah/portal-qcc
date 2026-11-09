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

export type CompetencyLevelCount = {
    level: number;
    label: string;
    count: number;
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
    competency_levels: CompetencyLevelCount[];
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

/** How many competency entries sit at each self-assessed level. */
function LevelBars({ levels }: { levels: CompetencyLevelCount[] }) {
    const total = sum(levels.map((level) => level.count));
    const max = Math.max(1, ...levels.map((level) => level.count));

    return (
        <ul className="grid" aria-label="Jumlah kompetensi per level">
            {levels.map((level, index) => (
                <li
                    key={level.level}
                    className="grid grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_auto] items-center gap-3 py-1.5 text-sm"
                >
                    <span className="flex min-w-0 items-center gap-2 leading-tight text-foreground">
                        <span
                            aria-hidden="true"
                            className="size-2 shrink-0 rounded-full"
                            style={{
                                backgroundColor:
                                    LEVEL_COLORS[index % LEVEL_COLORS.length],
                            }}
                        />
                        <span className="min-w-0">
                            <span className="font-medium tabular-nums">
                                {level.level}
                            </span>
                            {' · '}
                            {level.label}
                        </span>
                    </span>
                    <span
                        className="h-1.5 overflow-hidden rounded-full"
                        style={{ backgroundColor: programColors.track }}
                    >
                        <span
                            className="block h-full rounded-full"
                            style={{
                                width: `${(level.count / max) * 100}%`,
                                backgroundColor:
                                    LEVEL_COLORS[index % LEVEL_COLORS.length],
                            }}
                        />
                    </span>
                    <span
                        className="min-w-10 text-right font-medium text-foreground tabular-nums"
                        title={
                            total > 0
                                ? `${Math.round((level.count / total) * 100)}% dari ${numberFormat.format(total)} entri`
                                : undefined
                        }
                    >
                        {numberFormat.format(level.count)}
                    </span>
                </li>
            ))}
        </ul>
    );
}

export function AdminOverview({ overview }: { overview: AdminOverviewData }) {
    const isMounted = useMounted();
    const { cards, activity, recent, attention } = overview;
    const levels = overview.competency_levels;
    const levelEntries = sum(levels.map((level) => level.count));
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
                    title="Level kompetensi"
                    description={
                        levelEntries > 0
                            ? `${numberFormat.format(levelEntries)} kompetensi terisi, menurut penilaian diri peserta.`
                            : 'Berdasarkan level penilaian diri peserta.'
                    }
                >
                    {levelEntries > 0 ? (
                        <LevelBars levels={levels} />
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
