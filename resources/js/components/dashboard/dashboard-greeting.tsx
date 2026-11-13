import { format } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import type { ReactNode } from 'react';

/** Time-of-day greeting in Indonesian, by the viewer's clock. */
function greetingFor(hour: number): string {
    if (hour < 11) return 'Selamat pagi';
    if (hour < 15) return 'Selamat siang';
    if (hour < 18) return 'Selamat sore';

    return 'Selamat malam';
}

/**
 * The top of every dashboard: today's date, a greeting by first name, one
 * line of context and room on the right for the batch or role at a glance.
 */
export function DashboardGreeting({
    name,
    description,
    aside,
}: {
    name: string;
    description: string;
    aside?: ReactNode;
}) {
    const now = new Date();

    return (
        <section
            aria-label="Sapaan"
            className="relative mb-6 overflow-hidden rounded-xl border border-primary/15 bg-primary/[0.05] px-5 py-5 sm:px-6"
        >
            <svg
                aria-hidden="true"
                viewBox="0 0 200 200"
                className="pointer-events-none absolute -top-16 -right-10 size-56 text-primary/[0.07]"
            >
                <circle
                    cx="100"
                    cy="100"
                    r="70"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="18"
                />
                <circle
                    cx="100"
                    cy="100"
                    r="34"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="12"
                />
            </svg>
            <div className="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="grid gap-1">
                    <p className="text-xs font-medium text-primary">
                        {format(now, 'EEEE, d MMMM yyyy', {
                            locale: indonesianLocale,
                        })}
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                        {greetingFor(now.getHours())}
                        {name ? `, ${name}` : ''}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                </div>
                {aside && (
                    <div className="flex flex-wrap items-center gap-2 sm:justify-end">
                        {aside}
                    </div>
                )}
            </div>
        </section>
    );
}

/** A small label for the greeting's right side, e.g. the active phase. */
export function GreetingChip({
    label,
    value,
}: {
    label: string;
    value: string;
}) {
    return (
        <span className="inline-flex items-center gap-2 rounded-lg border border-border/70 bg-card px-3 py-1.5 text-xs">
            <span className="text-muted-foreground">{label}</span>
            <span className="font-semibold text-foreground">{value}</span>
        </span>
    );
}
