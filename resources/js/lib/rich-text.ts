/**
 * Proposal text is stored as a small Markdown subset: **bold**, *italic*
 * and "- " bullet lines. The editor shows it already formatted, so users
 * never see these markers; these helpers convert in both directions.
 */

export type RichInline = { text: string; bold: boolean; italic: boolean };
export type RichBlock =
    | { type: 'paragraph'; inlines: RichInline[] }
    | { type: 'list'; items: RichInline[][] };

const INLINE_PATTERN = /(\*\*[^*\n]+\*\*|\*[^*\n]+\*)/g;

export function parseInline(line: string): RichInline[] {
    return line
        .split(INLINE_PATTERN)
        .filter((part) => part !== '')
        .map((part) => {
            if (/^\*\*[^*\n]+\*\*$/.test(part)) {
                return { text: part.slice(2, -2), bold: true, italic: false };
            }

            if (/^\*[^*\n]+\*$/.test(part)) {
                return { text: part.slice(1, -1), bold: false, italic: true };
            }

            return { text: part, bold: false, italic: false };
        });
}

export function parseRichText(value: string): RichBlock[] {
    const blocks: RichBlock[] = [];

    for (const line of value.replace(/\r\n?/g, '\n').split('\n')) {
        const bullet = /^\s*[-•]\s+(.*)$/.exec(line);

        if (bullet) {
            const last = blocks.at(-1);
            const item = parseInline(bullet[1]);

            if (last?.type === 'list') {
                last.items.push(item);
            } else {
                blocks.push({ type: 'list', items: [item] });
            }

            continue;
        }

        blocks.push({ type: 'paragraph', inlines: parseInline(line) });
    }

    return blocks;
}

function escapeHtml(text: string): string {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function inlinesToHtml(inlines: RichInline[]): string {
    return inlines
        .map(({ text, bold, italic }) => {
            const escaped = escapeHtml(text);

            if (bold) return `<strong>${escaped}</strong>`;
            if (italic) return `<em>${escaped}</em>`;

            return escaped;
        })
        .join('');
}

/** Stored text to editor HTML. Every value is escaped, so no user HTML runs. */
export function richTextToHtml(value: string): string {
    if (value === '') return '';

    return parseRichText(value)
        .map((block) => {
            if (block.type === 'list') {
                return `<ul>${block.items.map((item) => `<li>${inlinesToHtml(item) || '<br>'}</li>`).join('')}</ul>`;
            }

            return `<div>${inlinesToHtml(block.inlines) || '<br>'}</div>`;
        })
        .join('');
}

function inlineMarkdown(node: Node, bold = false, italic = false): string {
    if (node.nodeType === Node.TEXT_NODE) {
        const text = (node.textContent ?? '').replace(/ /g, ' ');

        if (text.trim() === '') return text;

        const leading = /^\s*/.exec(text)?.[0] ?? '';
        const trailing = /\s*$/.exec(text)?.[0] ?? '';
        const core = text.trim();

        if (bold) return `${leading}**${core}**${trailing}`;
        if (italic) return `${leading}*${core}*${trailing}`;

        return text;
    }

    if (!(node instanceof HTMLElement)) return '';

    const tag = node.tagName.toLowerCase();

    if (tag === 'br') return '\n';

    const style = node.style;
    const isBold =
        bold ||
        tag === 'b' ||
        tag === 'strong' ||
        style.fontWeight === 'bold' ||
        Number(style.fontWeight) >= 600;
    const isItalic =
        !isBold &&
        (italic || tag === 'i' || tag === 'em' || style.fontStyle === 'italic');

    return Array.from(node.childNodes)
        .map((child) => inlineMarkdown(child, isBold, isItalic))
        .join('');
}

const BLOCK_TAGS = new Set(['div', 'p', 'ul', 'ol', 'li', 'h1', 'h2', 'h3']);

function blockLines(node: Node, lines: string[]): void {
    let inline = '';
    const flush = (): void => {
        if (inline !== '') {
            lines.push(...inline.split('\n'));
            inline = '';
        }
    };

    for (const child of Array.from(node.childNodes)) {
        const tag =
            child instanceof HTMLElement ? child.tagName.toLowerCase() : '';

        if (tag === 'ul' || tag === 'ol') {
            flush();

            for (const item of Array.from(child.childNodes)) {
                const text = inlineMarkdown(item).replace(/\n+/g, ' ').trim();
                lines.push(`- ${text}`);
            }

            continue;
        }

        if (BLOCK_TAGS.has(tag)) {
            flush();
            const nested: string[] = [];
            blockLines(child, nested);
            lines.push(...(nested.length > 0 ? nested : ['']));

            continue;
        }

        inline += inlineMarkdown(child);
    }

    flush();
}

/** Editor HTML back to stored text. */
export function htmlToRichText(root: HTMLElement): string {
    const lines: string[] = [];
    blockLines(root, lines);

    return lines
        .map((line) => line.replace(/\*\*\*\*/g, '').replace(/\s+$/, ''))
        .join('\n')
        .replace(/\n{3,}/g, '\n\n')
        .trim();
}
