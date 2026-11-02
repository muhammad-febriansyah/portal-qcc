import { Check, Lock } from 'lucide-react';
import { cn } from '@/lib/utils';

export type WizardStep = {
    number: number;
    label: string;
};

type Props = {
    steps: WizardStep[];
    /** The step that is open now. */
    current: number;
    /** The furthest step that may be opened. Steps before it count as done. */
    reached: number;
    onSelect: (step: number) => void;
    label?: string;
};

/**
 * Progress through a multi step form. Done steps show a check, the open step is
 * highlighted and steps that cannot be opened yet are locked.
 */
export function WizardStepper({
    steps,
    current,
    reached,
    onSelect,
    label = 'Tahapan',
}: Props) {
    return (
        <nav aria-label={label}>
            <ol className="grid grid-cols-5 gap-1.5 sm:gap-3">
                {steps.map((step) => {
                    const isCurrent = step.number === current;
                    const isDone = step.number < reached;
                    const isLocked = step.number > reached;

                    return (
                        <li key={step.number} className="min-w-0">
                            <button
                                type="button"
                                disabled={isLocked}
                                onClick={() => onSelect(step.number)}
                                aria-current={isCurrent ? 'step' : undefined}
                                aria-label={`Langkah ${step.number}: ${step.label}${isDone ? ', selesai' : ''}${isLocked ? ', belum bisa dibuka' : ''}`}
                                className={cn(
                                    'group flex h-full w-full flex-col items-center gap-2 rounded-xl border px-2 py-3 text-center transition-colors outline-none focus-visible:ring-3 focus-visible:ring-ring/50 sm:flex-row sm:gap-3 sm:px-4 sm:text-left',
                                    isCurrent &&
                                        'border-primary bg-primary/5 shadow-sm',
                                    !isCurrent &&
                                        !isLocked &&
                                        'border-border bg-card hover:border-primary/40 hover:bg-primary/[0.03]',
                                    isLocked &&
                                        'cursor-not-allowed border-dashed border-border bg-muted/30',
                                )}
                            >
                                <span
                                    className={cn(
                                        'flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold transition-colors',
                                        isCurrent &&
                                            'bg-primary text-primary-foreground',
                                        isDone &&
                                            !isCurrent &&
                                            'bg-success/15 text-success',
                                        !isCurrent &&
                                            !isDone &&
                                            !isLocked &&
                                            'bg-muted text-foreground',
                                        isLocked &&
                                            'bg-muted text-muted-foreground',
                                    )}
                                >
                                    {isLocked ? (
                                        <Lock
                                            className="size-3.5"
                                            aria-hidden="true"
                                        />
                                    ) : isDone && !isCurrent ? (
                                        <Check
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    ) : (
                                        step.number
                                    )}
                                </span>
                                <span className="hidden min-w-0 sm:grid sm:gap-0.5">
                                    <span
                                        className={cn(
                                            'text-xs font-medium tracking-wide uppercase',
                                            isCurrent
                                                ? 'text-primary'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        Langkah {step.number}
                                    </span>
                                    <span
                                        className={cn(
                                            'line-clamp-2 text-sm leading-tight',
                                            isCurrent
                                                ? 'font-semibold text-foreground'
                                                : isLocked
                                                  ? 'text-muted-foreground'
                                                  : 'font-medium text-foreground',
                                        )}
                                    >
                                        {step.label}
                                    </span>
                                </span>
                            </button>
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
