import { router } from '@inertiajs/react';
import { ArrowRight, CornerDownLeft, Search, SearchX } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { toast } from 'sonner';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { useNavigationSections } from '@/hooks/use-navigation-sections';
import { cn } from '@/lib/utils';
import type { SidebarItem } from '@/types';

type SearchResult = SidebarItem & { section: string };

function isMacPlatform(): boolean {
    return (
        typeof navigator !== 'undefined' &&
        /Mac|iPhone|iPad/.test(navigator.platform)
    );
}

/**
 * Command palette for jumping to a menu page. It searches only the
 * sections the signed-in user can see, the same list the sidebar uses.
 */
export function MenuSearch() {
    const sections = useNavigationSections();
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [activeIndex, setActiveIndex] = useState(0);
    const [shortcutLabel, setShortcutLabel] = useState('Ctrl K');
    const itemRefs = useRef<(HTMLButtonElement | null)[]>([]);

    const results = useMemo<SearchResult[]>(() => {
        const keyword = query.trim().toLowerCase();

        return sections.flatMap((section) =>
            section.items
                .filter(
                    (item) =>
                        keyword === '' ||
                        item.title.toLowerCase().includes(keyword) ||
                        section.title.toLowerCase().includes(keyword),
                )
                .map((item) => ({ ...item, section: section.title })),
        );
    }, [sections, query]);

    useEffect(() => {
        if (isMacPlatform()) {
            setShortcutLabel('⌘ K');
        }

        const handleShortcut = (event: globalThis.KeyboardEvent) => {
            if (
                event.key.toLowerCase() === 'k' &&
                (event.metaKey || event.ctrlKey)
            ) {
                event.preventDefault();
                setOpen((isOpen) => !isOpen);
            }
        };

        window.addEventListener('keydown', handleShortcut);

        return () => window.removeEventListener('keydown', handleShortcut);
    }, []);

    useEffect(() => {
        itemRefs.current[activeIndex]?.scrollIntoView({ block: 'nearest' });
    }, [activeIndex]);

    const handleOpenChange = (isOpen: boolean) => {
        setOpen(isOpen);

        if (!isOpen) {
            setQuery('');
            setActiveIndex(0);
        }
    };

    const selectResult = (result: SearchResult | undefined) => {
        if (!result) {
            return;
        }

        handleOpenChange(false);

        if (result.href) {
            router.visit(result.href);

            return;
        }

        toast.info(`Halaman ${result.title} belum tersedia.`);
    };

    const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (results.length === 0) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            setActiveIndex((index) => (index + 1) % results.length);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActiveIndex(
                (index) => (index - 1 + results.length) % results.length,
            );
        } else if (event.key === 'Enter') {
            event.preventDefault();
            selectResult(results[activeIndex]);
        }
    };

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="hidden h-10 w-full max-w-sm items-center gap-2 rounded-lg border border-border/70 bg-background px-3 text-left text-[13px] text-muted-foreground transition-colors outline-none hover:border-border hover:bg-card focus-visible:ring-2 focus-visible:ring-ring md:flex"
            >
                <Search className="size-4 shrink-0" aria-hidden="true" />
                <span className="flex-1 truncate">Cari menu...</span>
                <kbd className="rounded border border-border bg-card px-1.5 font-sans text-[10px] font-medium">
                    {shortcutLabel}
                </kbd>
            </button>

            <button
                type="button"
                onClick={() => setOpen(true)}
                aria-label="Cari menu"
                className="flex size-10 items-center justify-center rounded-full border border-border/70 bg-card text-foreground/70 transition-colors outline-none hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring md:hidden"
            >
                <Search className="size-[18px]" aria-hidden="true" />
            </button>

            <Dialog open={open} onOpenChange={handleOpenChange}>
                <DialogContent
                    showCloseButton={false}
                    className="top-[12vh] max-w-[calc(100%-2rem)] translate-y-0 gap-0 overflow-hidden rounded-2xl bg-white/75 p-0 shadow-[0_24px_60px_-16px_rgba(24,35,55,0.3)] ring-1 ring-white/70 backdrop-blur-xl backdrop-saturate-150 sm:max-w-xl"
                >
                    <DialogTitle className="sr-only">Cari menu</DialogTitle>
                    <DialogDescription className="sr-only">
                        Ketik nama menu, gunakan panah atas dan bawah untuk
                        memilih, lalu tekan Enter untuk membuka.
                    </DialogDescription>

                    <div className="p-3">
                        <label className="flex h-11 items-center gap-2.5 rounded-xl bg-white/80 px-3.5 ring-1 ring-border/60 focus-within:ring-2 focus-within:ring-primary/40">
                            <Search
                                className="size-4 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <input
                                autoFocus
                                value={query}
                                onChange={(event) => {
                                    setQuery(event.target.value);
                                    setActiveIndex(0);
                                }}
                                onKeyDown={handleKeyDown}
                                placeholder="Cari menu..."
                                aria-label="Cari menu"
                                role="combobox"
                                aria-expanded="true"
                                aria-controls="menu-search-results"
                                aria-activedescendant={
                                    results[activeIndex]
                                        ? `menu-search-${activeIndex}`
                                        : undefined
                                }
                                className="h-full flex-1 bg-transparent text-sm text-foreground outline-none placeholder:text-muted-foreground"
                            />
                        </label>
                    </div>

                    <div
                        id="menu-search-results"
                        role="listbox"
                        aria-label="Hasil pencarian menu"
                        className="max-h-[min(60vh,420px)] overflow-y-auto px-3 pb-3"
                    >
                        {results.length === 0 ? (
                            <div className="flex flex-col items-center gap-2 py-10 text-center">
                                <SearchX
                                    className="size-6 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <p className="text-sm font-medium">
                                    Menu tidak ditemukan
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Coba kata kunci lain.
                                </p>
                            </div>
                        ) : (
                            results.map((result, index) => {
                                const isNewSection =
                                    index === 0 ||
                                    results[index - 1].section !==
                                        result.section;
                                const Icon = result.icon ?? ArrowRight;
                                const isActive = index === activeIndex;

                                return (
                                    <div
                                        key={`${result.section}-${result.title}`}
                                    >
                                        {isNewSection && (
                                            <p className="px-2.5 pt-3 pb-1.5 text-[11px] font-medium tracking-wider text-muted-foreground uppercase first:pt-1">
                                                {result.section}
                                            </p>
                                        )}
                                        <button
                                            ref={(element) => {
                                                itemRefs.current[index] =
                                                    element;
                                            }}
                                            id={`menu-search-${index}`}
                                            type="button"
                                            role="option"
                                            aria-selected={isActive}
                                            tabIndex={-1}
                                            onMouseMove={() =>
                                                setActiveIndex(index)
                                            }
                                            onClick={() => selectResult(result)}
                                            className={cn(
                                                'flex h-10 w-full items-center gap-3 rounded-lg px-2.5 text-left text-[13px] text-foreground/85 transition-colors outline-none',
                                                isActive &&
                                                    'bg-primary/8 text-primary',
                                            )}
                                        >
                                            <Icon
                                                className={cn(
                                                    'size-4 shrink-0 text-muted-foreground',
                                                    isActive && 'text-primary',
                                                )}
                                                aria-hidden="true"
                                            />
                                            <span className="flex-1 truncate">
                                                {result.title}
                                            </span>
                                            {isActive && (
                                                <CornerDownLeft
                                                    className="size-3.5 shrink-0"
                                                    aria-hidden="true"
                                                />
                                            )}
                                        </button>
                                    </div>
                                );
                            })
                        )}
                    </div>

                    <div className="flex items-center gap-4 border-t border-border/60 bg-white/50 px-4 py-2.5 text-[11px] text-muted-foreground">
                        <span className="flex items-center gap-1.5">
                            <kbd className="rounded border border-border/80 bg-white px-1 font-sans">
                                ↵
                            </kbd>
                            Buka halaman
                        </span>
                        <span className="flex items-center gap-1.5">
                            <kbd className="rounded border border-border/80 bg-white px-1 font-sans">
                                ↑↓
                            </kbd>
                            Pilih
                        </span>
                        <span className="flex items-center gap-1.5">
                            <kbd className="rounded border border-border/80 bg-white px-1 font-sans">
                                Esc
                            </kbd>
                            Tutup
                        </span>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
