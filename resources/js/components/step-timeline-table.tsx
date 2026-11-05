import { format, isValid, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';

export type StepTimelineRow = {
    number: number;
    name: string;
    date: string | null;
};

function formatDate(value: string | null): string {
    if (!value) return 'Belum diisi';

    const date = parseISO(value);

    return isValid(date)
        ? format(date, 'd MMMM yyyy', { locale: indonesianLocale })
        : value;
}

/** Planned target date for each QCC step of a proposal. */
export function TimelineTable({ rows }: { rows: StepTimelineRow[] }) {
    return (
        <div className="overflow-hidden rounded-lg border bg-card">
            <table className="w-full text-sm">
                <thead className="bg-muted/40 text-xs text-muted-foreground">
                    <tr>
                        <th
                            scope="col"
                            className="px-3 py-2 text-left font-medium"
                        >
                            Step QCC
                        </th>
                        <th
                            scope="col"
                            className="w-40 px-3 py-2 text-left font-medium sm:w-48"
                        >
                            Target selesai
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row) => (
                        <tr key={row.number} className="border-t">
                            <td className="px-3 py-2">
                                <span className="font-medium">
                                    {row.number}.
                                </span>{' '}
                                {row.name}
                            </td>
                            <td
                                className={
                                    row.date
                                        ? 'px-3 py-2 tabular-nums'
                                        : 'px-3 py-2 text-muted-foreground'
                                }
                            >
                                {formatDate(row.date)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
