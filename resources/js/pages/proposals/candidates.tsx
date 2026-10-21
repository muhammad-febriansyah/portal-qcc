import { Head } from '@inertiajs/react';
import { Users } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { candidates as candidatesRoute } from '@/routes/proposals';

type Requirement = {
    competency_id: number;
    code: string;
    name: string;
    minimum_level: number | null;
};

type Candidate = {
    user_id: number;
    name: string;
    position: string | null;
    organization: string;
    availability_status: string | null;
    matched_count: number;
    requirement_count: number;
    competencies: {
        competency_id: number;
        self_level: number;
        verified_level: number | null;
    }[];
};

type Props = {
    proposal: { id: number; code: string; title: string };
    requirements: Requirement[];
    candidates: Candidate[];
    limit: number;
};

function initials(name: string): string {
    return name
        .split(' ')
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
}

export default function ProposalCandidates({
    proposal,
    requirements,
    candidates,
    limit,
}: Props) {
    const byId = Object.fromEntries(
        requirements.map((requirement) => [
            requirement.competency_id,
            requirement,
        ]),
    );

    return (
        <>
            <Head title={`Kandidat ${proposal.code}`} />
            <div className="mx-auto w-full max-w-[1440px] px-4 py-6 md:px-6 md:py-8">
                <PageHeader
                    breadcrumbs={[
                        { title: 'Dashboard', href: dashboard() },
                        {
                            title: 'Kandidat Tim',
                            href: candidatesRoute(proposal.id),
                        },
                    ]}
                    title="Kandidat Tim"
                    description={`${proposal.code} · ${proposal.title}`}
                />

                <Card className="mb-6 gap-0 py-0">
                    <CardContent className="grid gap-3 p-5">
                        <p className="text-sm font-medium">
                            Kompetensi yang dibutuhkan
                        </p>
                        {requirements.length === 0 ? (
                            <p className="text-[13px] text-muted-foreground">
                                Proyek ini belum punya kebutuhan kompetensi.
                                Tambahkan dulu agar kandidat bisa dicari.
                            </p>
                        ) : (
                            <div className="flex flex-wrap gap-2">
                                {requirements.map((requirement) => (
                                    <Badge
                                        key={requirement.competency_id}
                                        variant="secondary"
                                        className="font-normal"
                                    >
                                        {requirement.code} · {requirement.name}
                                        {requirement.minimum_level
                                            ? ` (min. level ${requirement.minimum_level})`
                                            : ''}
                                    </Badge>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {requirements.length > 0 && candidates.length === 0 && (
                    <div className="flex min-h-48 flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-border/80 text-center">
                        <Users
                            className="size-6 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <p className="text-sm font-medium">
                            Belum ada kandidat yang cocok
                        </p>
                        <p className="max-w-sm text-xs text-muted-foreground">
                            Tidak ada anggota aktif dan tersedia yang memenuhi
                            kompetensi dan level minimum proyek ini.
                        </p>
                    </div>
                )}

                {candidates.length > 0 && (
                    <>
                        <p className="mb-3 text-[13px] text-muted-foreground">
                            {candidates.length} kandidat teratas (maksimal{' '}
                            {limit}), diurutkan dari yang paling sesuai.
                        </p>
                        <ul className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {candidates.map((candidate) => (
                                <li key={candidate.user_id}>
                                    <Card className="h-full gap-0 py-0">
                                        <CardContent className="grid gap-4 p-5">
                                            <div className="flex items-start gap-3">
                                                <span
                                                    className="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary"
                                                    aria-hidden="true"
                                                >
                                                    {initials(candidate.name)}
                                                </span>
                                                <div className="grid min-w-0">
                                                    <p className="truncate text-sm font-semibold">
                                                        {candidate.name}
                                                    </p>
                                                    <p className="truncate text-xs text-muted-foreground">
                                                        {candidate.position ??
                                                            'Jabatan belum diisi'}{' '}
                                                        ·{' '}
                                                        {candidate.organization}
                                                    </p>
                                                </div>
                                                <Badge
                                                    variant={
                                                        candidate.matched_count ===
                                                        candidate.requirement_count
                                                            ? 'success'
                                                            : 'secondary'
                                                    }
                                                    className="ml-auto shrink-0"
                                                >
                                                    {candidate.matched_count}/
                                                    {
                                                        candidate.requirement_count
                                                    }{' '}
                                                    cocok
                                                </Badge>
                                            </div>
                                            <ul className="grid gap-1.5">
                                                {candidate.competencies.map(
                                                    (competency) => {
                                                        const requirement =
                                                            byId[
                                                                competency
                                                                    .competency_id
                                                            ];

                                                        return (
                                                            <li
                                                                key={
                                                                    competency.competency_id
                                                                }
                                                                className="flex items-center justify-between gap-3 text-[13px]"
                                                            >
                                                                <span className="min-w-0 truncate">
                                                                    {
                                                                        requirement?.name
                                                                    }
                                                                </span>
                                                                <span className="shrink-0 text-xs text-muted-foreground">
                                                                    Mandiri{' '}
                                                                    {
                                                                        competency.self_level
                                                                    }
                                                                    {competency.verified_level !==
                                                                        null &&
                                                                        ` · Verifikasi ${competency.verified_level}`}
                                                                </span>
                                                            </li>
                                                        );
                                                    },
                                                )}
                                            </ul>
                                        </CardContent>
                                    </Card>
                                </li>
                            ))}
                        </ul>
                    </>
                )}
            </div>
        </>
    );
}
