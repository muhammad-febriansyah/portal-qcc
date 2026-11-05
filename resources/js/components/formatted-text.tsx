import { Fragment } from 'react';
import { parseRichText, type RichInline } from '@/lib/rich-text';
import { cn } from '@/lib/utils';

function Inlines({ inlines }: { inlines: RichInline[] }) {
    return inlines.map(({ text, bold, italic }, index) => {
        if (bold) return <strong key={index}>{text}</strong>;
        if (italic) return <em key={index}>{text}</em>;

        return <Fragment key={index}>{text}</Fragment>;
    });
}

/** Shows proposal text with its bold, italic and bullet formatting applied. */
export function FormattedText({
    value,
    empty = '—',
    className,
}: {
    value: string | null | undefined;
    empty?: string;
    className?: string;
}) {
    if (!value?.trim()) {
        return <span className={className}>{empty}</span>;
    }

    return (
        <div className={cn('grid gap-1 break-words', className)}>
            {parseRichText(value.trim()).map((block, index) =>
                block.type === 'list' ? (
                    <ul key={index} className="list-disc space-y-0.5 pl-5">
                        {block.items.map((item, itemIndex) => (
                            <li key={itemIndex}>
                                <Inlines inlines={item} />
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p key={index} className="min-h-[1lh]">
                        <Inlines inlines={block.inlines} />
                    </p>
                ),
            )}
        </div>
    );
}
