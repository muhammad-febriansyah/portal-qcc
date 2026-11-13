import { Bold, Italic, List } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { htmlToRichText, richTextToHtml } from '@/lib/rich-text';
import { cn } from '@/lib/utils';

function focusAtEnd(editor: HTMLElement | null): void {
    if (!editor) return;

    editor.focus();

    const range = document.createRange();
    range.selectNodeContents(editor);
    range.collapse(false);
    window.getSelection()?.removeAllRanges();
    window.getSelection()?.addRange(range);
}

type RichTextareaProps = {
    id?: string;
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    className?: string;
    required?: boolean;
    'aria-describedby'?: string;
    'aria-invalid'?: boolean;
};

/**
 * Text field with bold, italic and bullet list. Formatting is shown directly
 * in the text (no Markdown markers); the value is still stored as Markdown.
 */
export function RichTextarea({
    id,
    value,
    onChange,
    placeholder,
    className,
    required,
    ...aria
}: RichTextareaProps) {
    const editorRef = useRef<HTMLDivElement>(null);
    const lastEmitted = useRef<string | null>(null);

    useEffect(() => {
        const editor = editorRef.current;

        // Skip our own edits, so the caret does not jump while typing.
        if (!editor || value === lastEmitted.current) return;

        editor.innerHTML = richTextToHtml(value);
        lastEmitted.current = value;
    }, [value]);

    // A <label for> does not focus a contentEditable box by itself.
    useEffect(() => {
        if (!id) return;

        const label = document.querySelector<HTMLLabelElement>(
            `label[for="${CSS.escape(id)}"]`,
        );
        const focusEditor = (): void => focusAtEnd(editorRef.current);

        label?.addEventListener('click', focusEditor);

        return () => label?.removeEventListener('click', focusEditor);
    }, [id]);

    function emit(): void {
        const editor = editorRef.current;
        if (!editor) return;

        const next = htmlToRichText(editor);
        lastEmitted.current = next;
        onChange(next);
    }

    function applyFormatting(format: 'bold' | 'italic' | 'list'): void {
        const editor = editorRef.current;
        if (!editor) return;

        editor.focus();
        document.execCommand(
            format === 'list' ? 'insertUnorderedList' : format,
            false,
        );
        emit();
    }

    function pastePlainText(event: React.ClipboardEvent<HTMLDivElement>): void {
        event.preventDefault();
        document.execCommand(
            'insertText',
            false,
            event.clipboardData.getData('text/plain'),
        );
    }

    return (
        <div className="overflow-hidden rounded-lg border border-input focus-within:border-ring focus-within:ring-3 focus-within:ring-ring/50 has-[[aria-invalid=true]]:border-destructive">
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
                    title="Tebal (Ctrl+B)"
                    onMouseDown={(event) => event.preventDefault()}
                    onClick={() => applyFormatting('bold')}
                >
                    <Bold aria-hidden="true" />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    aria-label="Miringkan teks"
                    title="Miring (Ctrl+I)"
                    onMouseDown={(event) => event.preventDefault()}
                    onClick={() => applyFormatting('italic')}
                >
                    <Italic aria-hidden="true" />
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    aria-label="Buat daftar berpoin"
                    title="Daftar berpoin"
                    onMouseDown={(event) => event.preventDefault()}
                    onClick={() => applyFormatting('list')}
                >
                    <List aria-hidden="true" />
                </Button>
            </div>
            <div
                {...aria}
                id={id}
                ref={editorRef}
                role="textbox"
                aria-multiline="true"
                aria-required={required}
                aria-placeholder={placeholder}
                contentEditable
                suppressContentEditableWarning
                data-empty={value.trim() === ''}
                data-placeholder={placeholder}
                onInput={emit}
                onBlur={emit}
                onPaste={pastePlainText}
                className={cn(
                    'relative min-h-32 w-full px-3 py-2 text-base break-words outline-none md:text-sm',
                    '[&_li]:my-0.5 [&_ul]:list-disc [&_ul]:pl-5',
                    'data-[empty=true]:before:pointer-events-none data-[empty=true]:before:absolute data-[empty=true]:before:text-muted-foreground data-[empty=true]:before:content-[attr(data-placeholder)]',
                    className,
                )}
            />
        </div>
    );
}
