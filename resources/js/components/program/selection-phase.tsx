import { ChevronRight } from 'lucide-react';
import {
    DashboardPanel,
    numberFormat,
    PanelEmpty,
} from '@/components/dashboard/dashboard-kit';
import {
    BarList,
    Meter,
    programColors,
    StackedBar,
    StatStrip,
} from './program-kit';
import type { SelectionPhaseData } from './types';

const MATCHING_COLORS: Record<string, string> = {
    fit: programColors.primary,
    partial: programColors.tan,
    gap: programColors.rest,
};

/** Phase 2: selection results and how far the teams of passed proposals are formed. */
export function SelectionPhase({ data }: { data: SelectionPhaseData }) {
    const formedTeams = data.team_sizes.reduce(
        (sum, size) => sum + size.count,
        0,
    );

    return (
        <div className="grid gap-5">
            <StatStrip
                stats={[
                    {
                        label: 'Total proposal',
                        value: numberFormat.format(data.total),
                        helper: `${numberFormat.format(data.in_selection)} masih dalam seleksi`,
                    },
                    {
                        label: 'Proposal lolos seleksi',
                        value: numberFormat.format(data.passed),
                        suffix: `${data.passed_percent}%`,
                        helper: 'Disetujui Owner QCC',
                    },
                    {
                        label: 'Proposal tidak lolos',
                        value: numberFormat.format(data.failed),
                        suffix: `${data.failed_percent}%`,
                        helper: 'Ditolak pada seleksi',
                    },
                    {
                        label: 'Sudah terbentuk tim',
                        value: numberFormat.format(data.teams_formed),
                        suffix: `${data.teams_formed_percent}%`,
                        helper: 'Dari proposal yang lolos',
                    },
                    {
                        label: 'Belum terbentuk tim',
                        value: numberFormat.format(data.teams_pending),
                        suffix: `${data.teams_pending_percent}%`,
                        helper: 'Tim masih disusun',
                    },
                ]}
            />

            <div className="grid gap-5 xl:grid-cols-2">
                <DashboardPanel
                    title="Hasil seleksi proposal"
                    description="Proposal yang diajukan menurut hasil seleksinya."
                >
                    {data.total > 0 ? (
                        <StackedBar
                            label={`${data.passed} lolos, ${data.in_selection} dalam seleksi, ${data.failed} tidak lolos dari ${data.total} proposal.`}
                            segments={[
                                {
                                    key: 'passed',
                                    label: 'Lolos seleksi',
                                    count: data.passed,
                                    color: programColors.primary,
                                },
                                {
                                    key: 'in_selection',
                                    label: 'Dalam seleksi',
                                    count: data.in_selection,
                                    color: programColors.tan,
                                },
                                {
                                    key: 'failed',
                                    label: 'Tidak lolos',
                                    count: data.failed,
                                    color: programColors.rest,
                                },
                            ]}
                        />
                    ) : (
                        <PanelEmpty
                            title="Belum ada proposal"
                            description="Hasil seleksi tampil setelah proposal diajukan."
                        />
                    )}
                </DashboardPanel>

                <DashboardPanel
                    title="Progress pembentukan tim"
                    description="Dari proposal yang lolos sampai tim terbentuk. Tim juga bisa disusun manual tanpa matching AI."
                >
                    <div className="grid gap-5">
                        <Meter
                            percent={data.teams_formed_percent}
                            caption={`${numberFormat.format(data.teams_formed)} dari ${numberFormat.format(data.passed)} proposal lolos`}
                            filledLabel={`Tim terbentuk ${numberFormat.format(data.teams_formed)}`}
                            restLabel={`Belum ${numberFormat.format(data.teams_pending)}`}
                            label="Persentase tim terbentuk"
                        />
                        <ol className="flex flex-wrap items-center gap-x-2 gap-y-2 border-t border-border/70 pt-4 text-sm">
                            {data.funnel.map((stage, index) => (
                                <li
                                    key={stage.key}
                                    className="flex items-center gap-2"
                                >
                                    <span className="text-muted-foreground">
                                        {stage.label}
                                    </span>
                                    <span className="font-semibold text-foreground tabular-nums">
                                        {numberFormat.format(stage.count)}
                                    </span>
                                    {index < data.funnel.length - 1 && (
                                        <ChevronRight
                                            aria-hidden="true"
                                            className="size-4 text-muted-foreground/70"
                                        />
                                    )}
                                </li>
                            ))}
                        </ol>
                    </div>
                </DashboardPanel>
            </div>

            <div className="grid gap-5 xl:grid-cols-3">
                <DashboardPanel
                    title="Sebaran proposal lolos per LOB/Div"
                    description="Menurut LOB/Div pengusul."
                >
                    <BarList
                        label="Proposal lolos per LOB/Div"
                        emptyLabel="LOB/Div belum ada yang lolos"
                        rows={data.passed_by_organization.map((row) => ({
                            name: row.name,
                            value: row.count,
                            display: numberFormat.format(row.count),
                        }))}
                    />
                </DashboardPanel>

                <DashboardPanel
                    title="Status matching kompetensi"
                    description="Bagian kebutuhan kompetensi proyek yang dipenuhi anggota tim."
                >
                    {formedTeams > 0 ? (
                        <StackedBar
                            label={`Kecocokan kompetensi ${formedTeams} tim.`}
                            segments={data.matching.map((band) => ({
                                key: band.key,
                                label: band.label,
                                count: band.count,
                                color:
                                    MATCHING_COLORS[band.key] ??
                                    programColors.rest,
                            }))}
                        />
                    ) : (
                        <PanelEmpty
                            title="Belum ada tim"
                            description="Kecocokan kompetensi tampil setelah tim terbentuk."
                        />
                    )}
                </DashboardPanel>

                <DashboardPanel
                    title="Sebaran jumlah anggota tim"
                    description="Anggota aktif per tim yang sudah terbentuk."
                >
                    {formedTeams > 0 ? (
                        <BarList
                            label="Jumlah tim per ukuran tim"
                            rows={data.team_sizes.map((size) => ({
                                name: size.label,
                                value: size.count,
                                display: `${numberFormat.format(size.count)} · ${size.percent}%`,
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
