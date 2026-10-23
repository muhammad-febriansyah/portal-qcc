import { CircleHelp } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

export type HelpSection = {
    title: string;
    /** Short paragraph shown above the steps. */
    intro?: ReactNode;
    /** Numbered steps. */
    steps?: ReactNode[];
    /** Highlighted remark shown below the steps. */
    note?: ReactNode;
};

type Props = {
    title: string;
    description: string;
    sections: HelpSection[];
    /** Label of the button that opens the dialog. */
    triggerLabel?: string;
};

/**
 * A button that opens a large dialog with the how-to of a feature.
 */
export function HelpDialog({
    title,
    description,
    sections,
    triggerLabel = 'Tata cara',
}: Props) {
    return (
        <Dialog>
            <DialogTrigger
                render={
                    <Button type="button" variant="outline">
                        <CircleHelp
                            data-icon="inline-start"
                            aria-hidden="true"
                        />
                        {triggerLabel}
                    </Button>
                }
            />
            <DialogContent className="max-h-[90vh] gap-0 overflow-y-auto p-0 sm:max-w-4xl">
                <DialogHeader className="border-b px-6 py-5">
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>

                <div className="grid gap-8 px-6 py-6">
                    {sections.map((section, index) => (
                        <section key={section.title} className="grid gap-3">
                            <h3 className="flex items-center gap-3 text-base font-semibold">
                                <span
                                    aria-hidden="true"
                                    className="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary"
                                >
                                    {index + 1}
                                </span>
                                {section.title}
                            </h3>

                            <div className="grid gap-3 pl-10 text-sm leading-6 text-muted-foreground">
                                {section.intro && <p>{section.intro}</p>}

                                {section.steps && (
                                    <ol className="grid list-decimal gap-2 pl-5 marker:font-medium marker:text-foreground">
                                        {section.steps.map(
                                            (step, stepIndex) => (
                                                <li key={stepIndex}>{step}</li>
                                            ),
                                        )}
                                    </ol>
                                )}

                                {section.note && (
                                    <p className="rounded-lg border border-primary/20 bg-primary/5 px-4 py-3 text-foreground">
                                        {section.note}
                                    </p>
                                )}
                            </div>
                        </section>
                    ))}
                </div>

                <DialogFooter className="border-t px-6 py-4">
                    <p className="mr-auto text-xs text-muted-foreground">
                        Butuh bantuan lain? Hubungi Owner QCC atau tim pengelola
                        portal.
                    </p>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
