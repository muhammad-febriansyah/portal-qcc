import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { NavigationSpinner } from '@/components/navigation-spinner';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';

void createInertiaApp({
    title: (title, page) => {
        const siteName =
            page.component === 'errors/show'
                ? 'Portal QCC SIMGROUP'
                : String(page.props.name);

        return title ? `${title} - ${siteName}` : siteName;
    },
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
            case name === 'auth/login':
            case name === 'auth/register':
            case name === 'auth/verify-email':
            case name === 'errors/show':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delay={0}>
                {app}
                <Toaster />
                <NavigationSpinner />
            </TooltipProvider>
        );
    },
    progress: false,
});
