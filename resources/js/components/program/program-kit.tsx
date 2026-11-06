import { lazy, Suspense } from 'react';
import type { DonutSlice } from '@/components/charts/program-donut-chart';
import {
    ChartFallback,
    useMounted,
} from '@/components/dashboard/dashboard-kit';
import { cn } from '@/lib/utils';

const ProgramDonutChart = lazy(
    () => import('@/components/charts/program-donut-chart'),
);

/** Colors shared by the program charts: validated portal order, then status colors. */
export const programColors = {
    primary: '#0191C2',
    tan: '#C4844F',
    violet: '#7A5AF8',
    good: '#16834A',
    warning: '#F79009',
    critical: '#D92D20',
    muted: '#D0D5DD',
    neutral: '#98A2B3',
} as const;

export function LegendRow({
    color,
    label,
    value,
}: {
    color: string;
    label: string;
    value: string;
}) {
    return (
        <li className="flex items-start justify-between gap-3 text-sm">
            <span className="flex min-w-0 items-start gap-2">
                <span
                    aria-hidden="true"
                    className="mt-1.5 size-2.5 shrink-0 rounded-full"
                    style={{ backgroundColor: color }}
                />
                <span className="text-foreground">{label}</span>
            </span>
            <span className="shrink-0 font-medium text-foreground tabular-nums">
                {value}
            </span>
        </li>
    );
}

/** Horizontal bars as plain HTML, so every value stays readable as text. */
export function BarList({
    rows,
    label,
}: {
    rows: { name: string; value: number; display: string }[];
    label: string;
}) {
    const max = Math.max(1, ...rows.map((row) => row.value));

    return (
        <ul className="grid gap-2.5" aria-label={label}>
            {rows.map((row) => (
                <li
                    key={row.name}
                    className="grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)_auto] items-center gap-3 text-sm"
                >
                    <span
                        className={cn(
                            'truncate',
                            row.value === 0
                                ? 'text-muted-foreground'
                                : 'text-foreground',
                        )}
                        title={row.name}
                    >
                        {row.name}
                    </span>
                    <span className="h-2 overflow-hidden rounded-full bg-muted">
                        <span
                            className="block h-full rounded-full bg-primary"
                            style={{ width: `${(row.value / max) * 100}%` }}
                        />
                    </span>
                    <span className="min-w-12 text-right font-medium text-foreground tabular-nums">
                        {row.display}
                    </span>
                </li>
            ))}
        </ul>
    );
}

/** A donut with its legend as text beside it (below it on narrow panels). */
export function DonutWithLegend({
    slices,
    centerLabel,
    centerValue,
    unit,
    label,
    formatValue,
}: {
    slices: DonutSlice[];
    centerLabel: string;
    centerValue: string;
    unit: string;
    label: string;
    formatValue: (slice: DonutSlice) => string;
}) {
    const isMounted = useMounted();

    return (
        <div className="grid items-center gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)] xl:grid-cols-1 2xl:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
            <div role="img" aria-label={label}>
                {isMounted ? (
                    <Suspense fallback={<ChartFallback height={220} />}>
                        <ProgramDonutChart
                            slices={slices}
                            centerLabel={centerLabel}
                            centerValue={centerValue}
                            unit={unit}
                        />
                    </Suspense>
                ) : (
                    <ChartFallback height={220} />
                )}
            </div>
            <ul className="grid gap-3">
                {slices.map((slice) => (
                    <LegendRow
                        key={slice.label}
                        color={slice.color}
                        label={slice.label}
                        value={formatValue(slice)}
                    />
                ))}
            </ul>
        </div>
    );
}
