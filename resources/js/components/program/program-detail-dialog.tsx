import { Link, useHttp } from '@inertiajs/react';
import { ArrowUpRight, Search } from 'lucide-react';
import { createContext, useContext, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import { numberFormat } from '@/components/dashboard/dashboard-kit';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { programDetails } from '@/routes/dashboard';

type PersonRow = {
    id: number;
    name: string;
    sim_id: string | null;
    position: string | null;
    organization: string | null;
    competencies: number;
    proposals: number;
};

type ProposalRow = {
    id: number;
    code: string;
    title: string;
    proposer: string | null;
    organization: string | null;
    theme: string | null;
    status: string;
    status_key: string;
    team_size: number;
    url: string | null;
};

type OrganizationRow = {
    id: number;
    name: string;
    participants: number;
    submitters: number;
    proposals: number;
};

type ProgramDetail =
    | { title: string; description: string; kind: 'people'; rows: PersonRow[] }
    | {
          title: string;
          description: string;
          kind: 'proposals';
          rows: ProposalRow[];
      }
    | {
          title: string;
          description: string;
          kind: 'organizations';
          rows: OrganizationRow[];
      };

type OpenDetail = (
    metric: string,
    title: string,
    key?: string | number,
) => void;

const ProgramDetailContext = createContext<OpenDetail | null>(null);

/**
 * Opens the list behind a dashboard number. Null outside the program dashboard,
 * where the numbers stay plain text.
 */
export function useProgramDetail(): OpenDetail | null {
    return useContext(ProgramDetailContext);
}

const STATUS_VARIANTS: Record<
    string,
    'default' | 'secondary' | 'outline' | 'destructive'
> = {
    APPROVED: 'default',
    REJECTED: 'destructive',
    SUBMITTED: 'secondary',
    UNDER_REVIEW: 'secondary',
    REVISION_REQUIRED: 'outline',
};

function matches(
    values: (string | null | undefined)[],
    query: string,
): boolean {
    return values.some((value) => value?.toLowerCase().includes(query));
}

/**
 * Lets every number of the program dashboard open a popup with the SIMers,
 * proposals or LOB/Divs it counts.
 */
export function ProgramDetailProvider({ children }: { children: ReactNode }) {
    const [open, setOpen] = useState(false);
    const [heading, setHeading] = useState('');
    const [search, setSearch] = useState('');
    const [detail, setDetail] = useState<ProgramDetail | null>(null);
    const [failed, setFailed] = useState(false);
    const http = useHttp<Record<string, never>, ProgramDetail>({});

    const openDetail: OpenDetail = (metric, title, key) => {
        setHeading(title);
        setSearch('');
        setDetail(null);
        setFailed(false);
        setOpen(true);
        http.get(
            programDetails.url({
                query: { metric, key: key === undefined ? null : String(key) },
            }),
            {
                onSuccess: (response) => setDetail(response),
                onHttpException: () => {
                    setFailed(true);

                    return false;
                },
                onNetworkError: () => {
                    setFailed(true);

                    return false;
                },
            },
        ).catch(() => setFailed(true));
    };

    return (
        <ProgramDetailContext.Provider value={openDetail}>
            {children}
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="flex max-h-[85vh] flex-col gap-3 sm:max-w-4xl">
                    <DialogHeader className="pr-8">
                        <DialogTitle>{detail?.title ?? heading}</DialogTitle>
                        <DialogDescription>
                            {detail
                                ? `${detail.description} ${numberFormat.format(detail.rows.length)} data.`
                                : 'Memuat data...'}
                        </DialogDescription>
                    </DialogHeader>
                    {failed ? (
                        <p className="py-6 text-center text-sm text-muted-foreground">
                            Data tidak dapat dimuat. Coba lagi beberapa saat.
                        </p>
                    ) : detail === null ? (
                        <div className="grid gap-2" aria-busy="true">
                            {Array.from({ length: 6 }, (_, index) => (
                                <Skeleton key={index} className="h-9 w-full" />
                            ))}
                        </div>
                    ) : (
                        <>
                            {detail.rows.length > 8 && (
                                <div className="relative">
                                    <Search
                                        aria-hidden="true"
                                        className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                                    />
                                    <Input
                                        value={search}
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                        placeholder="Cari nama, LOB/Div, judul..."
                                        aria-label="Cari data"
                                        className="pl-8"
                                    />
                                </div>
                            )}
                            <div className="min-h-0 flex-1 overflow-auto rounded-lg border">
                                <DetailTable
                                    detail={detail}
                                    search={search.trim().toLowerCase()}
                                    onOrganization={(row) =>
                                        openDetail(
                                            'organization_proposals',
                                            row.name,
                                            row.id,
                                        )
                                    }
                                />
                            </div>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </ProgramDetailContext.Provider>
    );
}

function DetailTable({
    detail,
    search,
    onOrganization,
}: {
    detail: ProgramDetail;
    search: string;
    onOrganization: (row: OrganizationRow) => void;
}) {
    const rows = useMemo(() => {
        if (search === '') {
            return detail.rows;
        }

        switch (detail.kind) {
            case 'people':
                return detail.rows.filter((row) =>
                    matches(
                        [row.name, row.sim_id, row.organization, row.position],
                        search,
                    ),
                );
            case 'proposals':
                return detail.rows.filter((row) =>
                    matches(
                        [
                            row.code,
                            row.title,
                            row.proposer,
                            row.organization,
                            row.theme,
                            row.status,
                        ],
                        search,
                    ),
                );
            default:
                return detail.rows.filter((row) => matches([row.name], search));
        }
    }, [detail, search]);

    if (rows.length === 0) {
        return (
            <p className="px-4 py-8 text-center text-sm text-muted-foreground">
                {detail.rows.length === 0
                    ? 'Belum ada data.'
                    : 'Tidak ada data yang cocok.'}
            </p>
        );
    }

    if (detail.kind === 'people') {
        return (
            <Table>
                <TableHeader className="sticky top-0 z-10 bg-popover">
                    <TableRow>
                        <TableHead>Nama</TableHead>
                        <TableHead>LOB/Div</TableHead>
                        <TableHead className="text-right">Skill</TableHead>
                        <TableHead className="text-right">Proposal</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {(rows as PersonRow[]).map((row) => (
                        <TableRow key={row.id}>
                            <TableCell className="whitespace-normal">
                                <span className="block font-medium text-foreground">
                                    {row.name}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {[
                                        row.sim_id
                                            ? `SIM ID ${row.sim_id}`
                                            : null,
                                        row.position,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ') || '-'}
                                </span>
                            </TableCell>
                            <TableCell className="whitespace-normal">
                                {row.organization ?? '-'}
                            </TableCell>
                            <TableCell className="text-right tabular-nums">
                                {numberFormat.format(row.competencies)}
                            </TableCell>
                            <TableCell className="text-right tabular-nums">
                                {numberFormat.format(row.proposals)}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        );
    }

    if (detail.kind === 'organizations') {
        return (
            <Table>
                <TableHeader className="sticky top-0 z-10 bg-popover">
                    <TableRow>
                        <TableHead>LOB/Div</TableHead>
                        <TableHead className="text-right">SIMers</TableHead>
                        <TableHead className="text-right">Pengusul</TableHead>
                        <TableHead className="text-right">Proposal</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {(rows as OrganizationRow[]).map((row) => (
                        <TableRow key={row.id}>
                            <TableCell className="whitespace-normal">
                                {row.proposals > 0 ? (
                                    <button
                                        type="button"
                                        onClick={() => onOrganization(row)}
                                        className="rounded text-left font-medium text-primary hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        {row.name}
                                    </button>
                                ) : (
                                    <span className="text-foreground">
                                        {row.name}
                                    </span>
                                )}
                            </TableCell>
                            <TableCell className="text-right tabular-nums">
                                {numberFormat.format(row.participants)}
                            </TableCell>
                            <TableCell className="text-right tabular-nums">
                                {numberFormat.format(row.submitters)}
                            </TableCell>
                            <TableCell className="text-right tabular-nums">
                                {numberFormat.format(row.proposals)}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        );
    }

    return (
        <Table>
            <TableHeader className="sticky top-0 z-10 bg-popover">
                <TableRow>
                    <TableHead>Proposal</TableHead>
                    <TableHead>Pengusul</TableHead>
                    <TableHead>Tema</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="text-right">Tim</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {(rows as ProposalRow[]).map((row) => (
                    <TableRow key={row.id}>
                        <TableCell className="min-w-56 whitespace-normal">
                            <span className="block text-xs text-muted-foreground tabular-nums">
                                {row.code}
                            </span>
                            {row.url ? (
                                <Link
                                    href={row.url}
                                    className="inline-flex items-start gap-1 rounded font-medium text-primary hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    {row.title}
                                    <ArrowUpRight
                                        aria-hidden="true"
                                        className="mt-0.5 size-3.5 shrink-0"
                                    />
                                </Link>
                            ) : (
                                <span className="font-medium text-foreground">
                                    {row.title}
                                </span>
                            )}
                        </TableCell>
                        <TableCell className="whitespace-normal">
                            <span className="block text-foreground">
                                {row.proposer ?? '-'}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {row.organization ?? '-'}
                            </span>
                        </TableCell>
                        <TableCell className="whitespace-normal">
                            {row.theme ?? '-'}
                        </TableCell>
                        <TableCell>
                            <Badge
                                variant={
                                    STATUS_VARIANTS[row.status_key] ?? 'outline'
                                }
                            >
                                {row.status}
                            </Badge>
                        </TableCell>
                        <TableCell className="text-right tabular-nums">
                            {row.team_size > 0 ? `${row.team_size} orang` : '-'}
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
