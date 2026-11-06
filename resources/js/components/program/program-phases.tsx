import { format, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import { Check, Lock } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { ProgramPhase, ProgramPhaseKey } from './types';

function shortDate(value: string): string {
    return format(parseISO(value), 'd MMM yyyy', { locale: indonesianLocale });
}

export function phasePeriod(phase: ProgramPhase): string {
    if (phase.starts_on && phase.ends_on) {
        return `${shortDate(phase.starts_on)} – ${shortDate(phase.ends_on)}`;
    }

    if (phase.starts_on) {
        return `Mulai ${shortDate(phase.starts_on)}`;
    }

    return 'Jadwal belum ditentukan';
}

/**
 * The three program phases in a row. Registration and any phase that has started
 * can be opened; a later phase stays locked until its start date.
 */
export function ProgramPhases({
    phases,
    selected,
    onSelect,
    compact = false,
}: {
    phases: ProgramPhase[];
    selected?: ProgramPhaseKey | null;
    onSelect?: (key: ProgramPhaseKey) => void;
    compact?: boolean;
}) {
    return (
        <ol
            className={cn(
                'grid gap-2',
                compact ? 'sm:grid-cols-3' : 'md:grid-cols-3',
            )}
            aria-label="Fase program QCC"
        >
            {phases.map((phase) => {
                const locked = !phase.viewable;
                const upcoming = phase.state === 'upcoming';
                const isSelected = selected === phase.key;
                const content = (
                    <>
                        <span
                            className={cn(
                                'flex size-9 shrink-0 items-center justify-center rounded-full border-2 text-sm font-semibold tabular-nums',
                                phase.state === 'done' &&
                                    'border-primary bg-primary/10 text-primary',
                                phase.state === 'active' &&
                                    'border-primary bg-primary text-primary-foreground',
                                locked &&
                                    'border-border bg-muted text-muted-foreground',
                                upcoming &&
                                    !locked &&
                                    'border-primary/50 bg-card text-primary',
                            )}
                        >
                            {phase.state === 'done' ? (
                                <Check className="size-4" aria-hidden="true" />
                            ) : locked ? (
                                <Lock className="size-3.5" aria-hidden="true" />
                            ) : (
                                String(phase.number).padStart(2, '0')
                            )}
                        </span>
                        <span className="grid min-w-0 gap-0.5 text-left">
                            <span className="flex flex-wrap items-center gap-1.5">
                                <span
                                    className={cn(
                                        'text-sm leading-snug font-semibold',
                                        locked
                                            ? 'text-muted-foreground'
                                            : 'text-foreground',
                                    )}
                                >
                                    {phase.label}
                                </span>
                                {phase.state === 'active' && (
                                    <Badge variant="success">Aktif</Badge>
                                )}
                                {phase.state === 'done' && (
                                    <Badge variant="secondary">Selesai</Badge>
                                )}
                                {upcoming && !locked && (
                                    <Badge variant="info">Segera dimulai</Badge>
                                )}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {locked
                                    ? `Terkunci · ${phasePeriod(phase)}`
                                    : phasePeriod(phase)}
                            </span>
                        </span>
                    </>
                );
                const itemClassName = cn(
                    'flex h-full w-full items-center gap-3 rounded-xl border px-3.5 py-3 transition-colors',
                    isSelected
                        ? 'border-primary/40 bg-primary/[0.06]'
                        : 'border-border/70 bg-card',
                    locked && 'bg-muted/40',
                );

                return (
                    <li
                        key={phase.key}
                        aria-current={
                            phase.state === 'active' ? 'step' : undefined
                        }
                    >
                        {onSelect && !locked ? (
                            <button
                                type="button"
                                onClick={() => onSelect(phase.key)}
                                aria-pressed={isSelected}
                                className={cn(
                                    itemClassName,
                                    'cursor-pointer hover:border-primary/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                )}
                            >
                                {content}
                            </button>
                        ) : (
                            <div className={itemClassName}>{content}</div>
                        )}
                    </li>
                );
            })}
        </ol>
    );
}
