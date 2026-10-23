import type { ApexOptions } from 'apexcharts';
import Chart from 'react-apexcharts';

export type CompetencyLevelData = {
    groups: string[];
    levels: Record<string, { label: string; data: number[] }>;
};

const COLORS = ['#D0D5DD', '#8FA3FB', '#2547F9', '#16834A'];

/**
 * Stacked bars: how many competency entries sit at each level, per group.
 */
export default function CompetencyLevelChart({
    data,
    height = 320,
}: {
    data: CompetencyLevelData;
    height?: number;
}) {
    const levels = Object.values(data.levels);

    const options: ApexOptions = {
        chart: {
            type: 'bar',
            stacked: true,
            height,
            toolbar: { show: false },
            fontFamily: 'Poppins, sans-serif',
            foreColor: '#697386',
        },
        colors: COLORS,
        plotOptions: { bar: { columnWidth: '48%', borderRadius: 3 } },
        dataLabels: { enabled: false },
        stroke: { width: 1, colors: ['#fff'] },
        grid: { borderColor: '#E7EAF2', strokeDashArray: 4 },
        xaxis: {
            categories: data.groups,
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: { rotate: 0, trim: true, style: { fontSize: '12px' } },
        },
        yaxis: {
            min: 0,
            forceNiceScale: true,
            labels: { formatter: (value: number) => String(Math.round(value)) },
        },
        legend: {
            position: 'top',
            horizontalAlign: 'left',
            markers: { size: 6, shape: 'circle' },
        },
        tooltip: { shared: true, intersect: false },
    };

    return (
        <Chart
            type="bar"
            width="100%"
            height={height}
            options={options}
            series={levels.map((level) => ({
                name: level.label,
                data: level.data,
            }))}
        />
    );
}
