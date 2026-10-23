import { usePage } from '@inertiajs/react';

/**
 * The website name saved in Pengaturan Sistem. Use it in any copy that names the
 * portal, so the text follows the database instead of a hardcoded name.
 */
export function useAppName(): string {
    return usePage().props.branding.name;
}
