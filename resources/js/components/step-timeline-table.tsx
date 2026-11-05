import {
    addDays,
    addMonths,
    endOfMonth,
    format,
    getDaysInMonth,
    isValid,
    parseISO,
    startOfMonth,
} from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import { cn } from '@/lib/utils';

export type StepTimelineRow = {
    number: number;
    name: string;
    date: string | null;
    deliverable?: string | null;
};

/** Months wider than this are cut, so a typo in a year cannot draw a huge table. */
const MAX_MONTHS = 24;
const WEEKS_PER_MONTH = 4;

function parseDate(value: string | null | undefined): Date | null {
    if (!value) return null;

    const date = parseISO(value);

    return isValid(date) ? date : null;
}

function formatDate(value: string | null): string {
    const date = parseDate(value);

    return date
        ? format(date, 'd MMM yyyy', { locale: indonesianLocale })
        : 'Belum diisi';
}

/**
 * A step runs from the day after the previous step's target (the batch start for
 * step 1) until its own target date.
 */
function stepRanges(
    rows: StepTimelineRow[],
    startDate: string | null | undefined,
): ({ start: Date; end: Date } | null)[] {
    const batchStart = parseDate(startDate);
    let previousEnd: Date | null = null;

    return rows.map((row) => {
        const end = parseDate(row.date);

        if (!end) return null;

        let start = previousEnd ? addDays(previousEnd, 1) : (batchStart ?? end);

        if (start > end) start = end;

        previousEnd = end;

        return { start, end };
    });
}

/** Week 1-3 are seven days each; week 4 takes the rest of the month. */
function weekRange(month: Date, week: number): { start: Date; end: Date } {
    const start = addDays(month, (week - 1) * 7);
    const end =
        week === WEEKS_PER_MONTH
            ? endOfMonth(month)
            : addDays(month, Math.min(week * 7, getDaysInMonth(month)) - 1);

    return { start, end };
}

/** Planned QCC steps as a week-by-week chart with their target date and deliverable. */
export function TimelineTable({
    rows,
    startDate,
}: {
    rows: StepTimelineRow[];
    startDate?: string | null;
}) {
    const ranges = stepRanges(rows, startDate);
    const planned = ranges.filter(
        (range): range is { start: Date; end: Date } => range !== null,
    );
    const months: Date[] = [];

    if (planned.length > 0) {
        const first = startOfMonth(
            new Date(
                Math.min(...planned.map((range) => range.start.getTime())),
            ),
        );
        const last = startOfMonth(
            new Date(Math.max(...planned.map((range) => range.end.getTime()))),
        );

        for (
            let month = first;
            month <= last && months.length < MAX_MONTHS;
            month = addMonths(month, 1)
        ) {
            months.push(month);
        }
    }

    return (
        <div className="max-w-full overflow-x-auto rounded-lg border bg-card">
            <table className="w-full border-collapse text-xs">
                <thead className="bg-muted/50 text-muted-foreground">
                    <tr>
                        <th
                            scope="col"
                            rowSpan={2}
                            className="sticky left-0 z-10 min-w-48 border-r bg-muted px-3 py-2 text-left font-semibold"
                        >
                            Phase
                        </th>
                        {months.map((month) => (
                            <th
                                key={month.toISOString()}
                                scope="colgroup"
                                colSpan={WEEKS_PER_MONTH}
                                className="border-r border-b px-1 py-1.5 text-center font-semibold whitespace-nowrap"
                            >
                                {format(month, 'MMMM yyyy', {
                                    locale: indonesianLocale,
                                })}
                            </th>
                        ))}
                        <th
                            scope="col"
                            rowSpan={2}
                            className="min-w-28 border-r px-3 py-2 text-left font-semibold"
                        >
                            Target selesai
                        </th>
                        <th
                            scope="col"
                            rowSpan={2}
                            className="min-w-40 px-3 py-2 text-left font-semibold"
                        >
                            Deliverable results
                        </th>
                    </tr>
                    <tr>
                        {months.flatMap((month) =>
                            Array.from(
                                { length: WEEKS_PER_MONTH },
                                (_, index) => (
                                    <th
                                        key={`${month.toISOString()}-${index}`}
                                        scope="col"
                                        className="w-7 min-w-7 border-r px-0 py-1 text-center font-medium tabular-nums"
                                    >
                                        {index + 1}
                                    </th>
                                ),
                            ),
                        )}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, rowIndex) => {
                        const range = ranges[rowIndex];

                        return (
                            <tr key={row.number} className="border-t">
                                <th
                                    scope="row"
                                    className="sticky left-0 z-10 border-r bg-card px-3 py-2 text-left font-normal text-foreground"
                                >
                                    <span className="font-semibold">
                                        {row.number}.
                                    </span>{' '}
                                    {row.name}
                                </th>
                                {months.flatMap((month) =>
                                    Array.from(
                                        { length: WEEKS_PER_MONTH },
                                        (_, index) => {
                                            const week = weekRange(
                                                month,
                                                index + 1,
                                            );
                                            const active =
                                                range !== null &&
                                                range.start <= week.end &&
                                                range.end >= week.start;

                                            return (
                                                <td
                                                    key={`${month.toISOString()}-${index}`}
                                                    className="border-r p-0.5"
                                                >
                                                    <span
                                                        className={cn(
                                                            'block h-5 rounded-sm',
                                                            active &&
                                                                'bg-primary/80',
                                                        )}
                                                    />
                                                </td>
                                            );
                                        },
                                    ),
                                )}
                                <td
                                    className={cn(
                                        'border-r px-3 py-2 whitespace-nowrap tabular-nums',
                                        !row.date && 'text-muted-foreground',
                                    )}
                                >
                                    {formatDate(row.date)}
                                </td>
                                <td
                                    className={cn(
                                        'px-3 py-2 text-foreground',
                                        !row.deliverable?.trim() &&
                                            'text-muted-foreground',
                                    )}
                                >
                                    {row.deliverable?.trim() || '—'}
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
            {months.length === 0 && (
                <p className="border-t px-3 py-3 text-xs text-muted-foreground">
                    Isi target tanggal step untuk melihat grafik timeline.
                </p>
            )}
        </div>
    );
}
