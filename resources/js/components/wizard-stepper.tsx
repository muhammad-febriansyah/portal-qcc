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
 * Progress through a multi step form: numbered circles joined by a progress line,
 * with the full step name underneath. Done steps show a check, the open step is
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
            <ol className="flex items-start">
                {steps.map((step, index) => {
                    const isCurrent = step.number === current;
                    const isDone = step.number < reached;
                    const isLocked = step.number > reached;
                    // The line into this step is filled once the step can be opened.
                    const lineFilled = step.number <= reached;

                    return (
                        <li
                            key={step.number}
                            className="relative flex min-w-0 flex-1 justify-center"
                        >
                            {index > 0 && (
                                <span
                                    aria-hidden="true"
                                    className={cn(
                                        'absolute top-4 right-[calc(50%+1.25rem)] left-[calc(-50%+1.25rem)] h-0.5 -translate-y-1/2 rounded-full',
                                        lineFilled ? 'bg-primary' : 'bg-border',
                                    )}
                                />
                            )}
                            <button
                                type="button"
                                disabled={isLocked}
                                onClick={() => onSelect(step.number)}
                                aria-current={isCurrent ? 'step' : undefined}
                                aria-label={`Langkah ${step.number}: ${step.label}${isDone ? ', selesai' : ''}${isLocked ? ', belum bisa dibuka' : ''}`}
                                className="group relative flex w-full min-w-0 flex-col items-center gap-2 rounded-lg px-1 pb-1 text-center outline-none focus-visible:ring-3 focus-visible:ring-ring/50 disabled:cursor-not-allowed"
                            >
                                <span
                                    className={cn(
                                        'flex size-8 shrink-0 items-center justify-center rounded-full border-2 text-sm font-semibold transition-colors',
                                        isCurrent &&
                                            'border-primary bg-primary text-primary-foreground shadow-sm ring-4 ring-primary/15',
                                        isDone &&
                                            !isCurrent &&
                                            'border-primary bg-primary/10 text-primary group-hover:bg-primary/20',
                                        !isCurrent &&
                                            !isDone &&
                                            !isLocked &&
                                            'border-primary/50 bg-background text-foreground group-hover:border-primary',
                                        isLocked &&
                                            'border-border bg-muted text-muted-foreground',
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
                                <span
                                    className={cn(
                                        'hidden text-xs leading-snug text-balance sm:block',
                                        isCurrent
                                            ? 'font-semibold text-primary'
                                            : isLocked
                                              ? 'text-muted-foreground'
                                              : 'font-medium text-foreground',
                                    )}
                                >
                                    {step.label}
                                </span>
                            </button>
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
