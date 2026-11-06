import { useState } from 'react';
import { numberFormat } from '@/components/dashboard/dashboard-kit';
import { Card } from '@/components/ui/card';

/**
 * Colors of the program charts: the validated portal order for categories,
 * gray for "the rest", green and red only for on-time and overdue.
 */
export const programColors = {
    primary: '#0191C2',
    tan: '#C4844F',
    violet: '#7A5AF8',
    green: '#16834A',
    orange: '#F79009',
    magenta: '#C11574',
    rest: '#98A2B3',
    track: '#E8EEF2',
    good: '#16834A',
    critical: '#D92D20',
} as const;

export const categoryColors = [
    programColors.primary,
    programColors.tan,
    programColors.violet,
    programColors.green,
    programColors.orange,
    programColors.magenta,
];

export function percentOf(value: number, total: number): number {
    return total > 0 ? Math.round((value / total) * 100) : 0;
}

export type Stat = {
    label: string;
    value: string;
    /** Small text beside the value, such as a percentage. */
    suffix?: string;
    helper: string;
};

/** The headline numbers of a phase as one card split into columns. */
export function StatStrip({ stats }: { stats: Stat[] }) {
    return (
        <Card className="gap-0 py-0">
            <dl className="grid grid-cols-2 gap-px bg-border/70 xl:grid-cols-5">
                {stats.map((stat) => (
                    <div
                        key={stat.label}
                        className="grid content-start gap-1 bg-card px-5 py-4 last:odd:col-span-2 xl:last:odd:col-span-1"
                    >
                        <dt className="truncate text-xs font-medium text-muted-foreground">
                            {stat.label}
                        </dt>
                        <dd className="flex items-baseline gap-1.5">
                            <span className="text-2xl leading-tight font-semibold text-foreground tabular-nums">
                                {stat.value}
                            </span>
                            {stat.suffix && (
                                <span className="text-sm text-muted-foreground tabular-nums">
                                    {stat.suffix}
                                </span>
                            )}
                        </dd>
                        <dd className="text-xs text-muted-foreground">
                            {stat.helper}
                        </dd>
                    </div>
                ))}
            </dl>
        </Card>
    );
}

/** A single share as a big number and a thin meter, with both sides named. */
export function Meter({
    percent,
    caption,
    filledLabel,
    restLabel,
    tone = 'primary',
    label,
}: {
    percent: number;
    caption: string;
    filledLabel: string;
    restLabel: string;
    tone?: 'primary' | 'good';
    label: string;
}) {
    const clamped = Math.min(100, Math.max(0, percent));

    return (
        <div className="grid gap-3">
            <div className="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                <span className="text-3xl leading-none font-semibold text-foreground tabular-nums">
                    {clamped}%
                </span>
                <span className="text-sm text-muted-foreground">{caption}</span>
            </div>
            <div
                role="progressbar"
                aria-label={label}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={clamped}
                className="h-2 overflow-hidden rounded-full"
                style={{ backgroundColor: programColors.track }}
            >
                <div
                    className="h-full rounded-full transition-[width] duration-500"
                    style={{
                        width: `${clamped}%`,
                        backgroundColor:
                            tone === 'good'
                                ? programColors.good
                                : programColors.primary,
                    }}
                />
            </div>
            <div className="flex justify-between gap-3 text-xs text-muted-foreground">
                <span>{filledLabel}</span>
                <span className="text-right">{restLabel}</span>
            </div>
        </div>
    );
}

export type Segment = {
    key: string;
    label: string;
    count: number;
    color: string;
};

/** Shares of one whole as a single bar, with a legend that carries the numbers. */
export function StackedBar({
    segments,
    label,
}: {
    segments: Segment[];
    label: string;
}) {
    const total = segments.reduce((sum, segment) => sum + segment.count, 0);
    const visible = segments.filter((segment) => segment.count > 0);

    return (
        <div className="grid gap-4">
            <div
                role="img"
                aria-label={label}
                className="flex h-2.5 gap-0.5 overflow-hidden rounded-full"
                style={{ backgroundColor: programColors.track }}
            >
                {visible.map((segment) => (
                    <span
                        key={segment.key}
                        className="h-full first:rounded-l-full last:rounded-r-full"
                        style={{
                            width: `${(segment.count / Math.max(total, 1)) * 100}%`,
                            backgroundColor: segment.color,
                        }}
                    />
                ))}
            </div>
            <ul className="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                {segments.map((segment) => (
                    <li
                        key={segment.key}
                        className="flex items-start justify-between gap-3 text-sm"
                    >
                        <span className="flex min-w-0 items-start gap-2">
                            <span
                                aria-hidden="true"
                                className="mt-1.5 size-2 shrink-0 rounded-full"
                                style={{ backgroundColor: segment.color }}
                            />
                            <span className="text-foreground">
                                {segment.label}
                            </span>
                        </span>
                        <span className="shrink-0 text-foreground tabular-nums">
                            <span className="font-medium">
                                {numberFormat.format(segment.count)}
                            </span>
                            <span className="ml-1.5 text-muted-foreground">
                                {percentOf(segment.count, total)}%
                            </span>
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

/**
 * Horizontal bars as plain HTML, busiest first. Rows without data fold into one
 * line, and long lists show their top rows until the reader asks for all.
 */
export function BarList({
    rows,
    label,
    limit = 8,
    emptyLabel,
}: {
    rows: { name: string; value: number; display: string }[];
    label: string;
    limit?: number;
    /** Text for the folded rows without data, e.g. "belum ada proposal". */
    emptyLabel?: string;
}) {
    const [showAll, setShowAll] = useState(false);
    const filled = rows.filter((row) => row.value > 0);
    const empty = rows.length - filled.length;
    const shown = showAll ? filled : filled.slice(0, limit);
    const hidden = filled.length - shown.length;
    const max = Math.max(1, ...filled.map((row) => row.value));

    return (
        <div className="grid gap-1">
            {filled.length > 0 ? (
                <ul className="grid" aria-label={label}>
                    {shown.map((row) => (
                        <li
                            key={row.name}
                            className="grid grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)_auto] items-center gap-3 py-1.5 text-sm"
                        >
                            <span className="text-foreground">{row.name}</span>
                            <span
                                className="h-1.5 overflow-hidden rounded-full"
                                style={{ backgroundColor: programColors.track }}
                            >
                                <span
                                    className="block h-full rounded-full bg-primary"
                                    style={{
                                        width: `${(row.value / max) * 100}%`,
                                    }}
                                />
                            </span>
                            <span className="min-w-10 text-right text-foreground tabular-nums">
                                {row.display}
                            </span>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="py-2 text-sm text-muted-foreground">
                    Belum ada data.
                </p>
            )}
            {(hidden > 0 ||
                (showAll && filled.length > limit) ||
                (empty > 0 && emptyLabel)) && (
                <div className="mt-1 flex flex-wrap items-center justify-between gap-2 border-t border-border/70 pt-2.5 text-xs text-muted-foreground">
                    <span>
                        {[
                            hidden > 0
                                ? `${hidden} lainnya tidak ditampilkan`
                                : null,
                            empty > 0 && emptyLabel
                                ? `${empty} ${emptyLabel}`
                                : null,
                        ]
                            .filter(Boolean)
                            .join(' · ')}
                    </span>
                    {filled.length > limit && (
                        <button
                            type="button"
                            onClick={() => setShowAll((current) => !current)}
                            className="rounded font-medium text-primary hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            {showAll
                                ? 'Tampilkan lebih sedikit'
                                : 'Tampilkan semua'}
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}
