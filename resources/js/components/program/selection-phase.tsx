import {
    ChevronRight,
    FileText,
    FileX2,
    UserPlus,
    Users,
    UserCheck,
} from 'lucide-react';
import {
    DashboardPanel,
    KpiCard,
    numberFormat,
    PanelEmpty,
    ProgressBar,
} from '@/components/dashboard/dashboard-kit';
import { BarList, DonutWithLegend, programColors } from './program-kit';
import type { SelectionPhaseData } from './types';

const MATCHING_COLORS: Record<string, string> = {
    fit: programColors.primary,
    partial: programColors.tan,
    gap: programColors.neutral,
};

/** Phase 2: selection results and how far the teams of passed proposals are formed. */
export function SelectionPhase({ data }: { data: SelectionPhaseData }) {
    const matchedTeams = data.matching.reduce(
        (sum, band) => sum + band.count,
        0,
    );
    const formedTeams = data.team_sizes.reduce(
        (sum, size) => sum + size.count,
        0,
    );

    return (
        <div className="grid gap-6">
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <KpiCard
                    label="Total Proposal"
                    value={numberFormat.format(data.total)}
                    helper={`${numberFormat.format(data.in_selection)} masih dalam seleksi`}
                    icon={FileText}
                    tone="bg-[#C4844F]/12 text-[#8F5A2E]"
                />
                <KpiCard
                    label="Proposal Lolos Seleksi"
                    value={`${numberFormat.format(data.passed)} (${data.passed_percent}%)`}
                    helper="Disetujui Owner QCC"
                    icon={UserCheck}
                    tone="bg-[#16834A]/10 text-[#16834A]"
                />
                <KpiCard
                    label="Proposal Tidak Lolos"
                    value={`${numberFormat.format(data.failed)} (${data.failed_percent}%)`}
                    helper="Ditolak pada seleksi"
                    icon={FileX2}
                    tone="bg-[#D92D20]/10 text-[#B42318]"
                />
                <KpiCard
                    label="Proposal Sudah Terbentuk Tim"
                    value={`${numberFormat.format(data.teams_formed)} (${data.teams_formed_percent}%)`}
                    helper="Dari proposal yang lolos"
                    icon={Users}
                    tone="bg-primary/10 text-primary"
                />
                <KpiCard
                    label="Proposal Belum Terbentuk Tim"
                    value={`${numberFormat.format(data.teams_pending)} (${data.teams_pending_percent}%)`}
                    helper="Tim masih disusun"
                    icon={UserPlus}
                    tone="bg-[#7A5AF8]/10 text-[#5B3FD6]"
                />
            </div>

            <div className="grid gap-6 xl:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
                <DashboardPanel
                    title="Hasil Seleksi Proposal"
                    description="Proposal yang diajukan menurut hasil seleksinya."
                >
                    {data.total > 0 ? (
                        <DonutWithLegend
                            label={`${data.passed} lolos, ${data.failed} tidak lolos, ${data.in_selection} dalam seleksi dari ${data.total} proposal.`}
                            centerLabel="Proposal"
                            centerValue={numberFormat.format(data.total)}
                            unit="proposal"
                            slices={[
                                {
                                    label: 'Lolos seleksi',
                                    value: data.passed,
                                    color: programColors.primary,
                                },
                                {
                                    label: 'Tidak lolos',
                                    value: data.failed,
                                    color: programColors.muted,
                                },
                                {
                                    label: 'Dalam seleksi',
                                    value: data.in_selection,
                                    color: programColors.tan,
                                },
                            ]}
                            formatValue={(slice) =>
                                `${numberFormat.format(slice.value)} (${data.total > 0 ? Math.round((slice.value / data.total) * 100) : 0}%)`
                            }
                        />
                    ) : (
                        <PanelEmpty
                            title="Belum ada proposal"
                            description="Hasil seleksi tampil setelah proposal diajukan."
                        />
                    )}
                </DashboardPanel>

                <DashboardPanel
                    title="Progress Pembentukan Tim"
                    description="Dari proposal yang lolos sampai tim terbentuk. Tim juga bisa disusun manual tanpa matching AI."
                    action={
                        <span className="text-sm font-medium text-foreground tabular-nums">
                            {numberFormat.format(data.teams_formed)} /{' '}
                            {numberFormat.format(data.passed)} proposal
                        </span>
                    }
                >
                    <div className="grid gap-5">
                        <div className="grid gap-2">
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">
                                    Tim terbentuk
                                </span>
                                <span className="font-semibold text-foreground tabular-nums">
                                    {data.teams_formed_percent}%
                                </span>
                            </div>
                            <ProgressBar
                                value={data.teams_formed_percent}
                                label="Persentase tim terbentuk"
                                className="h-2.5"
                            />
                        </div>
                        <ol className="grid gap-3 sm:grid-cols-3">
                            {data.funnel.map((stage, index) => (
                                <li
                                    key={stage.key}
                                    className="relative grid gap-1 rounded-xl border border-border/70 bg-muted/20 px-4 py-3"
                                >
                                    <span className="text-xs font-medium text-muted-foreground">
                                        {stage.label}
                                    </span>
                                    <span className="text-2xl font-semibold text-foreground tabular-nums">
                                        {numberFormat.format(stage.count)}
                                    </span>
                                    {index < data.funnel.length - 1 && (
                                        <ChevronRight
                                            aria-hidden="true"
                                            className="absolute top-1/2 -right-3 hidden size-4 -translate-y-1/2 text-muted-foreground sm:block"
                                        />
                                    )}
                                </li>
                            ))}
                        </ol>
                    </div>
                </DashboardPanel>
            </div>

            <div className="grid gap-6 xl:grid-cols-3">
                <DashboardPanel
                    title="Sebaran Proposal Lolos per LOB/Div"
                    description="Menurut LOB/Div pengusul."
                >
                    <BarList
                        label="Proposal lolos per LOB/Div"
                        rows={data.passed_by_organization.map((row) => ({
                            name: row.name,
                            value: row.count,
                            display: numberFormat.format(row.count),
                        }))}
                    />
                </DashboardPanel>

                <DashboardPanel
                    title="Status Matching Kompetensi"
                    description="Seberapa banyak kebutuhan kompetensi proyek yang dipenuhi anggota tim."
                >
                    {matchedTeams > 0 ? (
                        <DonutWithLegend
                            label={`Kecocokan kompetensi ${matchedTeams} tim.`}
                            centerLabel="Tim"
                            centerValue={numberFormat.format(matchedTeams)}
                            unit="tim"
                            slices={data.matching.map((band) => ({
                                label: band.label,
                                value: band.count,
                                color:
                                    MATCHING_COLORS[band.key] ??
                                    programColors.neutral,
                            }))}
                            formatValue={(slice) => {
                                const band = data.matching.find(
                                    (item) => item.label === slice.label,
                                );

                                return `${numberFormat.format(slice.value)} (${band?.percent ?? 0}%)`;
                            }}
                        />
                    ) : (
                        <PanelEmpty
                            title="Belum ada tim"
                            description="Kecocokan kompetensi tampil setelah tim terbentuk."
                        />
                    )}
                </DashboardPanel>

                <DashboardPanel
                    title="Sebaran Jumlah Anggota Tim"
                    description="Jumlah anggota aktif per tim yang sudah terbentuk."
                >
                    {formedTeams > 0 ? (
                        <BarList
                            label="Jumlah tim per ukuran tim"
                            rows={data.team_sizes.map((size) => ({
                                name: size.label,
                                value: size.count,
                                display: `${numberFormat.format(size.count)} (${size.percent}%)`,
                            }))}
                        />
                    ) : (
                        <PanelEmpty
                            title="Belum ada tim"
                            description="Sebaran tampil setelah tim terbentuk."
                        />
                    )}
                </DashboardPanel>
            </div>
        </div>
    );
}
