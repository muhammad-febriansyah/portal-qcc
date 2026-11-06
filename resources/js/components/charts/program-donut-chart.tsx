import type { ApexOptions } from 'apexcharts';
import Chart from 'react-apexcharts';

export type DonutSlice = { label: string; value: number; color: string };

/**
 * Donut with a total in the middle. The legend is drawn by the dashboard beside
 * the chart, so the numbers stay readable as text.
 */
export default function ProgramDonutChart({
    slices,
    centerLabel,
    centerValue,
    unit,
    height = 220,
}: {
    slices: DonutSlice[];
    centerLabel: string;
    centerValue: string;
    unit: string;
    height?: number;
}) {
    const options: ApexOptions = {
        chart: {
            type: 'donut',
            height,
            fontFamily: 'Poppins, sans-serif',
            foreColor: '#697386',
            animations: { enabled: true, speed: 400 },
        },
        labels: slices.map((slice) => slice.label),
        colors: slices.map((slice) => slice.color),
        stroke: { width: 2, colors: ['#ffffff'] },
        dataLabels: { enabled: false },
        legend: { show: false },
        states: {
            hover: { filter: { type: 'none' } },
            active: { filter: { type: 'none' } },
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '72%',
                    labels: {
                        show: true,
                        name: { show: true, offsetY: 22, fontSize: '12px' },
                        value: {
                            fontSize: '26px',
                            fontWeight: 600,
                            color: '#101828',
                            offsetY: -10,
                        },
                        total: {
                            show: true,
                            showAlways: true,
                            label: centerLabel,
                            fontSize: '12px',
                            color: '#697386',
                            formatter: () => centerValue,
                        },
                    },
                },
            },
        },
        tooltip: {
            y: { formatter: (value: number) => `${value} ${unit}` },
        },
    };

    return (
        <Chart
            type="donut"
            width="100%"
            height={height}
            options={options}
            series={slices.map((slice) => slice.value)}
        />
    );
}
