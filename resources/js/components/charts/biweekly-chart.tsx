import type { ApexOptions } from 'apexcharts';
import Chart from 'react-apexcharts';

export type BiweeklyCounts = {
    on_time: number;
    late: number;
    overdue: number;
};

const SLICES = [
    { key: 'on_time', label: 'Tepat waktu', color: '#16834A' },
    { key: 'late', label: 'Terlambat', color: '#F79009' },
    { key: 'overdue', label: 'Belum dikirim', color: '#D92D20' },
] as const;

/**
 * Donut of bi-weekly reports by submission state. The legend is rendered by
 * the dashboard so it can sit beside the chart on wide screens.
 */
export default function BiweeklyChart({
    counts,
    height = 200,
}: {
    counts: BiweeklyCounts;
    height?: number;
}) {
    const total = SLICES.reduce((sum, slice) => sum + counts[slice.key], 0);

    const options: ApexOptions = {
        chart: {
            type: 'donut',
            height,
            fontFamily: 'Poppins, sans-serif',
            foreColor: '#697386',
            animations: { enabled: true, speed: 400 },
        },
        labels: SLICES.map((slice) => slice.label),
        colors: SLICES.map((slice) => slice.color),
        stroke: { width: 3, colors: ['#ffffff'] },
        dataLabels: { enabled: false },
        legend: { show: false },
        states: {
            hover: { filter: { type: 'none' } },
            active: { filter: { type: 'none' } },
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '76%',
                    labels: {
                        show: true,
                        name: { show: false },
                        value: {
                            fontSize: '24px',
                            fontWeight: 600,
                            color: '#101828',
                            offsetY: 6,
                        },
                        total: {
                            show: true,
                            showAlways: true,
                            label: 'Laporan',
                            fontSize: '12px',
                            color: '#697386',
                            formatter: () => String(total),
                        },
                    },
                },
            },
        },
        tooltip: {
            y: { formatter: (value: number) => `${value} laporan` },
        },
    };

    return (
        <Chart
            type="donut"
            width="100%"
            height={height}
            options={options}
            series={SLICES.map((slice) => counts[slice.key])}
        />
    );
}
