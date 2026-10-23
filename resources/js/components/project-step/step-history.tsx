import { format, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';

export type StepHistoryEntry = {
    id: number;
    action: string;
    actor: string | null;
    previous_status: string | null;
    new_status: string;
    version: number;
    reason: string | null;
    created_at: string | null;
};

const actionLabels: Record<string, string> = {
    SAVED: 'menyimpan draft',
    SUBMITTED: 'mengajukan step',
    REVIEW_STARTED: 'memulai review',
    APPROVED: 'menyetujui step',
    REVISION_REQUESTED: 'meminta revisi',
};

/**
 * Riwayat Revisi: who did what to the step and when, newest first.
 */
export function StepHistory({
    entries,
    statusLabels,
}: {
    entries: StepHistoryEntry[];
    statusLabels: Record<string, string>;
}) {
    if (entries.length === 0) {
        return (
            <p className="text-[13px] text-muted-foreground">
                Belum ada riwayat perubahan.
            </p>
        );
    }

    return (
        <ol className="grid" aria-label="Riwayat revisi">
            {entries.map((entry) => (
                <li
                    key={entry.id}
                    className="grid gap-0.5 border-b border-border/60 py-3 first:pt-0 last:border-b-0 last:pb-0"
                >
                    <p className="text-[13px]">
                        <span className="font-medium">
                            {entry.actor ?? 'Sistem'}
                        </span>{' '}
                        {actionLabels[entry.action] ?? entry.action}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {entry.created_at
                            ? format(
                                  parseISO(entry.created_at),
                                  'd MMM yyyy, HH:mm',
                                  { locale: indonesianLocale },
                              )
                            : ''}{' '}
                        · versi {entry.version}
                        {entry.previous_status !== entry.new_status &&
                            ` · ${statusLabels[entry.previous_status ?? ''] ?? '—'} → ${statusLabels[entry.new_status] ?? entry.new_status}`}
                    </p>
                    {entry.reason && (
                        <p className="mt-1 rounded-md bg-muted px-3 py-2 text-[13px] whitespace-pre-line">
                            {entry.reason}
                        </p>
                    )}
                </li>
            ))}
        </ol>
    );
}
