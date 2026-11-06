import { lazy, Suspense, useState } from 'react';
import {
    ChartFallback,
    DashboardPanel,
    numberFormat,
    PanelEmpty,
    useMounted,
} from '@/components/dashboard/dashboard-kit';
import { ExecutionPhase } from './execution-phase';
import {
    BarList,
    categoryColors,
    Meter,
    percentOf,
    programColors,
    StackedBar,
    StatStrip,
} from './program-kit';
import { phasePeriod, ProgramPhases } from './program-phases';
import { SelectionPhase } from './selection-phase';
import type {
    ProgramOverview,
    ProgramPhaseKey,
    RegistrationPhaseData,
} from './types';

const ProgramTrendChart = lazy(
    () => import('@/components/charts/program-trend-chart'),
);

function RegistrationPhase({ data }: { data: RegistrationPhaseData }) {
    const isMounted = useMounted();
    const target = data.participant_target;
    const goal = target ?? data.participants;
    const remaining = Math.max(goal - data.submitters, 0);
    const activeOrganizations = data.submissions_by_organization.filter(
        (row) => row.count > 0,
    ).length;
    const categorized = data.themes.filter(
        (theme) => theme.key !== 'UNCATEGORIZED',
    );
    const uncategorized = data.themes.find(
        (theme) => theme.key === 'UNCATEGORIZED',
    );
    const hasTrend = data.trend.some(
        (point) => point.registered > 0 || point.submitted > 0,
    );

    return (
        <div className="grid gap-5">
            <StatStrip
                stats={[
                    {
                        label: 'Target partisipasi',
                        value:
                            target === null ? '–' : numberFormat.format(target),
                        helper:
                            target === null
                                ? 'Belum diatur di data batch'
                                : 'SIMers mengajukan proposal',
                    },
                    {
                        label: 'Total registered',
                        value: numberFormat.format(data.registered),
                        helper: `Profil lengkap dari ${numberFormat.format(data.participants)} akun`,
                    },
                    {
                        label: 'Proposal submitted',
                        value: numberFormat.format(data.proposals_submitted),
                        helper: `Dari ${numberFormat.format(data.submitters)} SIMers pengusul`,
                    },
                    {
                        label: 'Completion profil kompetensi',
                        value: `${data.competency_completed_percent}%`,
                        helper: `${numberFormat.format(data.competency_completed)} SIMers`,
                    },
                    {
                        label: 'Total LOB/Div',
                        value: numberFormat.format(data.organizations_count),
                        helper: `${activeOrganizations} sudah mengirim proposal`,
                    },
                ]}
            />

            <div className="grid gap-5 xl:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
                <DashboardPanel
                    title="Participation overview"
                    description={
                        target === null
                            ? 'SIMers pengusul dibanding semua akun SIMers. Atur target peserta di data batch.'
                            : 'Realisasi SIMers yang mengajukan proposal terhadap target.'
                    }
                >
                    <Meter
                        percent={percentOf(data.submitters, goal)}
                        caption={`${numberFormat.format(data.submitters)} dari ${numberFormat.format(goal)} SIMers`}
                        filledLabel={`Sudah submit ${numberFormat.format(data.submitters)}`}
                        restLabel={`Belum submit ${numberFormat.format(remaining)}`}
                        label="Realisasi submit proposal terhadap target"
                    />
                    <dl className="mt-5 grid divide-y divide-border/60 border-t border-border/70 text-sm">
                        {[
                            [
                                'Target peserta',
                                target === null
                                    ? 'Belum diatur'
                                    : numberFormat.format(target),
                            ],
                            [
                                'Akun SIMers',
                                numberFormat.format(data.participants),
                            ],
                            [
                                'Profil lengkap',
                                numberFormat.format(data.registered),
                            ],
                            [
                                'SIMers pengusul',
                                numberFormat.format(data.submitters),
                            ],
                        ].map(([term, value]) => (
                            <div
                                key={term}
                                className="flex items-center justify-between gap-3 py-2"
                            >
                                <dt className="text-muted-foreground">
                                    {term}
                                </dt>
                                <dd className="font-medium text-foreground tabular-nums">
                                    {value}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </DashboardPanel>

                <DashboardPanel
                    title="Sebaran submit proposal per LOB/Div"
                    description="Menurut LOB/Div pengusul, terbanyak di atas."
                >
                    <BarList
                        label="Jumlah proposal per LOB/Div"
                        emptyLabel="LOB/Div belum ada proposal"
                        rows={data.submissions_by_organization.map((row) => ({
                            name: row.name,
                            value: row.count,
                            display: numberFormat.format(row.count),
                        }))}
                    />
                </DashboardPanel>
            </div>

            <div className="grid gap-5 xl:grid-cols-2">
                <DashboardPanel
                    title="Competency profile SIMers"
                    description={`SIMers terdaftar yang punya kompetensi di tiap kelompok. ${numberFormat.format(data.competency_completed)} dari ${numberFormat.format(data.registered)} sudah mengisi (${data.competency_completed_percent}%).`}
                >
                    {data.competency_groups.length > 0 ? (
                        <BarList
                            label="SIMers per kelompok kompetensi"
                            emptyLabel="kelompok belum terisi"
                            rows={data.competency_groups.map((group) => ({
                                name: group.name,
                                value: group.count,
                                display: `${numberFormat.format(group.count)} · ${group.percent}%`,
                            }))}
                        />
                    ) : (
                        <PanelEmpty
                            title="Belum ada kelompok kompetensi"
                            description="Tambahkan kelompok kompetensi di master kompetensi."
                        />
                    )}
                </DashboardPanel>

                <DashboardPanel
                    title="Sebaran tema improvement"
                    description={`Kategori tema dari ${numberFormat.format(data.proposals_submitted)} proposal yang diajukan.`}
                >
                    {data.proposals_submitted > 0 ? (
                        <StackedBar
                            label={`Sebaran kategori tema dari ${data.proposals_submitted} proposal.`}
                            segments={[
                                ...categorized.map((theme, index) => ({
                                    key: theme.key,
                                    label: theme.label,
                                    count: theme.count,
                                    color:
                                        categoryColors[index] ??
                                        programColors.rest,
                                })),
                                ...(uncategorized
                                    ? [
                                          {
                                              key: uncategorized.key,
                                              label: uncategorized.label,
                                              count: uncategorized.count,
                                              color: programColors.rest,
                                          },
                                      ]
                                    : []),
                            ]}
                        />
                    ) : (
                        <PanelEmpty
                            title="Belum ada proposal"
                            description="Sebaran tema tampil setelah proposal pertama diajukan."
                        />
                    )}
                </DashboardPanel>
            </div>

            <DashboardPanel
                title="Tren registrasi dan submit proposal"
                description="Jumlah akumulasi per hari selama fase registrasi."
            >
                {hasTrend ? (
                    <div
                        className="w-full min-w-0"
                        role="img"
                        aria-label={`Tren akumulasi: ${data.registered} SIMers terdaftar dan ${data.proposals_submitted} proposal diajukan.`}
                    >
                        {isMounted ? (
                            <Suspense fallback={<ChartFallback height={280} />}>
                                <ProgramTrendChart points={data.trend} />
                            </Suspense>
                        ) : (
                            <ChartFallback height={280} />
                        )}
                    </div>
                ) : (
                    <PanelEmpty
                        title={
                            data.trend.length === 0
                                ? 'Fase registrasi belum dibuka'
                                : 'Belum ada aktivitas'
                        }
                        description={
                            data.trend.length === 0
                                ? 'Grafik tampil mulai tanggal fase registrasi dibuka.'
                                : 'Grafik tampil setelah SIMers mulai melengkapi profil atau mengajukan proposal.'
                        }
                    />
                )}
            </DashboardPanel>
        </div>
    );
}

/**
 * Program monitoring of the running batch: the phases this viewer may follow,
 * with the figures of the phase picked (the active one by default).
 */
export function ProgramDashboard({ overview }: { overview: ProgramOverview }) {
    const [selected, setSelected] = useState<ProgramPhaseKey>(
        overview.active_phase ??
            overview.phases.find((phase) => phase.viewable)?.key ??
            'registration',
    );
    const phase = overview.phases.find((item) => item.key === selected);

    return (
        <div className="grid gap-5">
            <div className="grid gap-3">
                <div className="grid gap-1">
                    <h2 className="text-lg font-semibold tracking-tight text-foreground">
                        {overview.batch.name} · Program Monitoring
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Progres program per fase. Fase berikutnya terbuka
                        otomatis pada tanggal mulainya.
                    </p>
                </div>
                <ProgramPhases
                    phases={overview.phases}
                    selected={selected}
                    onSelect={setSelected}
                />
            </div>

            {selected === 'registration' && overview.registration ? (
                <RegistrationPhase data={overview.registration} />
            ) : selected === 'selection' && overview.selection ? (
                <SelectionPhase data={overview.selection} />
            ) : selected === 'execution' && overview.execution ? (
                <ExecutionPhase data={overview.execution} />
            ) : (
                <PanelEmpty
                    title={`Fase ${phase?.number ?? ''} · ${phase?.label ?? ''}`}
                    description={
                        phase
                            ? `${phasePeriod(phase)}. Fase ini terbuka pada tanggal mulainya.`
                            : 'Fase belum tersedia.'
                    }
                />
            )}
        </div>
    );
}
