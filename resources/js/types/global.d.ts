import type { Auth } from '@/types/auth';
import '@inertiajs/core';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            branding: {
                name: string;
                logoUrl: string | null;
                faviconUrl: string | null;
                keywords: string;
                description: string;
            };
            auth: Auth;
            notifications: {
                unread_count: number;
                recent: {
                    id: string;
                    title: string;
                    message: string;
                    url: string | null;
                    read_at: string | null;
                    created_at: string | null;
                    project_code: string | null;
                    kind: string;
                    kind_label: string;
                    kind_icon: string;
                }[];
            };
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
