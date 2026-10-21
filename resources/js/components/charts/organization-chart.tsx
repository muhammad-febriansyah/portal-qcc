import type { ApexOptions } from "apexcharts";
import Chart from "react-apexcharts";

export type OrganizationCount = { label: string; total: number };

/**
 * Horizontal bars: participants per LOB or division, largest first.
 */
export default function OrganizationChart({
    rows,
}: {
    rows: OrganizationCount[];
}) {
    const height = Math.max(240, rows.length * 34 + 40);

    const options: ApexOptions = {
        chart: {
            type: "bar",
            height,
            toolbar: { show: false },
            fontFamily: "Poppins, sans-serif",
            foreColor: "#697386",
        },
        colors: ["#2547F9"],
        plotOptions: {
            bar: { horizontal: true, barHeight: "58%", borderRadius: 3 },
        },
        dataLabels: {
            enabled: true,
            offsetX: 4,
            style: { fontSize: "12px", colors: ["#344054"] },
        },
        grid: {
            borderColor: "#E7EAF2",
            strokeDashArray: 4,
            xaxis: { lines: { show: true } },
            yaxis: { lines: { show: false } },
        },
        xaxis: {
            categories: rows.map((row) => row.label),
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: {
                formatter: (value: string) => String(Math.round(Number(value))),
            },
        },
        yaxis: { labels: { maxWidth: 160, style: { fontSize: "12px" } } },
        legend: { show: false },
        tooltip: { y: { formatter: (value: number) => `${value} peserta` } },
    };

    return (
        <Chart
            type="bar"
            width="100%"
            height={height}
            options={options}
            series={[{ name: "Peserta", data: rows.map((row) => row.total) }]}
        />
    );
}
