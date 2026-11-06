import { ChevronRight, FileX2, Sparkles, UserPlus, Users } from 'lucide-react';
import { numberFormat, PanelEmpty } from '@/components/dashboard/dashboard-kit';
import {
    BarList,
    ColorBars,
    ColumnBars,
    HighlightCard,
    Meter,
    Panel,
    programColors,
    StatTiles,
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
    const matched =
        data.funnel.find((stage) => stage.key === 'matched')?.count ?? 0;

    return (
        <div className="grid gap-4">
            <div className="grid gap-4 xl:grid-cols-[minmax(0,4fr)_minmax(0,8fr)]">
                <HighlightCard
                    title="Proposal lolos seleksi"
                    percent={data.passed_percent}
                    caption="Proposal yang disetujui Owner QCC dari semua yang diajukan."
                    rows={[
                        {
                            label: 'Total proposal',
                            value: numberFormat.format(data.total),
                        },
                        {
                            label: 'Lolos seleksi',
                            value: numberFormat.format(data.passed),
                        },
                        {
                            label: 'Dalam seleksi',
                            value: numberFormat.format(data.in_selection),
                        },
                    ]}
                />
                <div className="grid min-w-0 gap-4">
                    <StatTiles
                        tiles={[
                            {
                                label: 'Tidak lolos',
                                value: numberFormat.format(data.failed),
                                suffix: `${data.failed_percent}%`,
                                helper: 'Ditolak pada seleksi',
                                icon: FileX2,
                                color: programColors.tan,
                            },
                            {
                                label: 'Matching AI',
                                value: numberFormat.format(matched),
                                helper: 'Proposal lolos dengan saran tim',
                                icon: Sparkles,
                                color: programColors.violet,
                            },
                            {
                                label: 'Tim terbentuk',
                                value: numberFormat.format(data.teams_formed),
                                suffix: `${data.teams_formed_percent}%`,
                                helper: 'Dari proposal yang lolos',
                                icon: Users,
                                color: programColors.primary,
                            },
                            {
                                label: 'Belum ada tim',
                                value: numberFormat.format(data.teams_pending),
                                suffix: `${data.teams_pending_percent}%`,
                                helper: 'Tim masih disusun',
                                icon: UserPlus,
                                color: programColors.green,
                            },
                        ]}
                    />
                    <Panel
                        title="Progress pembentukan tim"
                        description="Tim juga bisa disusun manual tanpa matching AI."
                    >
                        <Meter
                            percent={data.teams_formed_percent}
                            caption={`${numberFormat.format(data.teams_formed)} dari ${numberFormat.format(data.passed)} proposal lolos sudah punya tim`}
                            filledLabel={`Tim terbentuk ${numberFormat.format(data.teams_formed)}`}
                            restLabel={`Belum ${numberFormat.format(data.teams_pending)}`}
                            label="Persentase tim terbentuk"
                        />
                        <ol className="flex flex-wrap items-center gap-x-2 gap-y-1 border-t border-border/70 pt-3 text-sm">
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
                    </Panel>
                </div>
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                <Panel
                    title="Proposal lolos per LOB/Div"
                    description="Menurut LOB/Div pengusul."
                >
                    <BarList
                        label="Proposal lolos per LOB/Div"
                        limit={6}
                        emptyLabel="belum ada yang lolos"
                        rows={data.passed_by_organization.map((row) => ({
                            name: row.name,
                            value: row.count,
                            display: numberFormat.format(row.count),
                        }))}
                    />
                </Panel>

                <Panel
                    title="Status matching kompetensi"
                    description="Kebutuhan kompetensi proyek yang dipenuhi anggota tim."
                >
                    {formedTeams > 0 ? (
                        <ColorBars
                            label={`Kecocokan kompetensi ${formedTeams} tim`}
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
                </Panel>

                <Panel
                    title="Sebaran jumlah anggota tim"
                    description="Anggota aktif per tim yang sudah terbentuk."
                >
                    {formedTeams > 0 ? (
                        <ColumnBars
                            label="Jumlah tim per ukuran tim"
                            columns={data.team_sizes.map((size) => ({
                                key: String(size.size),
                                label: size.label,
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
                </Panel>
            </div>
        </div>
    );
}
