import type { ApexOptions } from 'apexcharts';
import { format, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import Chart from 'react-apexcharts';

export type ProgramTrendPoint = {
    date: string;
    registered: number;
    submitted: number;
};

/** Running totals of registrations and submitted proposals, one point per day. */
export default function ProgramTrendChart({
    points,
    height = 280,
}: {
    points: ProgramTrendPoint[];
    height?: number;
}) {
    const series = (key: 'registered' | 'submitted') =>
        points.map((point) => point[key]);

    const options: ApexOptions = {
        chart: {
            type: 'line',
            height,
            toolbar: { show: false },
            zoom: { enabled: false },
            fontFamily: 'Poppins, sans-serif',
            foreColor: '#697386',
            animations: { enabled: true, speed: 400 },
        },
        colors: ['#0191C2', '#C4844F'],
        stroke: { curve: 'straight', width: 2, lineCap: 'round' },
        dataLabels: { enabled: false },
        markers: {
            size: points.length <= 14 ? 3 : 0,
            strokeWidth: 2,
            strokeColors: '#ffffff',
            hover: { size: 6 },
        },
        grid: {
            borderColor: '#EEF2F5',
            strokeDashArray: 4,
            padding: { left: 8, right: 16 },
        },
        xaxis: {
            type: 'category',
            // Long phases show about eight day labels instead of every day.
            tickAmount: Math.max(1, Math.min(points.length - 1, 8)),
            categories: points.map((point) =>
                format(parseISO(point.date), 'd MMM', {
                    locale: indonesianLocale,
                }),
            ),
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: {
                rotate: 0,
                hideOverlappingLabels: true,
                style: { fontSize: '12px' },
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
            horizontalAlign: 'left',
            fontSize: '13px',
            markers: { size: 6, shape: 'circle' },
            itemMargin: { horizontal: 10 },
        },
        tooltip: {
            shared: true,
            intersect: false,
            x: {
                formatter: (
                    _value: number,
                    opts?: { dataPointIndex?: number },
                ) => {
                    const point = points[opts?.dataPointIndex ?? -1];

                    return point
                        ? format(parseISO(point.date), 'EEEE, d MMMM yyyy', {
                              locale: indonesianLocale,
                          })
                        : '';
                },
            },
        },
    };

    return (
        <Chart
            type="line"
            width="100%"
            height={height}
            options={options}
            series={[
                {
                    name: 'Registrasi (akumulasi)',
                    data: series('registered'),
                },
                {
                    name: 'Submit proposal (akumulasi)',
                    data: series('submitted'),
                },
            ]}
        />
    );
}
