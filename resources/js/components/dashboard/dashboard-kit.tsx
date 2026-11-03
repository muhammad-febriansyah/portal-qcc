import { Inbox } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

/** Hairline border and a soft two-layer shadow. Used by every dashboard panel. */
export const surfaceClassName =
    'border-border/60 shadow-[0_1px_2px_rgba(24,35,55,0.03),0_8px_24px_-12px_rgba(24,35,55,0.10)]';

export const numberFormat = new Intl.NumberFormat('id-ID');

/** Charts need the browser, so they render only after the first mount. */
export function useMounted(): boolean {
    const [isMounted, setIsMounted] = useState(false);

    useEffect(() => {
        setIsMounted(true);
    }, []);

    return isMounted;
}

export function SectionTitle({
    title,
    description,
}: {
    title: string;
    description?: string;
}) {
    return (
        <div className="grid gap-1">
            <h2 className="text-lg font-semibold tracking-tight text-foreground">
                {title}
            </h2>
            {description && (
                <p className="text-sm text-muted-foreground">{description}</p>
            )}
        </div>
    );
}

export function DashboardPanel({
    title,
    description,
    action,
    children,
    className,
    contentClassName,
}: {
    title: string;
    description?: string;
    action?: ReactNode;
    children: ReactNode;
    className?: string;
    contentClassName?: string;
}) {
    return (
        <Card className={cn('min-w-0 gap-0 py-0', surfaceClassName, className)}>
            <div className="flex flex-col gap-3 px-5 pt-5 sm:flex-row sm:items-start sm:justify-between sm:px-6 sm:pt-6">
                <div className="grid min-w-0 gap-1">
                    <h3 className="text-base font-semibold text-foreground">
                        {title}
                    </h3>
                    {description && (
                        <p className="text-sm text-muted-foreground">
                            {description}
                        </p>
                    )}
                </div>
                {action}
            </div>
            <div
                className={cn(
                    'min-w-0 p-5 pt-5 sm:p-6 sm:pt-5',
                    contentClassName,
                )}
            >
                {children}
            </div>
        </Card>
    );
}

export function KpiCard({
    label,
    value,
    helper,
    icon: Icon,
    tone,
    footer,
}: {
    label: string;
    value: ReactNode;
    helper: string;
    icon: LucideIcon;
    tone: string;
    footer?: ReactNode;
}) {
    return (
        <Card className={cn('gap-0 py-0', surfaceClassName)}>
            <div className="grid gap-4 p-5 sm:p-6">
                <div className="flex items-start justify-between gap-3">
                    <p className="text-sm font-medium text-muted-foreground">
                        {label}
                    </p>
                    <span
                        className={cn(
                            'flex size-9 shrink-0 items-center justify-center rounded-lg',
                            tone,
                        )}
                    >
                        <Icon className="size-[18px]" aria-hidden="true" />
                    </span>
                </div>
                <div className="grid gap-1.5">
                    <p className="text-3xl leading-none font-semibold tracking-tight text-foreground tabular-nums">
                        {value}
                    </p>
                    <p className="text-xs text-muted-foreground">{helper}</p>
                </div>
                {footer}
            </div>
        </Card>
    );
}

export function ProgressBar({
    value,
    label,
    className,
}: {
    value: number;
    label: string;
    className?: string;
}) {
    const percent = Math.min(100, Math.max(0, Math.round(value)));

    return (
        <div
            role="progressbar"
            aria-label={label}
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={percent}
            className={cn(
                'h-1.5 overflow-hidden rounded-full bg-muted',
                className,
            )}
        >
            <div
                className="h-full rounded-full bg-primary transition-[width] duration-500"
                style={{ width: `${percent}%` }}
            />
        </div>
    );
}

export function PanelEmpty({
    icon: Icon = Inbox,
    title,
    description,
}: {
    icon?: LucideIcon;
    title: string;
    description: string;
}) {
    return (
        <div className="flex min-h-40 flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-border px-4 py-8 text-center">
            <span className="flex size-9 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                <Icon className="size-[18px]" aria-hidden="true" />
            </span>
            <p className="text-sm font-medium text-foreground">{title}</p>
            <p className="max-w-xs text-xs text-muted-foreground">
                {description}
            </p>
        </div>
    );
}

export function ChartFallback({ height }: { height: number }) {
    return (
        <div role="status" aria-label="Memuat grafik" className="grid gap-3">
            <Skeleton className="w-full" style={{ height }} />
            <span className="sr-only">Memuat grafik</span>
        </div>
    );
}
