import type { ApexOptions } from 'apexcharts';
import Chart from 'react-apexcharts';

export type ProjectStatusCount = {
    status: string;
    label: string;
    count: number;
};

/**
 * Horizontal bars, one per proposal status. Flat primary colour, no gradient.
 */
export default function ProjectStatusChart({
    rows,
    height = 260,
}: {
    rows: ProjectStatusCount[];
    height?: number;
}) {
    const options: ApexOptions = {
        chart: {
            type: 'bar',
            height,
            toolbar: { show: false },
            zoom: { enabled: false },
            fontFamily: 'Poppins, sans-serif',
            foreColor: '#697386',
            animations: { enabled: true, speed: 400 },
        },
        colors: ['#2547F9'],
        states: {
            hover: { filter: { type: 'none' } },
            active: { filter: { type: 'none' } },
        },
        plotOptions: {
            bar: {
                horizontal: true,
                borderRadius: 4,
                borderRadiusApplication: 'end',
                barHeight: '56%',
            },
        },
        dataLabels: {
            enabled: true,
            textAnchor: 'start',
            offsetX: 6,
            style: { fontSize: '12px', fontWeight: 600, colors: ['#101828'] },
            formatter: (value: number) => String(value),
        },
        xaxis: {
            categories: rows.map((row) => row.label),
            labels: {
                style: { fontSize: '12px' },
            },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: {
            labels: { maxWidth: 150 },
        },
        grid: {
            borderColor: '#E7EAF2',
            strokeDashArray: 4,
            padding: { left: 0, right: 12 },
            xaxis: { lines: { show: true } },
            yaxis: { lines: { show: false } },
        },
        legend: { show: false },
        tooltip: {
            y: { formatter: (value: number) => `${value} proyek` },
        },
    };

    return (
        <Chart
            type="bar"
            width="100%"
            height={height}
            options={options}
            series={[{ name: 'Proyek', data: rows.map((row) => row.count) }]}
        />
    );
}
