import type { ApexOptions } from 'apexcharts';
import Chart from 'react-apexcharts';

export type OrganizationCount = { label: string; total: number };

const COLORS = [
    '#2547F9',
    '#16834A',
    '#F79009',
    '#7A5AF8',
    '#06AED4',
    '#D92D20',
    '#98A2B3',
];
const TOP = 6;

/**
 * Donut of participants per LOB or division: the biggest ones get a slice,
 * the rest are grouped as "Lainnya" so the chart stays readable.
 */
export default function OrganizationChart({
    rows,
    height = 320,
}: {
    rows: OrganizationCount[];
    height?: number;
}) {
    const filled = rows.filter((row) => row.total > 0);
    const top = filled.slice(0, TOP);
    const rest = filled.slice(TOP);
    const slices = rest.length
        ? [
              ...top,
              {
                  label: `Lainnya (${rest.length})`,
                  total: rest.reduce((sum, row) => sum + row.total, 0),
              },
          ]
        : top;
    const total = slices.reduce((sum, row) => sum + row.total, 0);

    const options: ApexOptions = {
        chart: {
            type: 'donut',
            height,
            fontFamily: 'Poppins, sans-serif',
            foreColor: '#697386',
        },
        labels: slices.map((row) => row.label),
        colors: COLORS,
        stroke: { width: 2, colors: ['#fff'] },
        dataLabels: { enabled: false },
        plotOptions: {
            pie: {
                donut: {
                    size: '68%',
                    labels: {
                        show: true,
                        name: { show: false },
                        value: {
                            fontSize: '28px',
                            fontWeight: 600,
                            color: '#101828',
                            offsetY: 8,
                        },
                        total: {
                            show: true,
                            showAlways: true,
                            label: 'Peserta',
                            formatter: () => String(total),
                        },
                    },
                },
            },
        },
        legend: {
            position: 'right',
            fontSize: '13px',
            markers: { size: 6, shape: 'circle' },
            itemMargin: { vertical: 4 },
            formatter: (label: string, opts) =>
                `${label}  <b>${slices[opts?.seriesIndex as number]?.total ?? 0}</b>`,
        },
        tooltip: { y: { formatter: (value: number) => `${value} peserta` } },
        responsive: [
            {
                breakpoint: 640,
                options: { legend: { position: 'bottom' } },
            },
        ],
    };

    return (
        <Chart
            type="donut"
            width="100%"
            height={height}
            options={options}
            series={slices.map((row) => row.total)}
        />
    );
}
