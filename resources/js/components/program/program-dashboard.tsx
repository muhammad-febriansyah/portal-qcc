import { lazy, Suspense, useState } from 'react';
import { Building2, FileText, ListChecks, Users } from 'lucide-react';
import {
    ChartFallback,
    numberFormat,
    PanelEmpty,
    useMounted,
} from '@/components/dashboard/dashboard-kit';
import { ExecutionPhase } from './execution-phase';
import {
    BarList,
    categoryColors,
    ColorBars,
    HighlightCard,
    Panel,
    percentOf,
    programColors,
    StatTiles,
} from './program-kit';
import {
    ProgramDetailProvider,
    useProgramDetail,
} from './program-detail-dialog';
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
    const openDetail = useProgramDetail();
    const select = (metric: string, title: string, key?: string | number) =>
        openDetail ? () => openDetail(metric, title, key) : undefined;
    const target = data.participant_target;
    const goal = target ?? data.participants;
    const activeOrganizations = data.submissions_by_organization.filter(
        (row) => row.count > 0,
    ).length;
    const hasTrend = data.trend.some(
        (point) => point.registered > 0 || point.submitted > 0,
    );

    return (
        <div className="grid gap-4">
            <div className="grid gap-4 xl:grid-cols-[minmax(0,4fr)_minmax(0,8fr)]">
                <HighlightCard
                    title="Realisasi submit proposal"
                    percent={percentOf(data.submitters, goal)}
                    caption={
                        target === null
                            ? 'SIMers pengusul dibanding semua akun SIMers. Atur target di data batch.'
                            : 'SIMers yang mengajukan proposal terhadap target.'
                    }
                    rows={[
                        {
                            label: 'Target peserta',
                            value:
                                target === null
                                    ? 'Belum diatur'
                                    : numberFormat.format(target),
                        },
                        {
                            label: 'Sudah submit',
                            value: numberFormat.format(data.submitters),
                            onSelect: select(
                                'submitters',
                                'Sudah submit proposal',
                            ),
                        },
                        {
                            label: 'Belum submit',
                            value: numberFormat.format(
                                Math.max(goal - data.submitters, 0),
                            ),
                            onSelect: select(
                                'not_submitted',
                                'Belum submit proposal',
                            ),
                        },
                    ]}
                />
                <div className="grid min-w-0 gap-4">
                    <StatTiles
                        tiles={[
                            {
                                label: 'Registered',
                                value: numberFormat.format(data.registered),
                                helper: `Profil lengkap dari ${numberFormat.format(data.participants)}`,
                                icon: Users,
                                color: programColors.primary,
                                onSelect: select(
                                    'registered',
                                    'SIMers terdaftar',
                                ),
                            },
                            {
                                label: 'Proposal',
                                value: numberFormat.format(
                                    data.proposals_submitted,
                                ),
                                helper: `Dari ${numberFormat.format(data.submitters)} pengusul`,
                                icon: FileText,
                                color: programColors.tan,
                                onSelect: select(
                                    'proposals',
                                    'Proposal diajukan',
                                ),
                            },
                            {
                                label: 'Profil kompetensi',
                                value: `${data.competency_completed_percent}%`,
                                helper: `${numberFormat.format(data.competency_completed)} SIMers mengisi`,
                                icon: ListChecks,
                                color: programColors.green,
                                onSelect: select(
                                    'competency_filled',
                                    'Profil kompetensi terisi',
                                ),
                            },
                            {
                                label: 'LOB/Div',
                                value: numberFormat.format(
                                    data.organizations_count,
                                ),
                                helper: `${activeOrganizations} sudah mengirim`,
                                icon: Building2,
                                color: programColors.violet,
                                onSelect: select('organizations', 'LOB/Div'),
                            },
                        ]}
                    />
                    <Panel
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
                                    <Suspense
                                        fallback={
                                            <ChartFallback height={190} />
                                        }
                                    >
                                        <ProgramTrendChart
                                            points={data.trend}
                                            height={190}
                                        />
                                    </Suspense>
                                ) : (
                                    <ChartFallback height={190} />
                                )}
                            </div>
                        ) : (
                            <p className="flex min-h-24 items-center justify-center rounded-lg border border-dashed border-border px-4 text-center text-sm text-muted-foreground">
                                {data.trend.length === 0
                                    ? 'Grafik tampil mulai tanggal fase registrasi dibuka.'
                                    : 'Grafik tampil setelah SIMers mulai melengkapi profil atau mengajukan proposal.'}
                            </p>
                        )}
                    </Panel>
                </div>
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <Panel
                    title="Proposal per LOB/Div"
                    description="Menurut LOB/Div pengusul."
                >
                    <BarList
                        label="Jumlah proposal per LOB/Div"
                        limit={6}
                        emptyLabel="belum ada proposal"
                        rows={data.submissions_by_organization.map((row) => ({
                            name: row.name,
                            value: row.count,
                            display: numberFormat.format(row.count),
                            onSelect: select(
                                'organization_proposals',
                                row.name,
                                row.id,
                            ),
                        }))}
                    />
                </Panel>

                <Panel
                    title="Competency profile SIMers"
                    description={`Skill yang paling banyak dimiliki SIMers terdaftar. ${numberFormat.format(data.competency_completed)} dari ${numberFormat.format(data.registered)} sudah mengisi.`}
                >
                    <BarList
                        label="SIMers per skill"
                        limit={6}
                        emptyLabel="skill belum dimiliki SIMers"
                        rows={data.top_competencies.map((competency) => ({
                            name: competency.name,
                            value: competency.count,
                            display: `${numberFormat.format(competency.count)} · ${competency.percent}%`,
                            onSelect: select(
                                'competency_holders',
                                competency.name,
                                competency.code,
                            ),
                        }))}
                    />
                </Panel>

                <Panel
                    title="Sebaran tema improvement"
                    description={`Kategori tema dari ${numberFormat.format(data.proposals_submitted)} proposal.`}
                >
                    <ColorBars
                        label="Proposal per kategori tema"
                        segments={data.themes
                            .filter(
                                (theme) =>
                                    theme.key !== 'UNCATEGORIZED' ||
                                    theme.count > 0,
                            )
                            .map((theme, index) => ({
                                key: theme.key,
                                label: theme.label,
                                count: theme.count,
                                color:
                                    theme.key === 'UNCATEGORIZED'
                                        ? programColors.rest
                                        : (categoryColors[index] ??
                                          programColors.rest),
                                onSelect: select(
                                    'theme_proposals',
                                    theme.label,
                                    theme.key,
                                ),
                            }))}
                    />
                </Panel>
            </div>
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
        <ProgramDetailProvider>
            <div className="grid gap-4">
                <div className="grid gap-2">
                    <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <h2 className="text-base font-semibold tracking-tight text-foreground">
                            {overview.batch.name} · Program Monitoring
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            Fase berikutnya terbuka otomatis pada tanggal
                            mulainya.
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
        </ProgramDetailProvider>
    );
}
