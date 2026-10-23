import { Head, Link } from '@inertiajs/react';
import { ArrowRight, FileText, Search, UsersRound } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
import { dashboard } from '@/routes';
import {
    index as proposalIndex,
    candidates as proposalCandidates,
} from '@/routes/proposals';
import { index as teamFormationIndex } from '@/routes/team-formation';

type ProposalRow = {
    id: number;
    code: string;
    title: string;
    batch: string | null;
    proposer: string | null;
    organization: string | null;
    members_count: number;
    requirements_count: number;
    updated_at: string | null;
};

type PageLink = { url: string | null; label: string; active: boolean };

type Props = {
    proposals: {
        data: ProposalRow[];
        current_page: number;
        from: number | null;
        to: number | null;
        total: number;
        last_page: number;
        links: PageLink[];
    };
    filters: { search: string };
    can_create_proposal: boolean;
};

function formatUpdatedAt(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

export default function TeamFormationIndex({
    proposals,
    filters,
    can_create_proposal,
}: Props) {
    return (
        <>
            <Head title="Pembentukan Tim" />
            <div className="mx-auto w-full max-w-[1440px] px-4 py-6 md:px-6 md:py-8">
                <PageHeader
                    breadcrumbs={[
                        { title: 'Dashboard', href: dashboard() },
                        {
                            title: 'Pembentukan Tim',
                            href: teamFormationIndex(),
                        },
                    ]}
                    title="Pembentukan Tim"
                    description="Pilih proposal draf untuk mencocokkan anggota dan meninjau susunan tim."
                    actions={
                        can_create_proposal ? (
                            <Button
                                variant="outline"
                                render={<Link href={proposalIndex()} />}
                                nativeButton={false}
                            >
                                <FileText data-icon="inline-start" />
                                Proposal QCC
                            </Button>
                        ) : undefined
                    }
                />

                <Card className="gap-0 py-0">
                    <CardHeader className="border-b py-4">
                        <CardTitle className="text-base font-semibold">
                            Proposal siap dibentuk tim
                        </CardTitle>
                        <CardDescription>
                            {proposals.total} proposal draf dapat ditangani
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="border-b p-4">
                        <form
                            action={teamFormationIndex.url()}
                            method="get"
                            className="flex w-full max-w-xl items-center gap-2"
                        >
                            <div className="relative min-w-0 flex-1">
                                <Search
                                    aria-hidden="true"
                                    className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    aria-label="Cari proposal"
                                    name="search"
                                    defaultValue={filters.search}
                                    placeholder="Cari kode, judul, atau pengaju..."
                                    className="h-9 pl-9"
                                />
                            </div>
                            <Button type="submit" variant="outline" size="sm">
                                Cari
                            </Button>
                        </form>
                    </CardContent>

                    {proposals.data.length === 0 ? (
                        <Empty className="min-h-64 border-0">
                            <EmptyHeader>
                                <EmptyMedia variant="icon">
                                    <UsersRound aria-hidden="true" />
                                </EmptyMedia>
                                <EmptyTitle>
                                    {filters.search
                                        ? 'Proposal tidak ditemukan'
                                        : 'Belum ada proposal draf'}
                                </EmptyTitle>
                                <EmptyDescription>
                                    {filters.search
                                        ? 'Coba kata kunci lain atau hapus pencarian.'
                                        : 'Proposal draf yang dapat Anda tangani akan muncul di sini.'}
                                </EmptyDescription>
                            </EmptyHeader>
                            {!filters.search && (
                                <EmptyContent>
                                    <Button
                                        variant="outline"
                                        render={<Link href={proposalIndex()} />}
                                        nativeButton={false}
                                    >
                                        Buka Proposal QCC
                                        <ArrowRight data-icon="inline-end" />
                                    </Button>
                                </EmptyContent>
                            )}
                        </Empty>
                    ) : (
                        <ul className="divide-y divide-border/60">
                            {proposals.data.map((proposal) => (
                                <li
                                    key={proposal.id}
                                    className="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div className="flex min-w-0 items-start gap-3">
                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                            <UsersRound
                                                aria-hidden="true"
                                                className="size-5"
                                            />
                                        </span>
                                        <div className="grid min-w-0 gap-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <p className="truncate text-sm font-semibold">
                                                    {proposal.title}
                                                </p>
                                                <Badge variant="secondary">
                                                    Draf
                                                </Badge>
                                            </div>
                                            <p className="text-xs text-muted-foreground">
                                                {proposal.code}
                                                {' · '}
                                                {proposal.batch ??
                                                    'Tanpa batch'}
                                                {' · Pengaju: '}
                                                {proposal.proposer ?? '—'}
                                                {proposal.organization
                                                    ? ` · ${proposal.organization}`
                                                    : ''}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {proposal.members_count} anggota
                                                dipilih
                                                {' · '}
                                                {
                                                    proposal.requirements_count
                                                }{' '}
                                                kompetensi dibutuhkan
                                                {' · Diperbarui '}
                                                {formatUpdatedAt(
                                                    proposal.updated_at,
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        className="shrink-0"
                                        render={
                                            <Link
                                                href={proposalCandidates(
                                                    proposal.id,
                                                )}
                                            />
                                        }
                                        nativeButton={false}
                                    >
                                        Atur susunan tim
                                        <ArrowRight data-icon="inline-end" />
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}

                    {proposals.last_page > 1 && (
                        <CardContent className="flex flex-col gap-3 border-t px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-sm text-muted-foreground">
                                Menampilkan {proposals.from}–{proposals.to} dari{' '}
                                {proposals.total} proposal
                            </p>
                            <nav
                                aria-label="Navigasi halaman Pembentukan Tim"
                                className="flex flex-wrap gap-2"
                            >
                                {proposals.links.map((link, index) => (
                                    <Button
                                        key={`${link.label}-${index}`}
                                        variant={
                                            link.active ? 'default' : 'outline'
                                        }
                                        size="sm"
                                        disabled={!link.url}
                                        render={
                                            link.url ? (
                                                <Link
                                                    href={link.url}
                                                    preserveScroll
                                                />
                                            ) : undefined
                                        }
                                        nativeButton={!link.url}
                                    >
                                        {link.label
                                            .replace(/&laquo;|&raquo;/g, '')
                                            .trim()}
                                    </Button>
                                ))}
                            </nav>
                        </CardContent>
                    )}
                </Card>
            </div>
        </>
    );
}
