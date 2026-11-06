import { format, parseISO } from 'date-fns';
import { id as indonesianLocale } from 'date-fns/locale';
import { Check, Lock } from 'lucide-react';
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

const stateLabels: Record<ProgramPhase['state'], string> = {
    done: 'Selesai',
    active: 'Aktif',
    upcoming: 'Segera dimulai',
};

/**
 * The program phases as a thin row of tabs. Registration and any phase that has
 * started can be opened; a later phase stays locked until its start date.
 */
export function ProgramPhases({
    phases,
    selected,
    onSelect,
}: {
    phases: ProgramPhase[];
    selected?: ProgramPhaseKey | null;
    onSelect?: (key: ProgramPhaseKey) => void;
}) {
    return (
        <ol
            className={cn(
                'grid border-b border-border/70',
                phases.length > 1 && 'sm:grid-cols-3',
            )}
            aria-label="Fase program QCC"
        >
            {phases.map((phase) => {
                const locked = !phase.viewable;
                const isSelected = selected === phase.key;
                const content = (
                    <>
                        <span className="flex items-center gap-2">
                            <span
                                className={cn(
                                    'flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold tabular-nums',
                                    phase.state === 'done' &&
                                        'bg-primary/10 text-primary',
                                    phase.state === 'active' &&
                                        'bg-primary text-primary-foreground',
                                    phase.state === 'upcoming' &&
                                        'bg-muted text-muted-foreground',
                                )}
                            >
                                {phase.state === 'done' ? (
                                    <Check
                                        className="size-3"
                                        aria-hidden="true"
                                    />
                                ) : locked ? (
                                    <Lock
                                        className="size-3"
                                        aria-hidden="true"
                                    />
                                ) : (
                                    phase.number
                                )}
                            </span>
                            <span
                                className={cn(
                                    'text-sm font-medium',
                                    locked
                                        ? 'text-muted-foreground'
                                        : 'text-foreground',
                                )}
                            >
                                {phase.label}
                            </span>
                        </span>
                        <span className="pl-7 text-xs text-muted-foreground">
                            {locked ? 'Terkunci' : stateLabels[phase.state]} ·{' '}
                            {phasePeriod(phase)}
                        </span>
                    </>
                );
                const itemClassName = cn(
                    '-mb-px grid w-full gap-0.5 border-b-2 px-1 py-3 text-left transition-colors',
                    isSelected ? 'border-primary' : 'border-transparent',
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
                                    'cursor-pointer rounded-t-md hover:bg-muted/40 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
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
