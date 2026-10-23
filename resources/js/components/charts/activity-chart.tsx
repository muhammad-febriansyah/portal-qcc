import type { ApexOptions } from 'apexcharts';
import { format, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import Chart from 'react-apexcharts';

export type ActivitySeries = {
    days: string[];
    login: number[];
    changes: number[];
    failed: number[];
};

const COLORS = {
    login: '#2547F9',
    changes: '#16834A',
    failed: '#D92D20',
};

type Props = {
    series: ActivitySeries;
    height?: number;
};

/**
 * Daily activity as flat colour areas (design system: no big gradients or glow).
 * Loaded lazily by the dashboard so the chart library stays out of other pages.
 */
export default function ActivityChart({ series, height = 360 }: Props) {
    const points = (values: number[]): { x: number; y: number }[] =>
        series.days.map((day, index) => ({
            x: parseISO(day).getTime(),
            y: values[index] ?? 0,
        }));

    const options: ApexOptions = {
        chart: {
            type: 'area',
            height,
            toolbar: { show: false },
            zoom: { enabled: false },
            fontFamily: 'Poppins, sans-serif',
            foreColor: '#697386',
            animations: { enabled: true, speed: 400 },
        },
        colors: [COLORS.login, COLORS.changes, COLORS.failed],
        stroke: { curve: 'smooth', width: [2.5, 2.5, 2], lineCap: 'round' },
        fill: { type: 'solid', opacity: [0.12, 0.1, 0] },
        dataLabels: { enabled: false },
        markers: { size: 0, hover: { size: 5 } },
        grid: {
            borderColor: '#E7EAF2',
            strokeDashArray: 4,
            padding: { left: 8, right: 12 },
        },
        xaxis: {
            type: 'datetime',
            tickAmount: 6,
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: {
                datetimeUTC: false,
                rotate: 0,
                style: { fontSize: '12px' },
                formatter: (value: string) =>
                    format(new Date(Number(value)), 'd MMM', {
                        locale: indonesianLocale,
                    }),
            },
            tooltip: { enabled: false },
        },
        yaxis: {
            min: 0,
            forceNiceScale: true,
            labels: {
                style: { fontSize: '12px' },
                formatter: (value: number) => String(Math.round(value)),
            },
        },
        legend: {
            position: 'top',
            horizontalAlign: 'right',
            fontSize: '13px',
            markers: { size: 6, shape: 'circle' },
            itemMargin: { horizontal: 10 },
        },
        tooltip: {
            shared: true,
            intersect: false,
            x: {
                formatter: (value: number) =>
                    format(new Date(value), 'EEEE, d MMMM yyyy', {
                        locale: indonesianLocale,
                    }),
            },
        },
    };

    return (
        <Chart
            type="area"
            width="100%"
            height={height}
            options={options}
            series={[
                { name: 'Login', data: points(series.login) },
                { name: 'Perubahan data', data: points(series.changes) },
                { name: 'Login gagal', data: points(series.failed) },
            ]}
        />
    );
}
