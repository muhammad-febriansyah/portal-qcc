import { usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { PageHeader } from '@/components/page-header';
import { useAppName } from '@/hooks/use-app-name';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { BreadcrumbItem } from '@/types';

export default function SettingsLayout({ children }: PropsWithChildren) {
    const appName = useAppName();
    const pages = {
        profile: {
            title: 'Profil Saya',
            description: `Kelola identitas kerja dan keamanan akun Anda di ${appName}.`,
            breadcrumb: { title: 'Profil Saya', href: edit() },
        },
        security: {
            title: 'Keamanan Akun',
            description: 'Ubah kata sandi untuk menjaga keamanan akun.',
            breadcrumb: { title: 'Pengaturan Keamanan', href: editSecurity() },
        },
        appearance: {
            title: 'Tampilan',
            description: `${appName} menggunakan tampilan yang konsisten.`,
            breadcrumb: {
                title: 'Pengaturan Tampilan',
                href: editAppearance(),
            },
        },
    };
    const { url } = usePage();
    const currentPath = url.split('?')[0];
    const page = currentPath.includes('/security')
        ? pages.security
        : currentPath.includes('/appearance')
          ? pages.appearance
          : pages.profile;
    const breadcrumbs: BreadcrumbItem[] = [page.breadcrumb];

    return (
        <main className="mx-auto w-full max-w-[1440px] px-4 py-6 md:px-6 md:py-8">
            <PageHeader
                breadcrumbs={breadcrumbs}
                title={page.title}
                description={page.description}
            />

            {children}
        </main>
    );
}
