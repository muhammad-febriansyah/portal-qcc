import { Head, usePage } from '@inertiajs/react';
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';

export default function AppLayout({ children }: { children: React.ReactNode }) {
    const { branding } = usePage().props;

    return (
        <>
            <Head>
                {branding.faviconUrl && (
                    <link rel="icon" href={branding.faviconUrl} />
                )}
                <meta name="description" content={branding.description} />
                <meta name="keywords" content={branding.keywords} />
            </Head>
            <AppLayoutTemplate>{children}</AppLayoutTemplate>
        </>
    );
}
