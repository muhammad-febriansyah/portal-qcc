import { Building2, FileText, ListChecks, Target, Users } from 'lucide-react';
import { lazy, Suspense, useState } from 'react';
import {
    ChartFallback,
    DashboardPanel,
    KpiCard,
    numberFormat,
    PanelEmpty,
    useMounted,
} from '@/components/dashboard/dashboard-kit';
import { ExecutionPhase } from './execution-phase';
import { BarList, LegendRow } from './program-kit';
import { phasePeriod, ProgramPhases } from './program-phases';
import { SelectionPhase } from './selection-phase';
import type {
    ProgramOverview,
    ProgramPhaseKey,
    RegistrationPhaseData,
} from './types';

const ProgramDonutChart = lazy(
    () => import('@/components/charts/program-donut-chart'),
);
const ProgramTrendChart = lazy(
    () => import('@/components/charts/program-trend-chart'),
);

/** Same validated order as the other portal charts; gray is "not categorized". */
const THEME_COLORS: Record<string, string> = {
    EFFICIENCY: '#0191C2',
    PRODUCTIVITY: '#C4844F',
    REVENUE_GM: '#7A5AF8',
    QUALITY_CUSTOMER_EXPERIENCE: '#16834A',
    RISK_COMPLIANCE_PROCESS_CONTROL: '#F79009',
    UNCATEGORIZED: '#98A2B3',
};
const SUBMITTED_COLOR = '#0191C2';
const REMAINING_COLOR = '#D0D5DD';

function RegistrationPhase({ data }: { data: RegistrationPhaseData }) {
    const isMounted = useMounted();
    const target = data.participant_target;
    const goal = target ?? data.participants;
    const remaining = Math.max(goal - data.submitters, 0);
    const submittersPercent =
        goal > 0 ? Math.round((data.submitters / goal) * 100) : 0;
    const activeOrganizations = data.submissions_by_organization.filter(
        (row) => row.count > 0,
    ).length;
    const themes = data.themes.filter(
        (theme) => theme.key !== 'UNCATEGORIZED' || theme.count > 0,
    );
    const hasTrend = data.trend.some(
        (point) => point.registered > 0 || point.submitted > 0,
    );

    return (
        <div className="grid gap-6">
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <KpiCard
                    label="Target Partisipasi"
                    value={target === null ? '–' : numberFormat.format(target)}
                    helper={
                        target === null
                            ? 'Belum diatur di data batch'
                            : 'SIMers yang mengajukan proposal'
                    }
                    icon={Target}
                    tone="bg-[#C4844F]/12 text-[#8F5A2E]"
                />
                <KpiCard
                    label="Total Registered"
                    value={numberFormat.format(data.registered)}
                    helper={`Profil lengkap dari ${numberFormat.format(data.participants)} akun SIMers`}
                    icon={Users}
                    tone="bg-primary/10 text-primary"
                />
                <KpiCard
                    label="Proposal Submitted"
                    value={numberFormat.format(data.proposals_submitted)}
                    helper={`Dari ${numberFormat.format(data.submitters)} SIMers pengusul`}
                    icon={FileText}
                    tone="bg-[#C4844F]/12 text-[#8F5A2E]"
                />
                <KpiCard
                    label="Completion Profil Kompetensi"
                    value={`${data.competency_completed_percent}%`}
                    helper={`${numberFormat.format(data.competency_completed)} SIMers sudah mengisi kompetensi`}
                    icon={ListChecks}
                    tone="bg-[#16834A]/10 text-[#16834A]"
                />
                <KpiCard
                    label="Total LOB/Div"
                    value={numberFormat.format(data.organizations_count)}
                    helper={`${activeOrganizations} LOB/Div sudah mengirim proposal`}
                    icon={Building2}
                    tone="bg-[#7A5AF8]/10 text-[#5B3FD6]"
                />
            </div>

            <div className="grid gap-6 xl:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
                <DashboardPanel
                    title="Participation Overview"
                    description={
                        target === null
                            ? 'SIMers yang sudah mengajukan proposal dibanding semua akun SIMers. Atur target peserta di data batch.'
                            : 'Realisasi SIMers yang mengajukan proposal terhadap target.'
                    }
                >
                    <div className="grid items-center gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] xl:grid-cols-1 2xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                        <div
                            role="img"
                            aria-label={`${data.submitters} dari ${goal} SIMers sudah mengajukan proposal (${submittersPercent}%).`}
                        >
                            {isMounted ? (
                                <Suspense
                                    fallback={<ChartFallback height={220} />}
                                >
                                    <ProgramDonutChart
                                        centerLabel={`${data.submitters} / ${goal} SIMers`}
                                        centerValue={`${submittersPercent}%`}
                                        unit="SIMers"
                                        slices={[
                                            {
                                                label: 'Sudah submit',
                                                value: data.submitters,
                                                color: SUBMITTED_COLOR,
                                            },
                                            {
                                                label: 'Belum submit',
                                                value: remaining,
                                                color: REMAINING_COLOR,
                                            },
                                        ]}
                                    />
                                </Suspense>
                            ) : (
                                <ChartFallback height={220} />
                            )}
                        </div>
                        <ul className="grid gap-3">
                            <LegendRow
                                color={SUBMITTED_COLOR}
                                label="Sudah submit"
                                value={`${numberFormat.format(data.submitters)} (${submittersPercent}%)`}
                            />
                            <LegendRow
                                color={REMAINING_COLOR}
                                label="Belum submit"
                                value={`${numberFormat.format(remaining)} (${goal > 0 ? 100 - submittersPercent : 0}%)`}
                            />
                        </ul>
                    </div>
                </DashboardPanel>

                <DashboardPanel
                    title="Sebaran Submit Proposal per LOB/Div"
                    description={`${data.organizations_count} LOB/Div, menurut LOB/Div pengusul.`}
                >
                    <BarList
                        label="Jumlah proposal per LOB/Div"
                        rows={data.submissions_by_organization.map((row) => ({
                            name: row.name,
                            value: row.count,
                            display: numberFormat.format(row.count),
                        }))}
                    />
                </DashboardPanel>
            </div>

            <div className="grid gap-6 xl:grid-cols-2">
                <DashboardPanel
                    title="Competency Profile SIMers"
                    description={`${numberFormat.format(data.competency_completed)} dari ${numberFormat.format(data.registered)} SIMers terdaftar sudah melengkapi profil kompetensi (${data.competency_completed_percent}%).`}
                >
                    {data.competency_groups.length > 0 ? (
                        <BarList
                            label="SIMers per kelompok kompetensi"
                            rows={data.competency_groups.map((group) => ({
                                name: group.name,
                                value: group.count,
                                display: `${numberFormat.format(group.count)} (${group.percent}%)`,
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
                    title="Sebaran Tema Improvement"
                    description="Proposal yang sudah diajukan menurut kategori tema."
                >
                    {data.proposals_submitted > 0 ? (
                        <div className="grid items-center gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)] xl:grid-cols-1 2xl:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)]">
                            <div
                                role="img"
                                aria-label={`Sebaran tema dari ${data.proposals_submitted} proposal.`}
                            >
                                {isMounted ? (
                                    <Suspense
                                        fallback={
                                            <ChartFallback height={220} />
                                        }
                                    >
                                        <ProgramDonutChart
                                            centerLabel="Proposal"
                                            centerValue={numberFormat.format(
                                                data.proposals_submitted,
                                            )}
                                            unit="proposal"
                                            slices={themes.map((theme) => ({
                                                label: theme.label,
                                                value: theme.count,
                                                color:
                                                    THEME_COLORS[theme.key] ??
                                                    REMAINING_COLOR,
                                            }))}
                                        />
                                    </Suspense>
                                ) : (
                                    <ChartFallback height={220} />
                                )}
                            </div>
                            <ul className="grid gap-3">
                                {themes.map((theme) => (
                                    <LegendRow
                                        key={theme.key}
                                        color={
                                            THEME_COLORS[theme.key] ??
                                            REMAINING_COLOR
                                        }
                                        label={theme.label}
                                        value={`${numberFormat.format(theme.count)} (${theme.percent}%)`}
                                    />
                                ))}
                            </ul>
                        </div>
                    ) : (
                        <PanelEmpty
                            title="Belum ada proposal"
                            description="Sebaran tema tampil setelah proposal pertama diajukan."
                        />
                    )}
                </DashboardPanel>
            </div>

            <DashboardPanel
                title="Tren Registrasi & Submit Proposal"
                description="Jumlah akumulasi per hari selama fase registrasi."
            >
                {hasTrend ? (
                    <div
                        className="w-full min-w-0"
                        role="img"
                        aria-label={`Tren akumulasi: ${data.registered} SIMers terdaftar dan ${data.proposals_submitted} proposal diajukan.`}
                    >
                        {isMounted ? (
                            <Suspense fallback={<ChartFallback height={300} />}>
                                <ProgramTrendChart points={data.trend} />
                            </Suspense>
                        ) : (
                            <ChartFallback height={300} />
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
        <div className="grid gap-6">
            <div className="grid gap-4">
                <div className="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <div className="grid gap-1">
                        <h2 className="text-lg font-semibold tracking-tight text-foreground">
                            {overview.batch.name} · Program Monitoring
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Progres program per fase. Fase berikutnya terbuka
                            otomatis pada tanggal mulainya.
                        </p>
                    </div>
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
