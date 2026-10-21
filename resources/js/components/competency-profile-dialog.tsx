import { ShieldCheck } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type ProfileCompetency = {
    id: number;
    code: string;
    name: string;
    group_code: string | null;
    self_level: number;
    self_level_label: string | null;
    verified_level: number | null;
    verified_level_label: string | null;
};

export type MemberProfile = {
    id: number;
    name: string;
    sim_id: string | null;
    email: string;
    organization: string | null;
    role: string | null;
    competencies: ProfileCompetency[];
};

type Props = {
    member: MemberProfile | null;
    onClose: () => void;
};

function Info({ label, value }: { label: string; value: string | null }) {
    return (
        <div className="grid gap-1">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-sm font-medium break-words">{value ?? '—'}</dd>
        </div>
    );
}

export function CompetencyProfileDialog({ member, onClose }: Props) {
    return (
        <Dialog
            open={member !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="gap-6 sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {member?.name ?? 'Profil Kompetensi'}
                    </DialogTitle>
                </DialogHeader>

                {member && (
                    <div className="grid gap-6">
                        <dl className="grid gap-4 sm:grid-cols-2">
                            <Info label="SIM ID" value={member.sim_id} />
                            <Info label="Email" value={member.email} />
                            <Info
                                label="LOB / Divisi"
                                value={member.organization}
                            />
                            <Info label="Role" value={member.role} />
                        </dl>

                        {member.competencies.length === 0 ? (
                            <p className="rounded-lg bg-muted px-4 py-6 text-center text-sm text-muted-foreground">
                                Belum ada kompetensi yang diisi.
                            </p>
                        ) : (
                            <div className="max-h-[50vh] overflow-auto rounded-lg border border-border/70">
                                <table className="w-full text-sm">
                                    <thead className="sticky top-0 bg-muted text-left text-xs text-muted-foreground">
                                        <tr>
                                            <th className="px-3 py-2 font-medium">
                                                Kompetensi
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Level sendiri
                                            </th>
                                            <th className="px-3 py-2 font-medium">
                                                Terverifikasi
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border/60">
                                        {member.competencies.map((item) => (
                                            <tr key={item.id}>
                                                <td className="px-3 py-2">
                                                    <span className="font-medium">
                                                        {item.name}
                                                    </span>
                                                    <span className="block text-xs text-muted-foreground">
                                                        {item.code}
                                                        {item.group_code
                                                            ? ` · Kelompok ${item.group_code}`
                                                            : ''}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-2 whitespace-nowrap">
                                                    {item.self_level} ·{' '}
                                                    {item.self_level_label}
                                                </td>
                                                <td className="px-3 py-2 whitespace-nowrap">
                                                    {item.verified_level !==
                                                    null ? (
                                                        <Badge
                                                            variant="secondary"
                                                            className="gap-1 bg-[#16834a]/10 text-[#16834a]"
                                                        >
                                                            <ShieldCheck aria-hidden="true" />
                                                            {
                                                                item.verified_level
                                                            }{' '}
                                                            ·{' '}
                                                            {
                                                                item.verified_level_label
                                                            }
                                                        </Badge>
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            Belum terverifikasi
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
