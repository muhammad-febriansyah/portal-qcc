import { ComboboxItem } from '@/components/ui/combobox';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

/**
 * A competency in a picker list. Pointing at it shows the competency's
 * definition beside the list, so people can choose before they click.
 */
export function CompetencyOptionItem<
    T extends { label: string; description?: string | null },
>({ item }: { item: T }) {
    if (!item.description) {
        return <ComboboxItem value={item}>{item.label}</ComboboxItem>;
    }

    return (
        <Tooltip>
            <TooltipTrigger delay={250} render={<ComboboxItem value={item} />}>
                {item.label}
            </TooltipTrigger>
            <TooltipContent
                side="right"
                align="start"
                sideOffset={10}
                className="block max-w-72 px-3 py-2 leading-relaxed"
            >
                <span className="mb-0.5 block font-semibold">{item.label}</span>
                {item.description}
            </TooltipContent>
        </Tooltip>
    );
}
