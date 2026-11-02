import {
    CheckCircle2,
    Crown,
    RotateCcw,
    TriangleAlert,
    UserRound,
    Users,
    X,
} from 'lucide-react';
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
    AlertDialogTrigger,
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
import type { TeamPick, TeamRequirement, TeamRole } from '@/lib/team-rules';

export type TeamPanelPerson = {
    user_id: number;
    name: string;
    organization: string;
};

type Props = {
    picks: TeamPick[];
    people: Map<number, TeamPanelPerson>;
    problems: string[];
    gaps: TeamRequirement[];
    changedFromAi: boolean;
    processing: boolean;
    serverErrors: string[];
    onRoleChange: (userId: number, role: TeamRole) => void;
    onRemove: (userId: number) => void;
    onReset: () => void;
    onConfirm: () => void;
};

const roleItems = [
    { value: 'LEADER', label: 'Leader' },
    { value: 'MEMBER', label: 'Anggota' },
];

const errorAlertClass =
    'border-destructive/25 bg-destructive/10 text-destructive [&_[data-slot=alert-description]]:text-destructive/90';

/**
 * The team being put together: pick state, rule check, competency gaps and the
 * confirm step. Nothing here becomes a team until Konfirmasi is accepted.
 */
export function TeamPanel({
    picks,
    people,
    problems,
    gaps,
    changedFromAi,
    processing,
    serverErrors,
    onRoleChange,
    onRemove,
    onReset,
    onConfirm,
}: Props) {
    const canConfirm = problems.length === 0 && !processing;
    const leader = picks.find((pick) => pick.role === 'LEADER') ?? picks[0];
    const members = picks.filter((pick) => pick.user_id !== leader?.user_id);

    function renderMember(pick: TeamPick, isLeader: boolean) {
        const person = people.get(pick.user_id);
        const isProjectLeader = isLeader && pick.role === 'LEADER';

        return (
            <div className="flex min-w-0 items-center gap-2 rounded-xl border border-border/80 bg-background p-2.5 shadow-sm shadow-black/[0.02]">
                <span
                    className={`flex size-9 shrink-0 items-center justify-center rounded-lg ${isProjectLeader ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground'}`}
                    aria-hidden="true"
                >
                    {isProjectLeader ? (
                        <Crown className="size-4" />
                    ) : (
                        <UserRound className="size-4" />
                    )}
                </span>
                <div className="grid min-w-0 flex-1">
                    <p className="truncate text-[13px] font-medium">
                        {person?.name ?? `#${pick.user_id}`}
                    </p>
                    <p className="truncate text-xs text-muted-foreground">
                        {person?.organization || 'Organisasi belum tersedia'}
                    </p>
                </div>
                <Select
                    value={pick.role}
                    onValueChange={(value) =>
                        value &&
                        onRoleChange(pick.user_id, value as TeamRole)
                    }
                    items={roleItems}
                >
                    <SelectTrigger
                        aria-label={`Peran ${person?.name ?? ''}`}
                        className="h-8 w-25 px-2 text-xs"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {roleItems.map((item) => (
                            <SelectItem key={item.value} value={item.value}>
                                {item.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    aria-label={`Keluarkan ${person?.name ?? ''} dari susunan`}
                    onClick={() => onRemove(pick.user_id)}
                >
                    <X aria-hidden="true" />
                </Button>
            </div>
        );
    }

    return (
        <Card className="gap-0 py-0">
            <CardContent className="grid gap-4 p-5">
                <div className="flex items-center justify-between gap-3">
                    <div className="flex items-center gap-2">
                        <Users
                            className="size-4 text-primary"
                            aria-hidden="true"
                        />
                        <h2 className="text-base font-semibold">Susunan Tim</h2>
                    </div>
                    <Badge
                        variant={
                            picks.length > MAX_TEAM_MEMBERS
                                ? 'destructive'
                                : 'secondary'
                        }
                    >
                        {picks.length}/{MAX_TEAM_MEMBERS}
                    </Badge>
                </div>

                <Badge
                    variant={changedFromAi ? 'warning' : 'info'}
                    className="w-fit font-normal"
                >
                    {changedFromAi
                        ? 'Diubah dari saran AI'
                        : 'Mengikuti saran AI'}
                </Badge>

                {picks.length === 0 ? (
                    <p className="rounded-xl border border-dashed border-primary/25 bg-primary/[0.035] p-4 text-center text-[13px] text-muted-foreground">
                        Belum ada anggota. Pilih dari rekomendasi atau daftar
                        kandidat.
                    </p>
                ) : (
                    <div className="grid gap-2">
                        <div className="flex items-center gap-2 px-1 text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
                            <Crown
                                className="size-3.5 text-primary"
                                aria-hidden="true"
                            />
                            {leader?.role === 'LEADER'
                                ? 'Leader proyek'
                                : 'Tetapkan leader'}
                        </div>
                        {leader && (
                            <div className="rounded-2xl border border-primary/20 bg-primary/[0.04] p-2">
                                {renderMember(leader, true)}
                            </div>
                        )}
                        {members.length > 0 && (
                            <div className="relative ml-4 border-l border-dashed border-primary/30 pl-4 pt-1">
                                <p className="mb-2 text-[11px] font-semibold tracking-wide text-muted-foreground uppercase">
                                    Anggota tim
                                </p>
                                <ul className="grid gap-2">
                                    {members.map((pick) => (
                                        <li
                                            key={pick.user_id}
                                            className="relative before:absolute before:top-1/2 before:-left-4 before:w-4 before:border-t before:border-dashed before:border-primary/30"
                                        >
                                            {renderMember(pick, false)}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </div>
                )}

                {problems.length > 0 ? (
                    <Alert
                        variant="destructive"
                        className={errorAlertClass}
                    >
                        <TriangleAlert aria-hidden="true" />
                        <AlertTitle>Belum bisa dikonfirmasi</AlertTitle>
                        <AlertDescription>
                            <ul className="list-disc pl-4">
                                {problems.map((problem) => (
                                    <li key={problem}>{problem}</li>
                                ))}
                            </ul>
                        </AlertDescription>
                    </Alert>
                ) : (
                    <p className="flex items-center gap-2 text-[13px] text-success">
                        <CheckCircle2 className="size-4" aria-hidden="true" />
                        Susunan memenuhi aturan tim.
                    </p>
                )}

                {gaps.length > 0 ? (
                    <div className="grid gap-1.5">
                        <p className="text-[13px] font-medium">
                            Gap kompetensi tim
                        </p>
                        <div className="flex flex-wrap gap-1.5">
                            {gaps.map((gap) => (
                                <Badge
                                    key={gap.competency_id}
                                    variant="warning"
                                    className="font-normal"
                                >
                                    {gap.name}
                                    {gap.minimum_level
                                        ? ` (min. ${gap.minimum_level})`
                                        : ''}
                                </Badge>
                            ))}
                        </div>
                    </div>
                ) : (
                    picks.length > 0 && (
                        <p className="text-[13px] text-muted-foreground">
                            Semua kompetensi yang dibutuhkan sudah tercakup.
                        </p>
                    )
                )}

                {serverErrors.length > 0 && (
                    <Alert
                        variant="destructive"
                        role="alert"
                        className={errorAlertClass}
                    >
                        <TriangleAlert aria-hidden="true" />
                        <AlertTitle>Tim gagal dikonfirmasi</AlertTitle>
                        <AlertDescription>
                            <ul className="list-disc pl-4">
                                {serverErrors.map((error) => (
                                    <li key={error}>{error}</li>
                                ))}
                            </ul>
                        </AlertDescription>
                    </Alert>
                )}

                <div className="flex flex-wrap gap-2">
                    <AlertDialog>
                        <AlertDialogTrigger
                            render={
                                <Button
                                    type="button"
                                    className="h-10 flex-1 px-4"
                                    disabled={!canConfirm}
                                />
                            }
                        >
                            {processing && <Spinner data-icon="inline-start" />}
                            Konfirmasi Tim
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>
                                    Konfirmasi susunan tim?
                                </AlertDialogTitle>
                                <AlertDialogDescription>
                                    {picks.length} anggota akan menjadi tim
                                    proposal ini
                                    {changedFromAi
                                        ? ', termasuk perubahan manual dari saran AI'
                                        : ''}
                                    . Perubahannya tercatat di audit log.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>Batal</AlertDialogCancel>
                                <AlertDialogAction onClick={onConfirm}>
                                    Ya, konfirmasi
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                    <Button
                        type="button"
                        variant="outline"
                        className="h-10 px-3"
                        disabled={!changedFromAi || processing}
                        onClick={onReset}
                    >
                        <RotateCcw
                            data-icon="inline-start"
                            aria-hidden="true"
                        />
                        Ikuti saran AI
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}
