import type { ReactNode } from 'react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
    title: string;
    description: string;
    actions?: ReactNode;
};

export function PageHeader({
    breadcrumbs = [],
    title,
    description,
    actions,
}: Props) {
    return (
        <header className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between [&_[data-slot=breadcrumb-list]]:text-xs">
            <div className="min-w-0 space-y-2.5">
                <Breadcrumbs breadcrumbs={breadcrumbs} />
                <div className="space-y-1">
                    <h1 className="text-[22px] leading-8 font-semibold tracking-tight text-foreground sm:text-2xl">
                        {title}
                    </h1>
                    <p className="text-[13px] text-muted-foreground">
                        {description}
                    </p>
                </div>
            </div>
            {actions && (
                <div className="flex shrink-0 items-center gap-2">
                    {actions}
                </div>
            )}
        </header>
    );
}
