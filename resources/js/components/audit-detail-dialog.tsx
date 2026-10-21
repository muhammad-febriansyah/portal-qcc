import { format, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type AuditChange = {
    label: string;
    before: string;
    after: string;
};

export type AuditRow = {
    id: number;
    occurred_at: string;
    actor_name: string | null;
    actor_email: string | null;
    description: string;
    event_label: string;
    module_label: string;
    subject_label: string | null;
    trace_id: string;
    changes: AuditChange[];
    reason: string | null;
};

type Props = {
    log: AuditRow | null;
    onClose: () => void;
};

function InfoItem({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-1">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-sm font-medium break-words">{children}</dd>
        </div>
    );
}

export function AuditDetailDialog({ log, onClose }: Props) {
    return (
        <Dialog open={log !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="gap-6 sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Detail Aktivitas</DialogTitle>
                </DialogHeader>

                {log && (
                    <div className="grid gap-6">
                        <p className="rounded-lg bg-muted px-4 py-3 text-sm leading-relaxed font-medium">
                            {log.description}
                        </p>

                        <dl className="grid gap-4 sm:grid-cols-2">
                            <InfoItem label="Waktu">
                                {format(
                                    parseISO(log.occurred_at),
                                    "EEEE, d MMMM yyyy 'pukul' HH:mm:ss",
                                    { locale: indonesianLocale },
                                )}
                            </InfoItem>
                            <InfoItem label="Pelaku">
                                {log.actor_name ?? 'Sistem'}
                                {log.actor_email && (
                                    <span className="block text-xs font-normal text-muted-foreground">
                                        {log.actor_email}
                                    </span>
                                )}
                            </InfoItem>
                            <InfoItem label="Modul">
                                {log.module_label}
                            </InfoItem>
                            <InfoItem label="Jenis aktivitas">
                                {log.event_label}
                            </InfoItem>
                            {log.subject_label && (
                                <InfoItem label="Data terkait">
                                    {log.subject_label}
                                </InfoItem>
                            )}
                            {log.reason && (
                                <InfoItem label="Alasan">{log.reason}</InfoItem>
                            )}
                        </dl>

                        {log.changes.length > 0 && (
                            <div className="overflow-hidden rounded-lg border border-border/70">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50 text-left text-xs text-muted-foreground">
                                        <tr>
                                            <th className="px-3 py-2 font-medium">
                                                Data
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Sebelum
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Sesudah
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border/60">
                                        {log.changes.map((change) => (
                                            <tr key={change.label}>
                                                <td className="px-3 py-2 font-medium">
                                                    {change.label}
                                                </td>
                                                <td className="px-3 py-2 text-muted-foreground">
                                                    {change.before}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {change.after}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        <p className="text-xs text-muted-foreground">
                            ID jejak: <code>{log.trace_id}</code>
                        </p>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
