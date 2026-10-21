import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { BreadcrumbItem, NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: edit(),
        icon: null,
    },
    {
        title: 'Security',
        href: editSecurity(),
        icon: null,
    },
    {
        title: 'Appearance',
        href: editAppearance(),
        icon: null,
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { url } = usePage();
    const currentPath = url.split('?')[0];
    const page = currentPath.includes('/security')
        ? {
              title: 'Update password',
              description:
                  'Ensure your account is using a long, random password to stay secure',
              breadcrumb: { title: 'Security settings', href: editSecurity() },
          }
        : currentPath.includes('/appearance')
          ? {
                title: 'Appearance settings',
                description: 'Portal QCC uses a consistent light theme.',
                breadcrumb: {
                    title: 'Appearance settings',
                    href: editAppearance(),
                },
            }
          : {
                title: 'Profile',
                description: 'Update your name and email address',
                breadcrumb: { title: 'Profile settings', href: edit() },
            };
    const breadcrumbs: BreadcrumbItem[] = [page.breadcrumb];

    return (
        <main className="mx-auto w-full max-w-[1440px] px-4 py-6 md:px-6 md:py-8">
            <PageHeader
                breadcrumbs={breadcrumbs}
                title={page.title}
                description={page.description}
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav
                        className="flex flex-col space-y-1 space-x-0"
                        aria-label="Settings"
                    >
                        {sidebarNavItems.map((item, index) => (
                            <Button
                                key={`${toUrl(item.href)}-${index}`}
                                size="sm"
                                variant="ghost"
                                nativeButton={false}
                                render={<Link href={item.href} />}
                                className={cn('w-full justify-start', {
                                    'bg-muted': isCurrentOrParentUrl(item.href),
                                })}
                            >
                                {item.icon && (
                                    <item.icon className="h-4 w-4" />
                                )}
                                {item.title}
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className="flex-1 md:max-w-2xl">
                    <section className="max-w-xl space-y-12">
                        {children}
                    </section>
                </div>
            </div>
        </main>
    );
}
