import { usePage } from '@inertiajs/react';

/**
 * Website identity from the database (Pengaturan Sistem): the logo when it
 * exists, otherwise the site name as text. The collapsed sidebar shows the
 * favicon, or the first letter of the name.
 */
export default function AppLogo() {
    const { branding } = usePage().props;

    return (
        <>
            {branding.faviconUrl ? (
                <img
                    src={branding.faviconUrl}
                    alt=""
                    aria-hidden="true"
                    width={32}
                    height={32}
                    className="hidden size-8 shrink-0 object-contain group-data-[collapsible=icon]:block"
                />
            ) : (
                <span
                    aria-hidden="true"
                    className="hidden size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-sm font-semibold text-primary group-data-[collapsible=icon]:flex"
                >
                    {branding.name.charAt(0).toUpperCase()}
                </span>
            )}
            <div className="flex min-w-0 items-center group-data-[collapsible=icon]:hidden">
                {branding.logoUrl ? (
                    <img
                        src={branding.logoUrl}
                        alt={branding.name}
                        width={150}
                        height={39}
                        className="h-7 w-auto shrink-0"
                    />
                ) : (
                    <span className="truncate text-sm font-semibold">
                        {branding.name}
                    </span>
                )}
            </div>
        </>
    );
}
