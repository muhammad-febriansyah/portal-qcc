import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { PageHeader } from '@/components/page-header';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { edit as editPassword } from '@/routes/user-password';
import type { BreadcrumbItem, NavItem } from '@/types';

const tabs: NavItem[] = [
    { title: 'Profil', href: edit(), icon: null },
    { title: 'Ubah Password', href: editPassword(), icon: null },
];

const pages = {
    profile: {
        title: 'Profil Saya',
        description: 'Kelola identitas kerja yang terdaftar di Portal QCC.',
        breadcrumb: { title: 'Profil Saya', href: edit() },
    },
    password: {
        title: 'Ubah Password',
        description: 'Perbarui password untuk menjaga keamanan akun Anda.',
        breadcrumb: { title: 'Ubah Password', href: editPassword() },
    },
    security: {
        title: 'Keamanan Akun',
        description: 'Ubah kata sandi untuk menjaga keamanan akun.',
        breadcrumb: { title: 'Pengaturan Keamanan', href: editSecurity() },
    },
    appearance: {
        title: 'Tampilan',
        description: 'Portal QCC menggunakan tampilan yang konsisten.',
        breadcrumb: { title: 'Pengaturan Tampilan', href: editAppearance() },
    },
};

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { url } = usePage();
    const currentPath = url.split('?')[0];
    const page = currentPath.includes('/password')
        ? pages.password
        : currentPath.includes('/security')
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

            <nav
                aria-label="Pengaturan akun"
                className="mb-6 flex gap-1 border-b border-border/70"
            >
                {tabs.map((tab) => {
                    const isActive = isCurrentOrParentUrl(tab.href);

                    return (
                        <Link
                            key={toUrl(tab.href)}
                            href={tab.href}
                            aria-current={isActive ? 'page' : undefined}
                            className={cn(
                                '-mb-px border-b-2 px-4 py-2.5 text-sm font-medium transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring',
                                isActive
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {tab.title}
                        </Link>
                    );
                })}
            </nav>

            {children}
        </main>
    );
}
