import { Head, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    CalendarDays,
    CalendarX2,
    ClipboardList,
    FileText,
    FolderKanban,
    Inbox,
} from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';

type SummaryItem = {
    label: string;
    icon: LucideIcon;
};

const summaryItems: SummaryItem[] = [
    { label: 'Tugas tertunda', icon: ClipboardList },
    { label: 'Proposal', icon: FileText },
    { label: 'Proyek aktif', icon: FolderKanban },
    { label: 'Mentoring terjadwal', icon: CalendarDays },
];

const qccSteps: string[] = [
    'Menentukan Tema Improvement',
    'Menetapkan Target',
    'Menemukan Sebab-Sebab yang Mungkin',
    'Menentukan Sebab-Sebab yang Dominan',
    'Membuat Rencana Perbaikan',
    'Implementasi Rencana Perbaikan',
    'Evaluasi Hasil Perbaikan',
    'Standarisasi & Rencana Pencegahan',
];

function SummaryCard({ label, icon: Icon }: SummaryItem) {
    return (
        <Card className="gap-0 py-0">
            <CardContent className="flex items-start justify-between gap-3 p-5">
                <div className="space-y-1">
                    <p className="text-[13px] text-muted-foreground">{label}</p>
                    <p className="text-2xl font-semibold text-foreground">
                        &mdash;
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Belum ada data
                    </p>
                </div>
                <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/8 text-primary">
                    <Icon className="size-[18px]" aria-hidden="true" />
                </span>
            </CardContent>
        </Card>
    );
}

function EmptyState({
    icon: Icon,
    title,
    description,
}: {
    icon: LucideIcon;
    title: string;
    description: string;
}) {
    return (
        <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed border-border px-6 py-8 text-center">
            <span className="flex size-10 items-center justify-center rounded-full bg-muted">
                <Icon
                    className="size-[18px] text-muted-foreground"
                    aria-hidden="true"
                />
            </span>
            <p className="text-sm font-medium">{title}</p>
            <p className="max-w-64 text-xs text-muted-foreground">
                {description}
            </p>
        </div>
    );
}

export default function Dashboard() {
    const { auth } = usePage().props;
    const firstName = auth.user?.name.split(' ')[0] ?? '';

    return (
        <>
            <Head title="Dashboard" />
            <main className="mx-auto w-full max-w-[1440px] flex-1 px-4 py-6 md:px-6 md:py-8">
                <PageHeader
                    breadcrumbs={[{ title: 'Dashboard', href: dashboard() }]}
                    title="Dashboard"
                    description={`Selamat datang, ${firstName}. Berikut ringkasan kerja QCC Anda.`}
                />

                <div className="flex flex-col gap-6">
                    <section
                        aria-label="Ringkasan"
                        className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                    >
                        {summaryItems.map((item) => (
                            <SummaryCard key={item.label} {...item} />
                        ))}
                    </section>

                    <div className="grid gap-6 lg:grid-cols-3">
                        <Card className="lg:col-span-2">
                            <CardHeader className="border-b pb-4">
                                <CardTitle className="text-base font-semibold">
                                    Progres 8 Step QCC
                                </CardTitle>
                                <CardDescription className="text-[13px]">
                                    Progres tampil setelah proyek QCC Anda
                                    disetujui dan berjalan.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <ol className="divide-y divide-border">
                                    {qccSteps.map((step, index) => (
                                        <li
                                            key={step}
                                            className="flex items-center gap-3 py-3 first:pt-1 last:pb-1"
                                        >
                                            <span className="flex size-7 shrink-0 items-center justify-center rounded-full border border-border text-xs font-medium text-muted-foreground">
                                                {index + 1}
                                            </span>
                                            <span className="min-w-0 flex-1 truncate text-[13px] text-foreground">
                                                {step}
                                            </span>
                                            <Badge
                                                variant="secondary"
                                                className="font-normal text-muted-foreground"
                                            >
                                                Belum dimulai
                                            </Badge>
                                        </li>
                                    ))}
                                </ol>
                            </CardContent>
                        </Card>

                        <div className="flex flex-col gap-6">
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base font-semibold">
                                        Tugas Tertunda
                                    </CardTitle>
                                    <CardDescription className="text-[13px]">
                                        Item yang menunggu tindakan Anda.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <EmptyState
                                        icon={Inbox}
                                        title="Tidak ada tugas"
                                        description="Review, revisi, dan approval yang perlu Anda kerjakan akan muncul di sini."
                                    />
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base font-semibold">
                                        Jadwal Mentoring
                                    </CardTitle>
                                    <CardDescription className="text-[13px]">
                                        Sesi mentoring dan bi-weekly terdekat.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <EmptyState
                                        icon={CalendarX2}
                                        title="Belum ada jadwal"
                                        description="Jadwal akan tampil setelah tim dan mentor ditetapkan."
                                    />
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </main>
        </>
    );
}
