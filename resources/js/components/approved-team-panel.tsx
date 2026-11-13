import { errorAlertClass } from '@/lib/alert-styles';
import { Crown, TriangleAlert, UserRound, Users, X } from 'lucide-react';
import { useState } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { MAX_TEAM_MEMBERS } from '@/lib/team-rules';

export type ApprovedTeamMember = {
    user_id: number;
    name: string;
    organization: string | null;
    role: string;
    open_items: { risks: number; actions: number };
};

type Props = {
    team: ApprovedTeamMember[];
    errors: string[];
    busyUserId: number | null;
    onRemove: (userId: number, replacementPicId: number | null) => void;
    onChangeLeader: (userId: number) => void;
};

type PendingChange = {
    kind: 'remove' | 'leader';
    member: ApprovedTeamMember;
};

function openItemTotal(member: ApprovedTeamMember): number {
    return member.open_items.risks + member.open_items.actions;
}

/**
 * The team of an approved proposal. Members can be removed or the leader role can be
 * handed over here. The server checks the team rules on every change, and a member who
 * still leads open risks or action items must name a replacement PIC first.
 */
export function ApprovedTeamPanel({
    team,
    errors,
    busyUserId,
    onRemove,
    onChangeLeader,
}: Props) {
    const [pending, setPending] = useState<PendingChange | null>(null);
    const [replacementId, setReplacementId] = useState('');
    const leader = team.find((member) => member.role === 'LEADER');
    const members = team.filter((member) => member.role !== 'LEADER');
    const pendingItems = pending ? openItemTotal(pending.member) : 0;
    const needsReplacement = pending?.kind === 'remove' && pendingItems > 0;
    const replacementItems = pending
        ? team
              .filter((member) => member.user_id !== pending.member.user_id)
              .map((member) => ({
                  value: String(member.user_id),
                  label: member.name,
              }))
        : [];

    function openChange(
        kind: PendingChange['kind'],
        member: ApprovedTeamMember,
    ) {
        setReplacementId('');
        setPending({ kind, member });
    }

    function confirmChange() {
        if (pending === null) {
            return;
        }

        if (pending.kind === 'leader') {
            onChangeLeader(pending.member.user_id);
        } else {
            onRemove(
                pending.member.user_id,
                needsReplacement ? Number(replacementId) : null,
            );
        }

        setPending(null);
    }

    function renderMember(member: ApprovedTeamMember, isLeader: boolean) {
        const busy = busyUserId === member.user_id;
        const items = openItemTotal(member);

        return (
            <div className="flex min-w-0 items-center gap-2 rounded-xl border border-border/80 bg-background p-2.5 shadow-sm shadow-black/[0.02]">
                <span
                    className={`flex size-9 shrink-0 items-center justify-center rounded-lg ${isLeader ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground'}`}
                    aria-hidden="true"
                >
                    {isLeader ? (
                        <Crown className="size-4" />
                    ) : (
                        <UserRound className="size-4" />
                    )}
                </span>
                <div className="grid min-w-0 flex-1">
                    <p className="truncate text-sm font-medium">
                        {member.name}
                    </p>
                    <p className="truncate text-xs text-muted-foreground">
                        {member.organization || 'Organisasi belum tersedia'}
                        {items > 0 && ` · ${items} item PIC terbuka`}
                    </p>
                </div>
                {busy && <Spinner aria-label="Memproses" />}
                {!isLeader && !busy && (
                    <div className="flex shrink-0 items-center">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            aria-label={`Jadikan ${member.name} leader`}
                            title="Jadikan leader"
                            disabled={busyUserId !== null}
                            onClick={() => openChange('leader', member)}
                        >
                            <Crown aria-hidden="true" />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            aria-label={`Keluarkan ${member.name} dari tim`}
                            disabled={busyUserId !== null}
                            onClick={() => openChange('remove', member)}
                        >
                            <X aria-hidden="true" />
                        </Button>
                    </div>
                )}
            </div>
        );
    }

    return (
        <>
            <Card className="gap-0 py-0">
                <CardContent className="grid gap-4 p-5">
                    <div className="flex items-center justify-between gap-3">
                        <div className="flex items-center gap-2">
                            <Users
                                className="size-4 text-primary"
                                aria-hidden="true"
                            />
                            <h2 className="text-base font-semibold">
                                Tim Proposal
                            </h2>
                        </div>
                        <Badge variant="secondary">
                            {team.length}/{MAX_TEAM_MEMBERS}
                        </Badge>
                    </div>

                    <p className="text-sm text-muted-foreground">
                        Proposal sudah disetujui. Perubahan anggota berlaku
                        langsung dan tercatat di audit log.
                    </p>

                    {team.length === 0 ? (
                        <p className="rounded-xl border border-dashed border-primary/25 bg-primary/[0.035] p-4 text-center text-sm text-muted-foreground">
                            Belum ada anggota tim.
                        </p>
                    ) : (
                        <div className="grid gap-2">
                            <div className="flex items-center gap-2 px-1 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                <Crown
                                    className="size-3.5 text-primary"
                                    aria-hidden="true"
                                />
                                Leader proyek
                            </div>
                            {leader && (
                                <div className="rounded-2xl border border-primary/20 bg-primary/[0.04] p-2">
                                    {renderMember(leader, true)}
                                </div>
                            )}
                            {members.length > 0 && (
                                <div className="relative ml-4 border-l border-dashed border-primary/30 pt-1 pl-4">
                                    <p className="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                        Anggota tim
                                    </p>
                                    <ul className="grid gap-2">
                                        {members.map((member) => (
                                            <li
                                                key={member.user_id}
                                                className="relative before:absolute before:top-1/2 before:-left-4 before:w-4 before:border-t before:border-dashed before:border-primary/30"
                                            >
                                                {renderMember(member, false)}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </div>
                    )}

                    {errors.length > 0 && (
                        <Alert
                            variant="destructive"
                            role="alert"
                            className={errorAlertClass}
                        >
                            <TriangleAlert aria-hidden="true" />
                            <AlertTitle>
                                Perubahan tim belum berhasil
                            </AlertTitle>
                            <AlertDescription>
                                <ul className="list-disc pl-4">
                                    {errors.map((error) => (
                                        <li key={error}>{error}</li>
                                    ))}
                                </ul>
                            </AlertDescription>
                        </Alert>
                    )}
                </CardContent>
            </Card>

            <AlertDialog
                open={pending !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPending(null);
                    }
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {pending?.kind === 'leader'
                                ? `Jadikan ${pending.member.name} leader tim?`
                                : `Keluarkan ${pending?.member.name} dari tim?`}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {pending?.kind === 'leader'
                                ? 'Leader saat ini menjadi anggota biasa. PIC proposal tidak berubah.'
                                : 'Perubahan langsung berlaku dan tercatat di audit log. Tim tetap harus memenuhi aturan: minimal 2 LOB/Divisi dan tepat 1 leader.'}
                        </AlertDialogDescription>
                    </AlertDialogHeader>

                    {needsReplacement && pending && (
                        <div className="grid gap-2">
                            <p className="text-sm">
                                {pending.member.name} masih PIC untuk{' '}
                                {pending.member.open_items.risks} risiko dan{' '}
                                {pending.member.open_items.actions} tindakan
                                yang belum selesai. Pilih PIC pengganti dari
                                anggota yang tetap:
                            </p>
                            <Select
                                value={replacementId}
                                onValueChange={(value) =>
                                    setReplacementId(value ?? '')
                                }
                                items={replacementItems}
                            >
                                <SelectTrigger
                                    aria-label="PIC pengganti"
                                    className="w-full"
                                >
                                    <SelectValue placeholder="Pilih PIC pengganti" />
                                </SelectTrigger>
                                <SelectContent>
                                    {replacementItems.map((item) => (
                                        <SelectItem
                                            key={item.value}
                                            value={item.value}
                                        >
                                            {item.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}

                    <AlertDialogFooter>
                        <AlertDialogCancel variant="outline">
                            Batal
                        </AlertDialogCancel>
                        <AlertDialogAction
                            variant={
                                pending?.kind === 'leader'
                                    ? 'default'
                                    : 'destructive'
                            }
                            disabled={needsReplacement && replacementId === ''}
                            onClick={confirmChange}
                        >
                            {pending?.kind === 'leader'
                                ? 'Ya, jadikan leader'
                                : 'Ya, keluarkan'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
