import { Bold, Italic, List } from 'lucide-react';
import { useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';

type RichTextareaProps = Omit<
    React.ComponentProps<typeof Textarea>,
    'onChange'
> & {
    onChange: (value: string) => void;
};

export function RichTextarea({
    className,
    onChange,
    value,
    ...props
}: RichTextareaProps) {
    const textareaRef = useRef<HTMLTextAreaElement>(null);

    function applyFormatting(format: 'bold' | 'italic' | 'list'): void {
        const textarea = textareaRef.current;
        if (!textarea) return;

        const text = String(value ?? '');
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const selected = text.slice(start, end);
        let insertion = '';
        let selectionStart = start;
        let selectionEnd = start;

        if (format === 'list') {
            const source = selected || 'Item daftar';
            insertion = source
                .split('\n')
                .map((line) => (line.startsWith('- ') ? line : `- ${line}`))
                .join('\n');
            selectionStart = start;
            selectionEnd = start + insertion.length;
        } else {
            const marker = format === 'bold' ? '**' : '*';
            const source = selected || 'teks';
            insertion = `${marker}${source}${marker}`;
            selectionStart = start + marker.length;
            selectionEnd = selectionStart + source.length;
        }

        onChange(`${text.slice(0, start)}${insertion}${text.slice(end)}`);
        requestAnimationFrame(() => {
            textarea.focus();
            textarea.setSelectionRange(selectionStart, selectionEnd);
        });
    }

    return (
        <div className="overflow-hidden rounded-lg border border-input focus-within:border-ring focus-within:ring-3 focus-within:ring-ring/50">
            <div
                aria-label="Pemformatan teks"
                className="flex items-center gap-1 border-b bg-muted/30 px-2 py-1"
                role="toolbar"
            >
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    aria-label="Tebalkan teks"
                    title="Tebal"
                    onClick={() => applyFormatting('bold')}
                >
                    <Bold aria-hidden="true" />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    aria-label="Miringkan teks"
                    title="Miring"
                    onClick={() => applyFormatting('italic')}
                >
                    <Italic aria-hidden="true" />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    aria-label="Buat daftar"
                    title="Daftar berpoin"
                    onClick={() => applyFormatting('list')}
                >
                    <List aria-hidden="true" />
                </Button>
                <span className="ml-2 text-xs text-muted-foreground">
                    Pemformatan dasar
                </span>
            </div>
            <Textarea
                {...props}
                ref={textareaRef}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className={`min-h-32 rounded-none border-0 shadow-none focus-visible:border-0 focus-visible:ring-0 ${className ?? ''}`}
            />
        </div>
    );
}
