import { Head, usePage } from '@inertiajs/react';
import { CalendarOff } from 'lucide-react';
import { AdminOverview } from '@/components/dashboard/admin-overview';
import type { AdminOverviewData } from '@/components/dashboard/admin-overview';
import { PanelEmpty } from '@/components/dashboard/dashboard-kit';
import { PageHeader } from '@/components/page-header';
import { ProgramDashboard } from '@/components/program/program-dashboard';
import type { ProgramOverview } from '@/components/program/types';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { dashboard } from '@/routes';

/**
 * Dashboard of Owner QCC, Mentors and admins: the program of the running batch,
 * and for admins the system overview beside it.
 */
export default function Dashboard({
    adminOverview,
    program,
}: {
    adminOverview: AdminOverviewData | null;
    program: ProgramOverview | null;
}) {
    const { auth } = usePage<{ auth: { user: { name: string } | null } }>()
        .props;
    const firstName = auth.user?.name.split(' ')[0] ?? '';
    const programContent = program ? (
        <ProgramDashboard overview={program} />
    ) : (
        <PanelEmpty
            icon={CalendarOff}
            title="Belum ada batch berjalan"
            description="Dashboard program tampil setelah ada batch QCC berstatus aktif."
        />
    );

    return (
        <>
            <Head title="Dashboard" />
            <main className="mx-auto w-full max-w-[1440px] flex-1 px-4 py-6 md:px-6 md:py-8">
                <PageHeader
                    breadcrumbs={[{ title: 'Dashboard', href: dashboard() }]}
                    title="Dashboard"
                    description={`Selamat datang, ${firstName}. Berikut progres program QCC.`}
                />

                {adminOverview ? (
                    <Tabs defaultValue="program" className="gap-6">
                        <TabsList className="w-full justify-start overflow-x-auto sm:w-fit">
                            <TabsTrigger
                                value="program"
                                className="px-3 sm:px-4"
                            >
                                {program
                                    ? `Program ${program.batch.name}`
                                    : 'Program'}
                            </TabsTrigger>
                            <TabsTrigger
                                value="system"
                                className="px-3 sm:px-4"
                            >
                                Ringkasan sistem
                            </TabsTrigger>
                        </TabsList>
                        <TabsContent value="program">
                            {programContent}
                        </TabsContent>
                        <TabsContent value="system">
                            <AdminOverview overview={adminOverview} />
                        </TabsContent>
                    </Tabs>
                ) : (
                    programContent
                )}
            </main>
        </>
    );
}
