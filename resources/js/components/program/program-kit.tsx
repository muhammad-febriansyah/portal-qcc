import { ChevronRight } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { numberFormat } from '@/components/dashboard/dashboard-kit';
import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';

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

/** Hover and focus look of a dashboard figure that opens its data. */
export const selectableClass =
    'cursor-pointer rounded-md text-left transition-colors hover:bg-muted/70 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';

export function percentOf(value: number, total: number): number {
    return total > 0 ? Math.round((value / total) * 100) : 0;
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
    onSelect?: () => void;
};

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
    rows: {
        name: string;
        value: number;
        display: string;
        onSelect?: () => void;
    }[];
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
                    {shown.map((row) => {
                        const content = (
                            <>
                                <span className="text-foreground">
                                    {row.name}
                                </span>
                                <span
                                    className="h-1.5 overflow-hidden rounded-full"
                                    style={{
                                        backgroundColor: programColors.track,
                                    }}
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
                            </>
                        );
                        const grid =
                            'grid w-full grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)_auto] items-center gap-3 py-1.5 text-sm';

                        return (
                            <li key={row.name}>
                                {row.onSelect ? (
                                    <button
                                        type="button"
                                        onClick={row.onSelect}
                                        title={`Lihat data ${row.name}`}
                                        className={cn(
                                            grid,
                                            selectableClass,
                                            '-mx-2 w-[calc(100%+1rem)] px-2',
                                        )}
                                    >
                                        {content}
                                    </button>
                                ) : (
                                    <div className={grid}>{content}</div>
                                )}
                            </li>
                        );
                    })}
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

/** A compact panel: small title, optional note, little padding. */
export function Panel({
    title,
    description,
    action,
    children,
    className,
}: {
    title: string;
    description?: string;
    action?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <Card className={cn('min-w-0 gap-3 px-5 py-4', className)}>
            <div className="flex items-start justify-between gap-3">
                <div className="grid min-w-0 gap-0.5">
                    <h3 className="text-sm font-semibold text-foreground">
                        {title}
                    </h3>
                    {description && (
                        <p className="text-xs text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
                {action}
            </div>
            {children}
        </Card>
    );
}

/** A thin progress ring drawn in SVG, sized for the highlight card. */
function Ring({ percent, size = 120 }: { percent: number; size?: number }) {
    const stroke = 9;
    const radius = (size - stroke) / 2;
    const circumference = 2 * Math.PI * radius;
    const clamped = Math.min(100, Math.max(0, percent));

    return (
        <svg
            width={size}
            height={size}
            viewBox={`0 0 ${size} ${size}`}
            aria-hidden="true"
            className="shrink-0 -rotate-90"
        >
            <circle
                cx={size / 2}
                cy={size / 2}
                r={radius}
                fill="none"
                stroke="currentColor"
                strokeOpacity={0.22}
                strokeWidth={stroke}
            />
            <circle
                cx={size / 2}
                cy={size / 2}
                r={radius}
                fill="none"
                stroke="currentColor"
                strokeWidth={stroke}
                strokeLinecap="round"
                strokeDasharray={circumference}
                strokeDashoffset={circumference * (1 - clamped / 100)}
                className="transition-[stroke-dashoffset] duration-700"
            />
        </svg>
    );
}

/**
 * The one colored card of a phase: its key share as a ring, with a few
 * supporting numbers beside it.
 */
export function HighlightCard({
    title,
    percent,
    caption,
    rows,
}: {
    title: string;
    percent: number;
    caption: string;
    rows: { label: string; value: string; onSelect?: () => void }[];
}) {
    return (
        <section
            aria-label={title}
            className="flex min-w-0 flex-col gap-5 rounded-xl bg-primary px-5 py-5 text-primary-foreground sm:flex-row sm:items-center xl:flex-col xl:items-stretch 2xl:flex-row 2xl:items-center"
        >
            <div className="relative mx-auto flex shrink-0 items-center justify-center sm:mx-0 xl:mx-auto 2xl:mx-0">
                <Ring percent={percent} />
                <span className="absolute text-center">
                    <span className="block text-3xl leading-none font-semibold tabular-nums">
                        {Math.min(100, Math.max(0, percent))}%
                    </span>
                </span>
            </div>
            <div className="grid min-w-0 flex-1 gap-3">
                <div className="grid gap-0.5">
                    <h3 className="text-sm font-semibold">{title}</h3>
                    <p className="text-xs text-primary-foreground/80">
                        {caption}
                    </p>
                </div>
                <dl className="grid divide-y divide-primary-foreground/15 border-t border-primary-foreground/15 text-sm">
                    {rows.map((row) => (
                        <div
                            key={row.label}
                            className={cn(
                                'relative flex items-center justify-between gap-3 py-1.5',
                                row.onSelect &&
                                    '-mx-2 rounded-md px-2 transition-colors focus-within:ring-2 focus-within:ring-primary-foreground/60 hover:bg-primary-foreground/10',
                            )}
                        >
                            <dt className="text-primary-foreground/80">
                                {row.onSelect ? (
                                    <button
                                        type="button"
                                        onClick={row.onSelect}
                                        className="text-left after:absolute after:inset-0 focus-visible:outline-none"
                                    >
                                        {row.label}
                                    </button>
                                ) : (
                                    row.label
                                )}
                            </dt>
                            <dd className="flex items-center gap-1 font-semibold tabular-nums">
                                {row.value}
                                {row.onSelect && (
                                    <ChevronRight
                                        aria-hidden="true"
                                        className="size-3.5 text-primary-foreground/70"
                                    />
                                )}
                            </dd>
                        </div>
                    ))}
                </dl>
            </div>
        </section>
    );
}

export type Tile = {
    label: string;
    value: string;
    suffix?: string;
    helper: string;
    icon: LucideIcon;
    /** One of the category colors, used lightly behind the icon. */
    color: string;
    onSelect?: () => void;
};

/** Headline numbers as four light tiles, each with a small colored icon. */
export function StatTiles({ tiles }: { tiles: Tile[] }) {
    return (
        <dl className="grid grid-cols-2 gap-3 lg:grid-cols-4">
            {tiles.map(({ icon: Icon, ...tile }) => (
                <Card
                    key={tile.label}
                    className={cn(
                        'relative min-w-0 gap-2 px-4 py-3.5',
                        tile.onSelect &&
                            'transition-colors focus-within:ring-2 focus-within:ring-ring hover:bg-muted/50',
                    )}
                >
                    <dt className="flex items-center gap-2 text-xs font-medium text-muted-foreground">
                        <span
                            className="flex size-6 shrink-0 items-center justify-center rounded-md"
                            style={{
                                backgroundColor: `${tile.color}1A`,
                                color: tile.color,
                            }}
                        >
                            <Icon className="size-3.5" aria-hidden="true" />
                        </span>
                        <span className="min-w-0 leading-tight">
                            {tile.onSelect ? (
                                <button
                                    type="button"
                                    onClick={tile.onSelect}
                                    className="text-left after:absolute after:inset-0 after:rounded-xl focus-visible:outline-none"
                                >
                                    {tile.label}
                                </button>
                            ) : (
                                tile.label
                            )}
                        </span>
                        {tile.onSelect && (
                            <ChevronRight
                                aria-hidden="true"
                                className="ml-auto size-3.5 shrink-0 text-muted-foreground/70"
                            />
                        )}
                    </dt>
                    <dd className="flex items-baseline gap-1.5">
                        <span className="text-2xl leading-tight font-semibold text-foreground tabular-nums">
                            {tile.value}
                        </span>
                        {tile.suffix && (
                            <span className="text-sm text-muted-foreground tabular-nums">
                                {tile.suffix}
                            </span>
                        )}
                    </dd>
                    <dd className="line-clamp-2 text-xs text-muted-foreground">
                        {tile.helper}
                    </dd>
                </Card>
            ))}
        </dl>
    );
}

/** Categories as colored bars on one line each: name, bar, count and share. */
export function ColorBars({
    segments,
    label,
}: {
    segments: Segment[];
    label: string;
}) {
    const total = segments.reduce((sum, segment) => sum + segment.count, 0);
    const max = Math.max(1, ...segments.map((segment) => segment.count));

    return (
        <ul className="grid" aria-label={label}>
            {segments.map((segment) => {
                const content = (
                    <>
                        <span className="flex min-w-0 items-start gap-2">
                            <span
                                aria-hidden="true"
                                className="mt-1.5 size-2 shrink-0 rounded-full"
                                style={{ backgroundColor: segment.color }}
                            />
                            <span className="leading-tight text-foreground">
                                {segment.label}
                            </span>
                        </span>
                        <span
                            className="block h-1.5 overflow-hidden rounded-full"
                            style={{ backgroundColor: programColors.track }}
                        >
                            <span
                                className="block h-full rounded-full"
                                style={{
                                    width: `${(segment.count / max) * 100}%`,
                                    backgroundColor: segment.color,
                                }}
                            />
                        </span>
                        <span className="min-w-14 text-right tabular-nums">
                            <span className="font-medium text-foreground">
                                {numberFormat.format(segment.count)}
                            </span>
                            <span className="ml-1 text-xs text-muted-foreground">
                                {percentOf(segment.count, total)}%
                            </span>
                        </span>
                    </>
                );
                const grid =
                    'grid w-full grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)_auto] items-center gap-3 py-1.5 text-sm';

                return (
                    <li key={segment.key}>
                        {segment.onSelect && segment.count > 0 ? (
                            <button
                                type="button"
                                onClick={segment.onSelect}
                                title={`Lihat data ${segment.label}`}
                                className={cn(
                                    grid,
                                    selectableClass,
                                    '-mx-2 w-[calc(100%+1rem)] px-2',
                                )}
                            >
                                {content}
                            </button>
                        ) : (
                            <div className={grid}>{content}</div>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}

/** Small vertical bars with the value on top and a short name below. */
export function ColumnBars({
    columns,
    label,
    height = 112,
    max: fixedMax,
}: {
    columns: {
        key: string;
        label: string;
        value: number;
        display: string;
        title?: string;
        onSelect?: () => void;
    }[];
    label: string;
    height?: number;
    /** Fixed top of the scale, e.g. 100 for percentages. */
    max?: number;
}) {
    const max =
        fixedMax ?? Math.max(1, ...columns.map((column) => column.value));

    return (
        <ol
            aria-label={label}
            className="grid items-end gap-2"
            style={{
                gridTemplateColumns: `repeat(${columns.length}, minmax(0, 1fr))`,
            }}
        >
            {columns.map((column) => {
                const content = (
                    <>
                        <span className="text-xs font-semibold text-foreground tabular-nums">
                            {column.display}
                        </span>
                        <span
                            className="flex items-end overflow-hidden rounded-md"
                            style={{
                                height,
                                backgroundColor: programColors.track,
                            }}
                        >
                            <span
                                className="block w-full rounded-md bg-primary transition-[height] duration-500"
                                style={{
                                    height: `${Math.min(100, (column.value / max) * 100)}%`,
                                }}
                            />
                        </span>
                        <span className="line-clamp-2 text-[11px] leading-tight text-muted-foreground">
                            {column.label}
                        </span>
                    </>
                );

                return (
                    <li
                        key={column.key}
                        title={column.title}
                        className="min-w-0"
                    >
                        {column.onSelect && column.value > 0 ? (
                            <button
                                type="button"
                                onClick={column.onSelect}
                                className={cn(
                                    'grid w-full gap-1.5 p-1 text-center',
                                    selectableClass,
                                )}
                            >
                                {content}
                            </button>
                        ) : (
                            <div className="grid gap-1.5 text-center">
                                {content}
                            </div>
                        )}
                    </li>
                );
            })}
        </ol>
    );
}
