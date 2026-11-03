import { ChevronLeft, ChevronRight, Search, ShieldCheck } from 'lucide-react';
import { useMemo, useState } from 'react';
import { DataTable, type DataTableColumnDef } from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export type ProfileCompetency = {
    id: number;
    code: string;
    name: string;
    group_code: string | null;
    self_level: number;
    self_level_label: string | null;
    verified_level: number | null;
    verified_level_label: string | null;
    verification_status: string | null;
    verification_note: string | null;
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

const DEFAULT_PAGE_SIZE = 5;
const PAGE_SIZES = [5, 10, 25];

function Info({ label, value }: { label: string; value: string | null }) {
    return (
        <div className="grid min-w-0 gap-1 rounded-xl border border-border/60 bg-muted/30 px-4 py-3">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="text-base font-semibold [overflow-wrap:anywhere] text-foreground">
                {value ?? '—'}
            </dd>
        </div>
    );
}

function LevelChip({
    level,
    label,
    tone,
}: {
    level: number;
    label: string | null;
    tone: 'primary' | 'success';
}) {
    return (
        <span className="inline-flex max-w-full items-center gap-2.5 whitespace-normal">
            <span
                className={cn(
                    'flex size-7 shrink-0 items-center justify-center rounded-md text-sm font-semibold tabular-nums',
                    tone === 'success'
                        ? 'bg-success/10 text-success'
                        : 'bg-primary/10 text-primary',
                )}
            >
                {level}
            </span>
            <span className="text-base text-foreground">{label ?? '—'}</span>
        </span>
    );
}

const badgeTextClass =
    'h-auto min-h-6 px-2.5 py-0.5 text-left text-sm whitespace-normal';

function VerificationStatus({
    status,
    note,
}: {
    status: string | null;
    note: string | null;
}) {
    const normalized = status?.toUpperCase();

    if (normalized === 'VERIFIED') {
        return (
            <Badge variant="success" className={cn(badgeTextClass, 'gap-1.5')}>
                <ShieldCheck aria-hidden="true" />
                Terverifikasi
            </Badge>
        );
    }

    if (normalized === 'PENDING') {
        return (
            <Badge variant="outline" className={badgeTextClass}>
                Menunggu verifikasi ulang
            </Badge>
        );
    }

    if (normalized === 'REJECTED') {
        return (
            <div className="grid justify-items-start gap-1.5">
                <Badge variant="destructive" className={badgeTextClass}>
                    Perlu perbaikan
                </Badge>
                {note && (
                    <p className="text-sm leading-snug whitespace-normal text-muted-foreground">
                        {note}
                    </p>
                )}
            </div>
        );
    }

    return null;
}

function CompetencyName({ item }: { item: ProfileCompetency }) {
    return (
        <div className="grid gap-0.5 whitespace-normal">
            <span className="font-semibold text-foreground">{item.name}</span>
            <span className="text-sm text-muted-foreground">
                {item.code}
                {item.group_code ? ` · Kelompok ${item.group_code}` : ''}
            </span>
        </div>
    );
}

function VerifiedCell({ item }: { item: ProfileCompetency }) {
    return (
        <div className="grid justify-items-start gap-2 whitespace-normal">
            {item.verified_level !== null ? (
                <LevelChip
                    level={item.verified_level}
                    label={item.verified_level_label}
                    tone="success"
                />
            ) : (
                <span className="text-sm text-muted-foreground">
                    Belum ada level verifikasi
                </span>
            )}
            <VerificationStatus
                status={item.verification_status}
                note={item.verification_note}
            />
        </div>
    );
}

const competencyColumns: DataTableColumnDef<ProfileCompetency>[] = [
    {
        id: 'competency',
        header: 'Kompetensi',
        cell: ({ row }) => <CompetencyName item={row.original} />,
    },
    {
        id: 'self_level',
        header: 'Level sendiri',
        cell: ({ row }) => (
            <LevelChip
                level={row.original.self_level}
                label={row.original.self_level_label}
                tone="primary"
            />
        ),
    },
    {
        id: 'verified',
        header: 'Terverifikasi',
        cell: ({ row }) => <VerifiedCell item={row.original} />,
    },
];

function CompetencyCard({ item }: { item: ProfileCompetency }) {
    return (
        <article className="grid min-w-0 gap-4 rounded-xl border border-border/60 p-4">
            <CompetencyName item={item} />
            <dl className="grid gap-4">
                <div className="grid min-w-0 gap-1.5">
                    <dt className="text-xs font-medium text-muted-foreground">
                        Level sendiri
                    </dt>
                    <dd>
                        <LevelChip
                            level={item.self_level}
                            label={item.self_level_label}
                            tone="primary"
                        />
                    </dd>
                </div>
                <div className="grid min-w-0 gap-1.5">
                    <dt className="text-xs font-medium text-muted-foreground">
                        Terverifikasi
                    </dt>
                    <dd>
                        <VerifiedCell item={item} />
                    </dd>
                </div>
            </dl>
        </article>
    );
}

function CompetencyTable({
    competencies,
}: {
    competencies: ProfileCompetency[];
}) {
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [pageSize, setPageSize] = useState(DEFAULT_PAGE_SIZE);

    const filtered = useMemo(() => {
        const keyword = search.trim().toLowerCase();

        if (keyword === '') {
            return competencies;
        }

        return competencies.filter((item) =>
            `${item.code} ${item.name} ${item.group_code ?? ''}`
                .toLowerCase()
                .includes(keyword),
        );
    }, [competencies, search]);

    const pageCount = Math.max(1, Math.ceil(filtered.length / pageSize));
    const currentPage = Math.min(page, pageCount);
    const rows = useMemo(
        () =>
            filtered.slice(
                (currentPage - 1) * pageSize,
                currentPage * pageSize,
            ),
        [filtered, currentPage, pageSize],
    );

    function updateSearch(value: string): void {
        setSearch(value);
        setPage(1);
    }

    return (
        <section className="grid min-w-0 gap-3">
            <div className="flex items-center justify-between gap-3">
                <h3 className="text-sm font-semibold text-foreground">
                    Kompetensi
                </h3>
                <span className="text-sm text-muted-foreground tabular-nums">
                    {competencies.length} kompetensi
                </span>
            </div>

            {/* sm and up: the shared DataTable (search, sortable frame, pagination). */}
            <div className="hidden overflow-hidden rounded-xl border border-border/60 sm:block">
                <DataTable
                    embedded
                    columns={competencyColumns}
                    data={rows}
                    rowId={(row) => String(row.id)}
                    page={currentPage}
                    pageSize={pageSize}
                    total={filtered.length}
                    onPageChange={setPage}
                    onPageSizeChange={(size) => {
                        setPageSize(size);
                        setPage(1);
                    }}
                    pageSizes={PAGE_SIZES}
                    search={search}
                    onSearchChange={updateSearch}
                    searchPlaceholder="Cari kode, nama, atau kelompok..."
                    emptyTitle="Tidak ada kompetensi yang cocok"
                    emptyDescription="Coba kata kunci lain."
                />
            </div>

            {/* Below sm: same data as stacked cards, so the table never overflows a phone. */}
            <div className="grid min-w-0 gap-3 sm:hidden">
                <div className="relative">
                    <Search
                        aria-hidden="true"
                        className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        value={search}
                        onChange={(event) => updateSearch(event.target.value)}
                        placeholder="Cari kode, nama, atau kelompok..."
                        aria-label="Cari kompetensi"
                        className="pl-9"
                    />
                </div>

                {rows.length === 0 ? (
                    <p className="rounded-xl bg-muted/50 px-4 py-8 text-center text-sm text-muted-foreground">
                        Tidak ada kompetensi yang cocok. Coba kata kunci lain.
                    </p>
                ) : (
                    rows.map((item) => (
                        <CompetencyCard key={item.id} item={item} />
                    ))
                )}

                {filtered.length > pageSize && (
                    <div className="flex items-center justify-between gap-2 text-sm text-muted-foreground">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={currentPage <= 1}
                            onClick={() => setPage(currentPage - 1)}
                        >
                            <ChevronLeft aria-hidden="true" />
                            Sebelumnya
                        </Button>
                        <span aria-live="polite">
                            {currentPage} / {pageCount}
                        </span>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={currentPage >= pageCount}
                            onClick={() => setPage(currentPage + 1)}
                        >
                            Berikutnya
                            <ChevronRight aria-hidden="true" />
                        </Button>
                    </div>
                )}
            </div>
        </section>
    );
}

export function CompetencyProfileDialog({ member, onClose }: Props) {
    return (
        <Dialog
            open={member !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="flex max-h-[90vh] flex-col gap-0 overflow-hidden p-0 sm:max-w-5xl">
                <DialogHeader className="border-b border-border/60 px-5 py-5 sm:px-7">
                    <DialogTitle className="text-xl">
                        {member?.name ?? 'Profil Kompetensi'}
                    </DialogTitle>
                </DialogHeader>

                {member && (
                    <div className="grid min-h-0 min-w-0 gap-6 overflow-y-auto px-5 py-6 sm:px-7">
                        <dl className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <Info label="SIM ID" value={member.sim_id} />
                            <Info label="Email" value={member.email} />
                            <Info
                                label="LOB / Divisi"
                                value={member.organization}
                            />
                            <Info label="Role" value={member.role} />
                        </dl>

                        {member.competencies.length === 0 ? (
                            <p className="rounded-xl bg-muted/50 px-4 py-10 text-center text-base text-muted-foreground">
                                Belum ada kompetensi yang diisi.
                            </p>
                        ) : (
                            <CompetencyTable
                                key={member.id}
                                competencies={member.competencies}
                            />
                        )}
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
